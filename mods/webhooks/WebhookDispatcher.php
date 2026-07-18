<?php
declare(strict_types=1);

namespace Phorum\Mod\Webhooks;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Fires outgoing webhook deliveries synchronously and best-effort — there is
 * no queue/retry infrastructure anywhere in this codebase (see MailService),
 * so a short Guzzle timeout bounds the worst case and every failure is
 * caught and logged rather than propagated into the triggering request.
 */
class WebhookDispatcher
{
    /** Event name => human-readable label, for the admin subscription checkboxes. */
    public const EVENTS = [
        'message.created'         => 'Message posted',
        'message.approved'        => 'Message approved',
        'message.deleted'         => 'Message deleted',
        'user.registered'         => 'User registered',
        'user.banned'             => 'User banned',
        'user.shadow_ban_changed' => 'User shadow-ban changed',
        'pm.sent'                 => 'Private message sent',
    ];

    private readonly ClientInterface $http;

    public function __construct(
        private readonly WebhookMapper $webhooks,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client(['timeout' => 5, 'connect_timeout' => 2]);
    }

    /** Fire $event to every active webhook subscribed to it. Never throws. */
    public function dispatch(string $event, array $data): void
    {
        $targets = $this->webhooks->findActiveForEvent($event);
        if (empty($targets)) {
            return;
        }

        $timestamp = time();
        foreach ($targets as $webhook) {
            $this->deliver($webhook, $event, $timestamp, $data);
        }
    }

    private function deliver(Webhook $webhook, string $event, int $timestamp, array $data): void
    {
        try {
            $body = $this->buildBody($webhook, $event, $timestamp, $data);
        } catch (\Throwable $e) {
            error_log("Webhooks: payload_template render failed for webhook #{$webhook->id}: {$e->getMessage()}");
            return;
        }

        $signature = hash_hmac('sha256', $body, $webhook->secret);

        try {
            $this->http->request('POST', $webhook->url, [
                'headers' => [
                    'Content-Type'       => $webhook->content_type,
                    'X-Phorum-Signature' => 'sha256=' . $signature,
                    'X-Phorum-Event'     => $event,
                ],
                'body' => $body,
            ]);
        } catch (GuzzleException $e) {
            error_log("Webhooks: delivery failed for webhook #{$webhook->id}: {$e->getMessage()}");
        }
    }

    /** The standard JSON envelope, or the webhook's custom Twig payload_template if set. */
    private function buildBody(Webhook $webhook, string $event, int $timestamp, array $data): string
    {
        $vars = ['event' => $event, 'timestamp' => $timestamp, 'data' => $data];

        if ($webhook->payload_template === null || trim($webhook->payload_template) === '') {
            return json_encode(
                ['event' => $event, 'timestamp' => $timestamp, 'data' => $data],
                JSON_THROW_ON_ERROR
            );
        }

        $twig = new Environment(new ArrayLoader());
        return $twig->createTemplate($webhook->payload_template)->render($vars);
    }
}

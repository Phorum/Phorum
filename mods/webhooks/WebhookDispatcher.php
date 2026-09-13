<?php
declare(strict_types=1);

namespace Phorum\Mod\Webhooks;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Phorum\Core\ErrorLogLogger;
use Psr\Log\LoggerInterface;

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

    /** HTTP client used for deliveries. */
    private readonly ClientInterface $http;

    /** Target check applied before every delivery. */
    private readonly WebhookUrlGuard $urlGuard;

    /** Destination for refused/failed delivery notices; error_log() by default. */
    private readonly LoggerInterface $logger;

    /**
     * @param WebhookMapper        $webhooks Source of the subscribed webhook rows.
     * @param ClientInterface|null $http     HTTP client; defaults to a short-timeout Guzzle client.
     * @param WebhookUrlGuard|null $urlGuard Target check; defaults to a plain WebhookUrlGuard.
     * @param LoggerInterface|null $logger   Log destination; defaults to ErrorLogLogger.
     */
    public function __construct(
        private readonly WebhookMapper $webhooks,
        ?ClientInterface  $http     = null,
        ?WebhookUrlGuard  $urlGuard = null,
        ?LoggerInterface  $logger   = null,
    ) {
        // Redirects are deliberately not followed: a public URL that 302s to
        // http://169.254.169.254/ would otherwise walk straight past the
        // target check below.
        $this->http = $http ?? new Client([
            'timeout'         => 5,
            'connect_timeout' => 2,
            'allow_redirects' => false,
        ]);
        $this->urlGuard = $urlGuard ?? new WebhookUrlGuard();
        $this->logger   = $logger ?? new ErrorLogLogger();
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
        // Re-checked per delivery, not just when the admin saved it: DNS
        // answers change, and rows predating this check are still in the table.
        $blocked = $this->urlGuard->problem($webhook->url);
        if ($blocked !== null) {
            $this->logger->warning(
                'Webhooks: refusing delivery for webhook #{webhook}: {reason}',
                ['webhook' => $webhook->id, 'reason' => $blocked]
            );
            return;
        }

        try {
            $body = $this->buildBody($webhook, $event, $timestamp, $data);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Webhooks: payload build failed for webhook #{webhook}: {error}',
                ['webhook' => $webhook->id, 'error' => $e->getMessage()]
            );
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
            $this->logger->error(
                'Webhooks: delivery failed for webhook #{webhook}: {error}',
                ['webhook' => $webhook->id, 'error' => $e->getMessage()]
            );
        }
    }

    /** The standard JSON envelope, or the webhook's custom payload_template if set. */
    private function buildBody(Webhook $webhook, string $event, int $timestamp, array $data): string
    {
        if ($webhook->payload_template === null || trim($webhook->payload_template) === '') {
            return json_encode(
                ['event' => $event, 'timestamp' => $timestamp, 'data' => $data],
                JSON_THROW_ON_ERROR
            );
        }

        return $this->substitute(
            $webhook->payload_template,
            ['event' => $event, 'timestamp' => $timestamp, 'data' => $data],
            str_contains(strtolower($webhook->content_type), 'json'),
        );
    }

    /**
     * Fill `{{ event }}`, `{{ timestamp }}`, and `{{ data.<field> }}` placeholders
     * in $template with values from $vars.
     *
     * Deliberately plain substitution rather than a template engine. This ran as
     * an unsandboxed Twig template until it was found to be a remote-code-execution
     * hole: `{{ ["..."]|map("system") }}` and friends turn admin panel access into
     * a shell, and `|map("file_get_contents")` reads any file the web user can. A
     * sandbox would have to keep an allowlist permanently free of map/filter/sort/
     * reduce to stay safe, because it vets filter names and not the callables
     * handed to them. There is no expression to evaluate here, so there is no
     * gadget to allowlist against.
     *
     * Substitution is single-pass (preg_replace_callback never rescans what it
     * inserted), so a value that itself contains "{{ ... }}" — a message subject,
     * say — is emitted literally and cannot reach another field.
     *
     * Unknown placeholders and non-scalar values render as empty; send the whole
     * event object by leaving the template blank and taking the standard envelope.
     *
     * @param array<string, mixed> $vars       Top-level variables available to the template.
     * @param bool                 $jsonEscape Escape each value for use inside a JSON
     *                                         string, per the webhook's content type.
     */
    private function substitute(string $template, array $vars, bool $jsonEscape): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z0-9_]+)*)\s*\}\}/',
            static function (array $m) use ($vars, $jsonEscape): string {
                $value = self::lookup($m[1], $vars);
                if ($value === null) {
                    return '';
                }
                return $jsonEscape ? self::jsonEscape($value) : $value;
            },
            $template
        );
    }

    /**
     * Resolve a dotted path ("data.subject") against $vars, as a string.
     * Returns null when the path doesn't exist or doesn't name a scalar.
     *
     * @param array<string, mixed> $vars
     */
    private static function lookup(string $path, array $vars): ?string
    {
        $current = $vars;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return match (true) {
            is_bool($current)   => $current ? 'true' : 'false',
            $current === null   => '',
            is_scalar($current) => (string) $current,
            default             => null,
        };
    }

    /**
     * Escape a value for use inside a JSON string literal, without the
     * surrounding quotes — so the template supplies its own quoting, as in
     * `{"text": "{{ data.subject }}"}`. This is what makes a subject containing
     * a quote or backslash produce valid JSON; the old Twig path HTML-escaped it
     * instead, sending `&quot;` to the receiving system.
     */
    private static function jsonEscape(string $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $encoded === false ? '' : substr($encoded, 1, -1);
    }
}

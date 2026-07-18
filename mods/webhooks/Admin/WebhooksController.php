<?php
declare(strict_types=1);

namespace Phorum\Mod\Webhooks\Admin;

use Phorum\Core\AdminAuth;
use Phorum\Core\Config;
use Phorum\Http\Controllers\Admin\AdminController;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mod\Webhooks\Webhook;
use Phorum\Mod\Webhooks\WebhookDispatcher;
use Phorum\Mod\Webhooks\WebhookMapper;
use Twig\Environment;

/** Admin CRUD for outgoing webhook subscriptions. Routed via a fully-qualified action in etc/routes.php. */
class WebhooksController extends AdminController
{
    private readonly WebhookMapper $webhooks;
    private readonly ModLogMapper  $modLog;

    public function __construct(
        Config          $config,
        Environment     $twig,
        ?WebhookMapper  $webhooks = null,
        ?ModLogMapper   $modLog   = null,
    ) {
        parent::__construct($config, $twig);
        $this->webhooks = $webhooks ?? new WebhookMapper();
        $this->modLog   = $modLog   ?? new ModLogMapper();
    }

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $webhooks     = $this->webhooks->find(filter: [], order: 'id ASC') ?? [];
        $eventsById   = [];
        foreach ($webhooks as $webhook) {
            $eventsById[$webhook->id] = WebhookMapper::decodeEvents($webhook->events);
        }

        return $this->respond($this->renderAdmin('admin/mods/webhooks/index.html.twig', [
            'webhooks'     => $webhooks,
            'events'       => WebhookDispatcher::EVENTS,
            'events_by_id' => $eventsById,
        ]));
    }

    public function create(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $errors  = [];
        $webhook = new Webhook();
        $webhook->secret = bin2hex(random_bytes(32));

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $errors = $this->applyPost($webhook, $request);

            if (empty($errors)) {
                $webhook->created_at = time();
                $this->webhooks->save($webhook);
                $this->logAction('create', $webhook);
                return $this->redirect('/admin/webhooks');
            }
        }

        return $this->respond($this->renderAdmin('admin/mods/webhooks/edit.html.twig', [
            'webhook' => $webhook,
            'events'  => WebhookDispatcher::EVENTS,
            'selected_events' => WebhookMapper::decodeEvents($webhook->events),
            'errors'  => $errors,
            'is_new'  => true,
        ]));
    }

    public function edit(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $webhookId = (int) ($request->tokens['webhook_id'] ?? 0);
        $webhook   = $this->webhooks->load($webhookId);
        if ($webhook === null) { return $this->notFound(); }

        $errors = [];

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $errors = $this->applyPost($webhook, $request);

            if (empty($errors)) {
                $this->webhooks->save($webhook);
                $this->logAction('update', $webhook);
                return $this->redirect('/admin/webhooks');
            }
        }

        return $this->respond($this->renderAdmin('admin/mods/webhooks/edit.html.twig', [
            'webhook' => $webhook,
            'events'  => WebhookDispatcher::EVENTS,
            'selected_events' => WebhookMapper::decodeEvents($webhook->events),
            'errors'  => $errors,
            'is_new'  => false,
        ]));
    }

    public function delete(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $webhookId = (int) ($request->tokens['webhook_id'] ?? 0);
        $webhook   = $this->webhooks->load($webhookId);
        if ($webhook === null) { return $this->notFound(); }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $this->logAction('delete', $webhook);
            $this->webhooks->delete($webhook->id);
            return $this->redirect('/admin/webhooks');
        }

        return $this->respond($this->renderAdmin('admin/mods/webhooks/delete_confirm.html.twig', [
            'webhook' => $webhook,
        ]));
    }

    // -------------------------------------------------------------------------

    private function applyPost(Webhook $webhook, Request $request): array
    {
        $errors = [];

        $url = trim($request->post['url'] ?? '');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('!^https?://!i', $url)) {
            $errors[] = 'A valid http:// or https:// URL is required.';
        }

        $selectedEvents = array_values(array_intersect(
            $request->post['events'] ?? [],
            array_keys(WebhookDispatcher::EVENTS)
        ));

        if (empty($errors)) {
            $webhook->url              = $url;
            $webhook->events           = WebhookMapper::encodeEvents($selectedEvents);
            $webhook->active           = !empty($request->post['active']) ? 1 : 0;
            $webhook->payload_template = trim($request->post['payload_template'] ?? '') !== ''
                                       ? $request->post['payload_template']
                                       : null;
            $webhook->content_type     = trim($request->post['content_type'] ?? '') !== ''
                                       ? trim($request->post['content_type'])
                                       : 'application/json';

            if (!empty($request->post['regenerate_secret'])) {
                $webhook->secret = bin2hex(random_bytes(32));
            }
        }

        return $errors;
    }

    private function logAction(string $action, Webhook $webhook): void
    {
        $admin = AdminAuth::user();
        $this->modLog->record(
            userId:     $admin?->user_id ?? 0,
            action:     $action,
            objectType: 'webhook',
            objectId:   $webhook->id,
            forumId:    0,
            details:    $webhook->url,
        );
    }
}

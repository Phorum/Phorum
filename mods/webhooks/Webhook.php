<?php
declare(strict_types=1);

namespace Phorum\Mod\Webhooks;

/**
 * One configured outgoing webhook subscription.
 *
 * `events` is a JSON-encoded array of event name strings (e.g.
 * '["message.created","user.registered"]') — decode/encode it via
 * WebhookMapper rather than handling JSON inline at call sites.
 */
class Webhook
{
    public int     $id               = 0;
    public string  $url              = '';
    public string  $secret           = '';
    public string  $events           = '[]';
    public int     $active           = 1;
    public ?string $payload_template = null;
    public string  $content_type     = 'application/json';
    public int     $created_at       = 0;
}

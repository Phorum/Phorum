<?php
declare(strict_types=1);

namespace Phorum\Mod\Webhooks;

use Phorum\Core\Config;

// No PSR-4 autoloading exists for mods/ (composer.json only autoloads
// Phorum\ => src/), so this module requires its own sibling files —
// exactly like mods/bbcode/bbcode.php does.
require_once __DIR__ . '/Webhook.php';
require_once __DIR__ . '/WebhookMapper.php';
require_once __DIR__ . '/WebhookUrlGuard.php';
require_once __DIR__ . '/WebhookDispatcher.php';
require_once __DIR__ . '/WebhookHooks.php';
require_once __DIR__ . '/Admin/WebhooksController.php';

// Self-register hook handlers so the app only needs to require this file;
// no additional registration code needed in App.php.
//
// App::initModules() doesn't pass its own Config instance into module boot
// files, so this mirrors mods/oauth/oauth.php — a single small PHP array
// file, opcache-cached — to read whether internal webhook targets are allowed.
$webhookConfig = new Config(ROOT_PATH . '/etc/phorum.php');

WebhookHooks::register(new WebhookDispatcher(
    new WebhookMapper(),
    null,
    new WebhookUrlGuard((bool) $webhookConfig->get('webhook_allow_private_targets', false)),
));

<?php
declare(strict_types=1);

namespace Phorum\Mod\Oauth;

use Phorum\Core\Config;

// No PSR-4 autoloading exists for mods/ (composer.json only autoloads
// Phorum\ => src/), so this module requires its own sibling files —
// exactly like mods/webhooks/webhooks.php and mods/s3storage/s3storage.php do.
require_once __DIR__ . '/OauthEmailNotVerifiedException.php';
require_once __DIR__ . '/OauthIdentity.php';
require_once __DIR__ . '/OauthIdentityMapper.php';
require_once __DIR__ . '/OauthService.php';
require_once __DIR__ . '/OauthHooks.php';
require_once __DIR__ . '/Controllers/OauthController.php';
require_once __DIR__ . '/Admin/OauthController.php';

// App::initModules() doesn't pass its own Config instance into module boot
// files, so this mirrors public/index.php's own bootstrap line — cheap (a
// single small PHP array file, opcache-cached), needed only so the
// auth_login_buttons hook can build base_path-aware button URLs.
OauthHooks::register(new Config(ROOT_PATH . '/etc/phorum.php'));

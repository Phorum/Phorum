<?php
declare(strict_types=1);

namespace Phorum\Mod\S3Storage;

use Phorum\Mapper\SettingMapper;

// No PSR-4 autoloading exists for mods/ (composer.json only autoloads
// Phorum\ => src/), so this module requires its own sibling files —
// exactly like mods/bbcode/bbcode.php and mods/webhooks/webhooks.php do.
require_once __DIR__ . '/S3StorageService.php';
require_once __DIR__ . '/S3StorageHooks.php';
require_once __DIR__ . '/Admin/S3Controller.php';

// Self-register hook handlers so the app only needs to require this file;
// no additional registration code needed in App.php.
S3StorageHooks::register(new S3StorageService(new SettingMapper()));

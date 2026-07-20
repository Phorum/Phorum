<?php
declare(strict_types=1);

namespace Phorum\Mod\Cdn;

use Phorum\Mapper\SettingMapper;

// No PSR-4 autoloading exists for mods/ (composer.json only autoloads
// Phorum\ => src/), so this module requires its own sibling files —
// exactly like mods/bbcode/bbcode.php and mods/s3storage/s3storage.php do.
require_once __DIR__ . '/CdnService.php';
require_once __DIR__ . '/CdnHooks.php';
require_once __DIR__ . '/Admin/CdnController.php';

// Self-register hook handlers so the app only needs to require this file;
// no additional registration code needed in App.php.
CdnHooks::register(new CdnService(new SettingMapper()));

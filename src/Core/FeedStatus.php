<?php
declare(strict_types=1);

namespace Phorum\Core;

use Phorum\Mapper\SettingMapper;

/**
 * Request-scoped cache of the enable_rss site setting, resolved once in
 * App::run() and read cheaply everywhere else (Controller::baseData() for
 * template discoverability links, and FeedController's own gate) — mirrors
 * the Auth/AdminAuth/SiteStatus static-init pattern so per-render template
 * data stays DB-free.
 */
class FeedStatus
{
    private static bool $enabled = true;

    public static function initialize(SettingMapper $settings): void
    {
        $value         = $settings->getSetting('enable_rss');
        self::$enabled = $value === null ? true : (bool) $value;
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function clear(): void
    {
        self::$enabled = true;
    }
}

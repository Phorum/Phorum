<?php
declare(strict_types=1);

namespace Phorum\Core;

use Phorum\Mapper\SettingMapper;

/**
 * Request-scoped cache of the site_name setting, resolved once in App::run()
 * and read cheaply everywhere else — mirrors the Auth/AdminAuth/SiteStatus/
 * FeedStatus static-init pattern so per-render template data and outbound
 * emails stay DB-free after the initial lookup.
 *
 * Unlike enable_rss/file_uploads (DB-only, no phorum.php counterpart),
 * site_name has always had a phorum.php value too — that value is only the
 * *fallback default* here, used until an admin sets one in the database.
 */
class SiteSettings
{
    private static string $name = 'Phorum';

    public static function initialize(SettingMapper $settings, string $configDefault): void
    {
        $value      = $settings->getSetting('site_name');
        self::$name = ($value !== null && $value !== '') ? (string) $value : $configDefault;
    }

    public static function name(): string
    {
        return self::$name;
    }

    public static function clear(): void
    {
        self::$name = 'Phorum';
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Core;

use Phorum\Service\SiteStatusService;

/**
 * Request-scoped cache of the site-wide status, resolved once in App::run()
 * and read cheaply everywhere else (e.g. the read-only banner in
 * Controller::baseData()) — mirrors the Auth/AdminAuth/Impersonation
 * static-init pattern so per-render template data stays DB-free.
 */
class SiteStatus
{
    private static string $current = SiteStatusService::NORMAL;

    public static function initialize(SiteStatusService $service): void
    {
        self::$current = $service->current();
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function isReadOnly(): bool
    {
        return self::$current === SiteStatusService::READ_ONLY;
    }

    public static function clear(): void
    {
        self::$current = SiteStatusService::NORMAL;
    }
}

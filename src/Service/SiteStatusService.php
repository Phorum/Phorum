<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\SettingMapper;

/**
 * Site-wide status: a single install-level kill switch, independent of the
 * per-forum pub_perms/reg_perms permission bits. Ported from Phorum 6's
 * $PHORUM['status'] setting (the "Phorum Status" select shown across the
 * legacy admin panel).
 */
class SiteStatusService
{
    public const NORMAL     = 'normal';
    public const READ_ONLY  = 'read-only';
    public const ADMIN_ONLY = 'admin-only';
    public const DISABLED   = 'disabled';

    public const LABELS = [
        self::NORMAL     => 'Normal',
        self::READ_ONLY  => 'Read Only',
        self::ADMIN_ONLY => 'Admin Only',
        self::DISABLED   => 'Disabled',
    ];

    private readonly SettingMapper $settings;

    public function __construct(?SettingMapper $settings = null)
    {
        $this->settings = $settings ?? new SettingMapper();
    }

    /** One of the class constants; defaults to NORMAL if unset, unrecognized, or unreadable (e.g. pre-install). */
    public function current(): string
    {
        try {
            $value = $this->settings->getSetting('status');
        } catch (\Throwable) {
            return self::NORMAL;
        }
        return is_string($value) && isset(self::LABELS[$value]) ? $value : self::NORMAL;
    }

    /** Persist a new status; silently ignores unrecognized values. */
    public function set(string $status): void
    {
        if (!isset(self::LABELS[$status])) {
            return;
        }
        $this->settings->saveSetting('status', $status);
    }
}

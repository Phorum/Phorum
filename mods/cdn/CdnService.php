<?php
declare(strict_types=1);

namespace Phorum\Mod\Cdn;

use Phorum\Mapper\SettingMapper;

/** Resolves the configured CDN base URL for attachment/avatar links. */
class CdnService
{
    public function __construct(private readonly SettingMapper $settings) {}

    /**
     * Return $routePath rewritten onto the configured CDN base URL, or null
     * if no base URL is configured (caller should fall back to its own default).
     */
    public function urlFor(string $routePath): ?string
    {
        $base = trim((string) ($this->settings->getSetting('cdn_base_url') ?? ''));
        if ($base === '') {
            return null;
        }
        return rtrim($base, '/') . $routePath;
    }
}

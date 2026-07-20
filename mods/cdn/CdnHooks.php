<?php
declare(strict_types=1);

namespace Phorum\Mod\Cdn;

use Phorum\Hook\HookDispatcher;

/** Registers the CDN module's listeners on the core attachment_url/avatar_url hooks. */
class CdnHooks
{
    public static function register(CdnService $cdn, ?HookDispatcher $hooks = null): void
    {
        $hooks ??= HookDispatcher::getInstance();

        $hooks->register(
            hook:     'attachment_url',
            callback: fn(string $default, string $routePath, int $fileId, string $filename): ?string
                => $cdn->urlFor($routePath),
        );

        $hooks->register(
            hook:     'avatar_url',
            callback: fn(string $default, string $routePath, int $userId): ?string
                => $cdn->urlFor($routePath),
        );
    }
}

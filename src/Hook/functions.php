<?php
declare(strict_types=1);

use Phorum\Hook\HookDispatcher;

/**
 * Procedural wrapper around HookDispatcher for backward compatibility
 * with existing Phorum modules that call phorum_api_hook() directly.
 *
 * Usage matches the old Phorum convention:
 *   $data = phorum_api_hook('hook_name', $data, $extra_arg, ...);
 */
function phorum_api_hook(string $hook, mixed $data = null, mixed ...$args): mixed
{
    return HookDispatcher::getInstance()->dispatch($hook, $data, ...$args);
}

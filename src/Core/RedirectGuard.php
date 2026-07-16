<?php
declare(strict_types=1);

namespace Phorum\Core;

/**
 * Shared open-redirect guard and change-password URL builder, used by every
 * place that carries a post-login/post-password-change "redirect" target
 * (AuthController::login(), App::blockedByForcePasswordChange(),
 * UserController::forcePasswordChange()) so the same allow-list rule and the
 * same URL shape aren't hand-copied at each call site.
 */
final class RedirectGuard
{
    /**
     * Only same-site relative paths are allowed as a redirect target —
     * rejects protocol-relative ("//evil.com") and absolute/external URLs.
     */
    public static function sanitizePath(?string $path): string
    {
        $path = (string) $path;
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return '/';
        }
        return $path;
    }

    /**
     * Site-relative change-password URL carrying a sanitized post-change
     * redirect target as a query param. Does not include base_path — callers
     * that aren't going through Controller::redirect() (which prepends it
     * automatically) must prepend it themselves.
     */
    public static function changePasswordUrl(?string $redirect): string
    {
        return '/user/change-password?redirect=' . urlencode(self::sanitizePath($redirect));
    }
}

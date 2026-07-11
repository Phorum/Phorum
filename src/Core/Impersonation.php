<?php
declare(strict_types=1);

namespace Phorum\Core;

use Phorum\Mapper\UserMapper;
use Phorum\Model\User;

/**
 * Lets an admin browse the front end as another user (for support/debugging)
 * without disturbing the admin's own AdminAuth session.
 *
 * Uses a HMAC-signed, time-stamped cookie (phorum_impersonate) — same
 * cookie-only approach as AdminAuth, so no DB schema changes are needed.
 * The real admin's identity is only ever accepted from a currently-valid
 * AdminAuth session; the impersonation cookie alone cannot authenticate one.
 */
class Impersonation
{
    private const COOKIE_NAME = 'phorum_impersonate';
    private const TIMEOUT     = 1800; // 30 minutes, same window as AdminAuth

    private static ?User $admin = null;

    public static function initialize(Config $config, ?UserMapper $mapper = null): void
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($cookie === '') {
            return;
        }

        $decoded = base64_decode($cookie, strict: true);
        if ($decoded === false) {
            return;
        }

        $parts = explode(':', $decoded, 4);
        if (count($parts) !== 4) {
            return;
        }

        [$adminId, $targetId, $timestamp, $hmac] = $parts;
        $adminId   = (int) $adminId;
        $targetId  = (int) $targetId;
        $timestamp = (int) $timestamp;

        if (time() - $timestamp > self::TIMEOUT) {
            self::clearCookie($config);
            return;
        }

        if (!hash_equals(self::makeHmac($adminId, $targetId, $timestamp, $config), $hmac)) {
            return;
        }

        $admin = AdminAuth::user();
        if ($admin === null || $admin->user_id !== $adminId) {
            self::clearCookie($config);
            return;
        }

        $mapper = $mapper ?? new UserMapper();
        $target = $mapper->load($targetId);
        if ($target === null || !$target->active || $target->admin) {
            self::clearCookie($config);
            return;
        }

        self::$admin = $admin;
        Auth::setUser($target);
        // Slide the expiry window
        self::setCookie($adminId, $targetId, $config);
    }

    /**
     * Start impersonating $target as $admin. Returns false without side
     * effects if $target is not a valid impersonation target.
     */
    public static function start(User $admin, User $target, Config $config): bool
    {
        if (!$target->active || $target->admin || $target->user_id === $admin->user_id) {
            return false;
        }

        self::$admin = $admin;
        Auth::setUser($target);
        self::setCookie($admin->user_id, $target->user_id, $config);
        return true;
    }

    /**
     * Stop impersonating and restore the real front-end session (if any)
     * from the untouched Auth cookies.
     */
    public static function stop(Config $config): void
    {
        self::$admin = null;
        self::clearCookie($config);
        Auth::clear();
        Auth::initialize(new UserMapper());
    }

    public static function isActive(): bool
    {
        return self::$admin !== null;
    }

    /** The real admin currently impersonating, or null if not impersonating. */
    public static function admin(): ?User
    {
        return self::$admin;
    }

    private static function setCookie(int $adminId, int $targetId, Config $config): void
    {
        $timestamp = time();
        $hmac      = self::makeHmac($adminId, $targetId, $timestamp, $config);
        $value     = base64_encode("{$adminId}:{$targetId}:{$timestamp}:{$hmac}");

        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => 0, // browser-session cookie
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => (bool) $config->get('session_secure', false),
        ]);
    }

    private static function clearCookie(Config $config): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => 1,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => (bool) $config->get('session_secure', false),
        ]);
    }

    private static function makeHmac(int $adminId, int $targetId, int $timestamp, Config $config): string
    {
        $secret = (string) ($config->get('admin_secret') ?? '');
        if ($secret === '') {
            throw new \RuntimeException('admin_secret must be set in etc/phorum.php');
        }
        return hash_hmac('sha256', "{$adminId}:{$targetId}:{$timestamp}", $secret);
    }
}

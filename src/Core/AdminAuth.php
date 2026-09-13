<?php
declare(strict_types=1);

namespace Phorum\Core;

use Phorum\Mapper\UserMapper;
use Phorum\Model\User;

/**
 * Manages the separate admin session cookie (phorum_admin_session).
 * Uses a HMAC-signed, time-stamped token so no extra DB column is needed.
 */
class AdminAuth
{
    private const COOKIE_NAME = 'phorum_admin_session';
    private const TIMEOUT     = 1800; // 30 minutes

    private static ?User $admin = null;

    public static function initialize(Config $config): void
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($cookie === '') {
            return;
        }

        // Fail closed rather than throwing: a misconfigured secret shouldn't
        // 500 every page for anyone still holding an admin cookie. No admin
        // session is established, and Admin\LoginController explains why.
        if (!AdminSecret::isUsable($config)) {
            return;
        }

        $decoded = base64_decode($cookie, strict: true);
        if ($decoded === false) {
            return;
        }

        $parts = explode(':', $decoded, 3);
        if (count($parts) !== 3) {
            return;
        }

        [$userId, $timestamp, $hmac] = $parts;
        $userId    = (int) $userId;
        $timestamp = (int) $timestamp;

        if (time() - $timestamp > self::TIMEOUT) {
            self::logout($config);
            return;
        }

        if (!hash_equals(self::makeHmac($userId, $timestamp, $config), $hmac)) {
            return;
        }

        $user = (new UserMapper())->load($userId);
        if ($user === null || !$user->admin || $user->active !== 1) {
            self::logout($config);
            return;
        }

        self::$admin = $user;
        // Slide the expiry window
        self::setAdminCookie($user->user_id, $config);
    }

    public static function login(User $user, Config $config): void
    {
        // Elevating to an admin session is an identity change too.
        CsrfGuard::rotate();

        self::$admin = $user;
        self::setAdminCookie($user->user_id, $config);
    }

    public static function logout(Config $config): void
    {
        self::$admin = null;
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => 1,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => (bool) $config->get('session_secure', false),
        ]);
    }

    public static function user(): ?User
    {
        return self::$admin;
    }

    private static function setAdminCookie(int $userId, Config $config): void
    {
        $timestamp = time();
        $hmac      = self::makeHmac($userId, $timestamp, $config);
        $value     = base64_encode("{$userId}:{$timestamp}:{$hmac}");

        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => 0, // browser-session cookie
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => (bool) $config->get('session_secure', false),
        ]);
    }

    private static function makeHmac(int $userId, int $timestamp, Config $config): string
    {
        return hash_hmac('sha256', "{$userId}:{$timestamp}", AdminSecret::get($config));
    }
}

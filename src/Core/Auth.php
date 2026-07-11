<?php
declare(strict_types=1);

namespace Phorum\Core;

use Phorum\Mapper\UserMapper;
use Phorum\Model\User;

class Auth
{
    public const COOKIE_LT    = 'phorum_session_v5';
    public const COOKIE_ST    = 'phorum_session_st';
    public const COOKIE_ADMIN = 'phorum_admin_session';

    private static ?User $user = null;

    /**
     * Resolve the current user from cookies. Call once per request before dispatch.
     * Short-term session takes priority; falls back to long-term (remember-me).
     */
    public static function initialize(UserMapper $mapper): void
    {
        // Short-term session — has an expiry stored in the DB
        $stToken = $_COOKIE[self::COOKIE_ST] ?? '';
        if ($stToken !== '') {
            $user = $mapper->findBySessionSt($stToken);
            if ($user !== null && $user->sessid_st_timeout > time()) {
                self::$user = $user;
                phorum_api_hook('user_session_restore', $user);
                return;
            }
        }

        // Long-term session — remember-me cookie
        $ltToken = $_COOKIE[self::COOKIE_LT] ?? '';
        if ($ltToken !== '') {
            $user = $mapper->findBySessionLt($ltToken);
            if ($user !== null && $user->active) {
                self::$user = $user;
                phorum_api_hook('user_session_restore', $user);
            }
        }
    }

    public static function user(): ?User
    {
        return self::$user;
    }

    public static function isLoggedIn(): bool
    {
        return self::$user !== null;
    }

    public static function isAdmin(): bool
    {
        return self::$user?->admin === 1;
    }

    public static function setUser(User $user): void
    {
        self::$user = $user;
    }

    public static function clear(): void
    {
        self::$user = null;
    }
}

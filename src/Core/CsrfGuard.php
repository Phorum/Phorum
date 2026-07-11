<?php
declare(strict_types=1);

namespace Phorum\Core;

class CsrfGuard
{
    private const SESSION_KEY  = 'phorum_csrf_token';
    private const FIELD_NAME   = 'csrf_token';

    public static function token(): string
    {
        self::startSession();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(string $token): bool
    {
        self::startSession();

        $stored = $_SESSION[self::SESSION_KEY] ?? '';
        return $stored !== '' && hash_equals($stored, $token);
    }

    public static function fieldName(): string
    {
        return self::FIELD_NAME;
    }

    /** Render the hidden input HTML. */
    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . $token . '">';
    }

    private static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }
}

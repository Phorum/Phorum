<?php
declare(strict_types=1);

namespace Phorum\Core;

/**
 * Issues and validates the per-session CSRF token, and owns the one place
 * this application starts a PHP session.
 *
 * The token lives in $_SESSION, so whoever knows the session id knows the
 * token. That makes session fixation a CSRF bypass rather than a theoretical
 * problem: without `session.use_strict_mode` PHP adopts whatever session id a
 * client sends, so an attacker able to plant a cookie could choose the id,
 * read the token it contains, and forge state-changing POSTs as the victim.
 * startSession() turns strict mode on, and rotate() re-keys both the session
 * id and the token whenever the authenticated identity changes.
 */
class CsrfGuard
{
    private const SESSION_KEY  = 'phorum_csrf_token';
    private const FIELD_NAME   = 'csrf_token';

    /** Whether the session cookie should carry the Secure flag (config: session_secure). */
    private static bool $secureCookie = false;

    /**
     * Pick up cookie settings from config. Call once per request during boot,
     * before anything renders a form. Without it the session cookie falls back
     * to not setting Secure, which is the right default for a plain-HTTP dev
     * install and wrong for production — hence the config flag.
     */
    public static function initialize(Config $config): void
    {
        self::$secureCookie = (bool) $config->get('session_secure', false);
    }

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

    /**
     * Give the visitor a new session id and a new CSRF token.
     *
     * Call on every change of authenticated identity — logging in, logging
     * out, elevating to an admin session. A session id an attacker managed to
     * fix before login then stops being the one the authenticated user is
     * using, and any token they had already read is no longer valid.
     *
     * A no-op when the response has already begun, since neither the session
     * cookie nor a regenerated id could reach the browser at that point.
     */
    public static function rotate(): void
    {
        self::startSession();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Start the PHP session if one isn't running, with this application's
     * cookie settings. Public so the OAuth module can stash its `state` value
     * in the same session under the same settings rather than starting one of
     * its own with a hand-copied (and drift-prone) set of parameters.
     */
    public static function ensureSession(): void
    {
        self::startSession();
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        // Refuse a session id the client invented rather than adopting it.
        // This is what stops an attacker choosing the id — and so knowing the
        // CSRF token stored under it — before the victim ever logs in.
        ini_set('session.use_strict_mode', '1');

        session_set_cookie_params([
            'path'     => '/',
            'secure'   => self::$secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

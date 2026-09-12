<?php
declare(strict_types=1);

namespace Phorum\Core;

/**
 * Validates the `admin_secret` config value that signs admin-session and
 * impersonation cookies.
 *
 * Those cookies carry no server-side state — they are just
 * `base64(ids:timestamp:hmac)` — so the secret is the only thing standing
 * between a visitor and a forged admin session. `etc/phorum.example.php`
 * shipped a working placeholder, which meant an install that never changed it
 * was signing with a value published in the source tree. A secret that is
 * empty, still a placeholder, or too short to be a credible HMAC key is
 * therefore treated as a configuration error rather than silently used.
 *
 * Single source of truth for both AdminAuth and Impersonation, which sign
 * different payloads with the same secret.
 */
final class AdminSecret
{
    /** Minimum length for a usable secret, in characters (128 bits as hex). */
    public const MIN_LENGTH = 32;

    /**
     * Substrings marking a value as an unedited example. Matched
     * case-insensitively anywhere in the secret, so reworded variants of the
     * shipped placeholders are caught too.
     */
    private const PLACEHOLDER_MARKERS = [
        'change-me',
        'changeme',
        'replace-with',
        'replacewith',
        'your-secret',
        'long-random-string',
    ];

    /**
     * Describe why the configured secret can't be used, or null when it's fine.
     * Returns a message rather than throwing so callers can fail closed and
     * show it, instead of turning a misconfiguration into a 500 on every page.
     */
    public static function problem(Config $config): ?string
    {
        $secret = (string) ($config->get('admin_secret') ?? '');

        if ($secret === '') {
            return 'admin_secret is not set in etc/phorum.php. '
                 . 'Generate one with: php -r "echo bin2hex(random_bytes(32));"';
        }

        foreach (self::PLACEHOLDER_MARKERS as $marker) {
            if (stripos($secret, $marker) !== false) {
                return 'admin_secret in etc/phorum.php is still the example placeholder. '
                     . 'That value is published in the Phorum source, so anyone could forge an '
                     . 'admin session cookie. Generate one with: php -r "echo bin2hex(random_bytes(32));"';
            }
        }

        if (strlen($secret) < self::MIN_LENGTH) {
            return sprintf(
                'admin_secret in etc/phorum.php must be at least %d characters; it is %d. '
                . 'Generate one with: php -r "echo bin2hex(random_bytes(32));"',
                self::MIN_LENGTH,
                strlen($secret),
            );
        }

        return null;
    }

    /** True when the configured secret is safe to sign cookies with. */
    public static function isUsable(Config $config): bool
    {
        return self::problem($config) === null;
    }

    /**
     * The configured secret, for signing.
     *
     * @throws \RuntimeException when it isn't usable — a backstop for callers
     *         that reach signing without having checked problem() first.
     */
    public static function get(Config $config): string
    {
        $problem = self::problem($config);
        if ($problem !== null) {
            throw new \RuntimeException($problem);
        }

        return (string) $config->get('admin_secret');
    }
}

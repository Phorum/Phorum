<?php
declare(strict_types=1);

namespace Phorum\Core;

/**
 * Response headers sent on every request.
 *
 * The default Content-Security-Policy deliberately omits `script-src` and
 * `style-src`. The templates carry eight inline `<script>` blocks, a handful
 * of inline event handlers (`onclick`, `onchange`, `onerror`, `onsubmit`),
 * inline `<style>` blocks, and many `style=` attributes; the admin, install
 * and upgrade pages additionally pull Materialize and Material Icons from
 * cdnjs and fonts.googleapis.com. A policy covering those would need
 * `'unsafe-inline'`, which is the one value that makes a script-src policy
 * stop being XSS protection — a header that reads strict while blocking
 * nothing is worse than an honest omission, because it invites the assumption
 * that XSS is already contained.
 *
 * What is here instead are the directives that work without a template
 * refactor and still close real attacks: `base-uri` stops an injected
 * `<base>` from re-pointing every relative URL on the page, `form-action`
 * stops an injected form posting credentials off-site, `object-src` removes
 * the plugin-based script vectors, and `frame-ancestors` (with the older
 * X-Frame-Options alongside it) stops clickjacking of admin actions.
 *
 * Operators who can test a full policy can set `content_security_policy` in
 * etc/phorum.php, which replaces the default outright.
 */
final class SecurityHeaders
{
    /**
     * The default policy. See the class docblock for why script-src and
     * style-src aren't in it.
     */
    public const DEFAULT_CSP = "base-uri 'self'; object-src 'none'; form-action 'self'; frame-ancestors 'self'";

    /**
     * Build the header set for this request.
     *
     * Pure, so it can be asserted on directly; send() is the thin wrapper that
     * actually emits them.
     *
     * @return array<string, string> Header name => value.
     */
    public static function build(Config $config): array
    {
        $headers = [
            // Never let a browser second-guess a declared Content-Type. This
            // is what keeps an attachment served as octet-stream from being
            // sniffed back into something executable.
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'SAMEORIGIN',
            // Keeps URLs — including the token in a password-reset link — from
            // being handed to third-party sites in the Referer header.
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => self::policy($config),
        ];

        $hsts = self::strictTransportSecurity($config);
        if ($hsts !== null) {
            $headers['Strict-Transport-Security'] = $hsts;
        }

        return $headers;
    }

    /** Emit the header set. No-op once the response body has started. */
    public static function send(Config $config): void
    {
        if (headers_sent()) {
            return;
        }

        foreach (self::build($config) as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    /** The configured policy, or the built-in default. */
    private static function policy(Config $config): string
    {
        $configured = trim((string) ($config->get('content_security_policy', '') ?? ''));

        return $configured !== '' ? $configured : self::DEFAULT_CSP;
    }

    /**
     * The HSTS header value, or null when it shouldn't be sent.
     *
     * Off unless `hsts_max_age` is set to a positive number of seconds, and
     * then only over HTTPS: HSTS is not reversible within its own lifetime, so
     * a site that sends it before TLS is working properly locks browsers out
     * of itself until the max-age expires. It also requires `session_secure`,
     * since a site still issuing cookies over plain HTTP isn't ready for it.
     */
    private static function strictTransportSecurity(Config $config): ?string
    {
        $maxAge = (int) ($config->get('hsts_max_age', 0) ?? 0);
        if ($maxAge <= 0 || !$config->get('session_secure', false)) {
            return null;
        }

        $value = 'max-age=' . $maxAge;
        if ($config->get('hsts_include_subdomains', false)) {
            $value .= '; includeSubDomains';
        }

        return $value;
    }
}

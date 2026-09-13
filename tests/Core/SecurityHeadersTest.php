<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\Config;
use Phorum\Core\SecurityHeaders;
use PHPUnit\Framework\TestCase;

/**
 * Covers the response headers sent on every request.
 *
 * Asserts build() rather than send(), since the latter calls header() and
 * can't be inspected from a test.
 */
class SecurityHeadersTest extends TestCase
{
    /** Build a Config returning $values, falling through to each call's default. */
    private function configWith(array $values = []): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $values[$key] ?? $default
        );
        return $config;
    }

    /** The baseline headers are present on an otherwise empty config. */
    public function testBaselineHeadersArePresent(): void
    {
        $headers = SecurityHeaders::build($this->configWith());

        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
        $this->assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
        $this->assertArrayHasKey('Content-Security-Policy', $headers);
    }

    /**
     * Referrer-Policy is what stops a password-reset URL — token and all —
     * being handed to a third-party site in the Referer header.
     */
    public function testReferrerPolicyDoesNotLeakUrlsCrossOrigin(): void
    {
        $headers = SecurityHeaders::build($this->configWith());

        $this->assertContains(
            $headers['Referrer-Policy'],
            ['strict-origin-when-cross-origin', 'no-referrer', 'same-origin', 'strict-origin'],
        );
    }

    /** The default policy carries the directives that work without a refactor. */
    public function testDefaultPolicyRestrictsTheDirectivesItCan(): void
    {
        $csp = SecurityHeaders::build($this->configWith())['Content-Security-Policy'];

        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    /**
     * The default must not claim a script-src it can't honour. Shipping
     * "script-src ... 'unsafe-inline'" would read as protection while allowing
     * exactly the injection it appears to stop.
     */
    public function testDefaultPolicyDoesNotShipAnUnsafeInlineScriptSrc(): void
    {
        $csp = SecurityHeaders::build($this->configWith())['Content-Security-Policy'];

        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringNotContainsString('script-src', $csp);
    }

    /** An operator-supplied policy replaces the default outright. */
    public function testConfiguredPolicyOverridesTheDefault(): void
    {
        $headers = SecurityHeaders::build($this->configWith([
            'content_security_policy' => "default-src 'self'",
        ]));

        $this->assertSame("default-src 'self'", $headers['Content-Security-Policy']);
    }

    /** A blank configured policy falls back to the default rather than sending nothing. */
    public function testBlankConfiguredPolicyFallsBackToDefault(): void
    {
        $headers = SecurityHeaders::build($this->configWith(['content_security_policy' => '   ']));

        $this->assertSame(SecurityHeaders::DEFAULT_CSP, $headers['Content-Security-Policy']);
    }

    /** HSTS is off unless asked for — it can't be withdrawn once sent. */
    public function testHstsIsOffByDefault(): void
    {
        $this->assertArrayNotHasKey('Strict-Transport-Security', SecurityHeaders::build($this->configWith()));
    }

    /**
     * HSTS is withheld over plain HTTP even when a max-age is configured: a
     * site not yet issuing secure cookies isn't ready to be pinned to HTTPS.
     */
    public function testHstsIsWithheldWithoutSecureCookies(): void
    {
        $headers = SecurityHeaders::build($this->configWith([
            'hsts_max_age'   => 31536000,
            'session_secure' => false,
        ]));

        $this->assertArrayNotHasKey('Strict-Transport-Security', $headers);
    }

    /** With HTTPS and a max-age, HSTS is sent. */
    public function testHstsIsSentWhenConfiguredOverHttps(): void
    {
        $headers = SecurityHeaders::build($this->configWith([
            'hsts_max_age'   => 300,
            'session_secure' => true,
        ]));

        $this->assertSame('max-age=300', $headers['Strict-Transport-Security']);
    }

    /** includeSubDomains is opt-in on top of the max-age. */
    public function testHstsIncludesSubdomainsWhenConfigured(): void
    {
        $headers = SecurityHeaders::build($this->configWith([
            'hsts_max_age'            => 300,
            'session_secure'          => true,
            'hsts_include_subdomains' => true,
        ]));

        $this->assertSame('max-age=300; includeSubDomains', $headers['Strict-Transport-Security']);
    }

    /** A zero or negative max-age disables it. */
    public function testHstsIsOffForNonPositiveMaxAge(): void
    {
        foreach ([0, -1] as $maxAge) {
            $headers = SecurityHeaders::build($this->configWith([
                'hsts_max_age'   => $maxAge,
                'session_secure' => true,
            ]));

            $this->assertArrayNotHasKey('Strict-Transport-Security', $headers, 'max-age ' . $maxAge);
        }
    }
}

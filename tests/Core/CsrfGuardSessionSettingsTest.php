<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\Config;
use Phorum\Core\CsrfGuard;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Covers the session settings CsrfGuard applies at session_start() time.
 *
 * These can only be observed before a session exists — session ini settings
 * and cookie params are fixed once one is active — and the rest of the suite
 * leaves a session running, so each test here needs its own process.
 */
class CsrfGuardSessionSettingsTest extends TestCase
{
    /** Build a Config whose session_secure is $secure. */
    private function configWith(bool $secure): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $key === 'session_secure' ? $secure : $default
        );
        return $config;
    }

    /**
     * Strict mode is what stops PHP adopting a session id the client made up.
     * Without it an attacker can choose the id, and so knows the CSRF token
     * stored under it before the victim has even logged in.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionUsesStrictMode(): void
    {
        CsrfGuard::initialize($this->configWith(false));
        CsrfGuard::ensureSession();

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertSame('1', ini_get('session.use_strict_mode'));
    }

    /** With session_secure on, the session cookie is marked Secure. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionCookieIsSecureWhenConfigured(): void
    {
        CsrfGuard::initialize($this->configWith(true));
        CsrfGuard::ensureSession();

        $params = session_get_cookie_params();
        $this->assertTrue($params['secure']);
        $this->assertTrue($params['httponly']);
        $this->assertSame('Lax', $params['samesite']);
    }

    /** Plain-HTTP dev installs leave Secure off, but keep the other flags. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionCookieIsNotSecureWhenNotConfigured(): void
    {
        CsrfGuard::initialize($this->configWith(false));
        CsrfGuard::ensureSession();

        $params = session_get_cookie_params();
        $this->assertFalse($params['secure']);
        $this->assertTrue($params['httponly']);
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\AdminAuth;
use Phorum\Core\Config;
use Phorum\Model\User;
use PHPUnit\Framework\TestCase;

class AdminAuthTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('get')->willReturnCallback(function (string $key, mixed $default = null) {
            return match ($key) {
                'admin_secret'   => 'testsecretvalue',
                'session_secure' => false,
                default          => $default,
            };
        });

        // Clear static admin state before each test
        AdminAuth::logout($this->config);
    }

    protected function tearDown(): void
    {
        AdminAuth::logout($this->config);
    }

    // -------------------------------------------------------------------------
    // user()
    // -------------------------------------------------------------------------

    public function testUserReturnsNullBeforeLogin(): void
    {
        $this->assertNull(AdminAuth::user());
    }

    // -------------------------------------------------------------------------
    // login() / user()
    // -------------------------------------------------------------------------

    public function testLoginSetsCurrentAdmin(): void
    {
        $user       = new User();
        $user->user_id = 1;
        $user->admin   = 1;
        $user->active  = 1;

        AdminAuth::login($user, $this->config);
        $this->assertSame($user, AdminAuth::user());
    }

    // -------------------------------------------------------------------------
    // logout()
    // -------------------------------------------------------------------------

    public function testLogoutClearsAdmin(): void
    {
        $user       = new User();
        $user->user_id = 2;
        AdminAuth::login($user, $this->config);

        AdminAuth::logout($this->config);
        $this->assertNull(AdminAuth::user());
    }

    // -------------------------------------------------------------------------
    // initialize() — invalid/empty cookie
    // -------------------------------------------------------------------------

    public function testInitializeDoesNothingForEmptyCookie(): void
    {
        unset($_COOKIE['phorum_admin_session']);
        AdminAuth::initialize($this->config);
        $this->assertNull(AdminAuth::user());
    }

    public function testInitializeDoesNothingForNonBase64Cookie(): void
    {
        $_COOKIE['phorum_admin_session'] = '!!!not-base64===';
        AdminAuth::initialize($this->config);
        $this->assertNull(AdminAuth::user());
        unset($_COOKIE['phorum_admin_session']);
    }

    public function testInitializeDoesNothingForMalformedCookie(): void
    {
        // Valid base64 but not the expected userId:timestamp:hmac format
        $_COOKIE['phorum_admin_session'] = base64_encode('onlytwoparts:here');
        AdminAuth::initialize($this->config);
        $this->assertNull(AdminAuth::user());
        unset($_COOKIE['phorum_admin_session']);
    }

    public function testInitializeIgnoresExpiredCookie(): void
    {
        // Build a cookie that is older than 1800 seconds
        $userId    = 1;
        $timestamp = time() - 3600; // 1 hour ago
        $secret    = 'testsecretvalue';
        $hmac      = hash_hmac('sha256', "{$userId}:{$timestamp}", $secret);
        $cookie    = base64_encode("{$userId}:{$timestamp}:{$hmac}");

        $_COOKIE['phorum_admin_session'] = $cookie;
        AdminAuth::initialize($this->config);
        $this->assertNull(AdminAuth::user());
        unset($_COOKIE['phorum_admin_session']);
    }

    // -------------------------------------------------------------------------
    // makeHmac throws when secret is empty
    // -------------------------------------------------------------------------

    public function testLoginThrowsWhenSecretNotConfigured(): void
    {
        $emptyConfig = $this->createMock(Config::class);
        $emptyConfig->method('get')->willReturnCallback(function (string $key, mixed $default = null) {
            return match ($key) {
                'admin_secret'   => '', // empty secret
                'session_secure' => false,
                default          => $default,
            };
        });

        $user       = new User();
        $user->user_id = 5;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('admin_secret');
        AdminAuth::login($user, $emptyConfig);
    }
}

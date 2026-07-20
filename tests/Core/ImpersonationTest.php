<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\AdminAuth;
use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\Impersonation;
use Phorum\Mapper\UserMapper;
use Phorum\Model\User;
use PHPUnit\Framework\TestCase;

class ImpersonationTest extends TestCase
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

        AdminAuth::logout($this->config);
        Impersonation::stop($this->config);
        unset($_COOKIE['phorum_impersonate']);
    }

    protected function tearDown(): void
    {
        AdminAuth::logout($this->config);
        Impersonation::stop($this->config);
        unset($_COOKIE['phorum_impersonate']);
    }

    private function makeUser(int $id, bool $admin = false, bool $active = true, ?int $activeState = null): User
    {
        $user           = new User();
        $user->user_id  = $id;
        $user->username = "user{$id}";
        $user->admin    = $admin ? 1 : 0;
        $user->active   = $activeState ?? ($active ? 1 : 0);
        return $user;
    }

    // -------------------------------------------------------------------------
    // start()
    // -------------------------------------------------------------------------

    public function testStartSetsAuthUserAndAdmin(): void
    {
        $admin  = $this->makeUser(1, admin: true);
        $target = $this->makeUser(2);

        $this->assertTrue(Impersonation::start($admin, $target, $this->config));
        $this->assertTrue(Impersonation::isActive());
        $this->assertSame($admin, Impersonation::admin());
        $this->assertSame($target, Auth::user());
    }

    public function testStartRejectsAdminTarget(): void
    {
        $admin  = $this->makeUser(1, admin: true);
        $target = $this->makeUser(2, admin: true);

        $this->assertFalse(Impersonation::start($admin, $target, $this->config));
        $this->assertFalse(Impersonation::isActive());
    }

    public function testStartRejectsSelfTarget(): void
    {
        $admin = $this->makeUser(1, admin: true);

        $this->assertFalse(Impersonation::start($admin, $admin, $this->config));
        $this->assertFalse(Impersonation::isActive());
    }

    public function testStartRejectsInactiveTarget(): void
    {
        $admin  = $this->makeUser(1, admin: true);
        $target = $this->makeUser(2, active: false);

        $this->assertFalse(Impersonation::start($admin, $target, $this->config));
        $this->assertFalse(Impersonation::isActive());
    }

    /**
     * Regression test: PHP treats any non-zero int (including negative
     * pending states) as truthy, so a naive `!$target->active` check would
     * incorrectly allow impersonating a pending (unapproved) account.
     */
    public function testStartRejectsPendingTarget(): void
    {
        $admin  = $this->makeUser(1, admin: true);
        $target = $this->makeUser(2, activeState: UserMapper::PENDING_MOD);

        $this->assertFalse(Impersonation::start($admin, $target, $this->config));
        $this->assertFalse(Impersonation::isActive());
    }

    // -------------------------------------------------------------------------
    // stop()
    // -------------------------------------------------------------------------

    public function testStopClearsImpersonationAndAuthUser(): void
    {
        $admin  = $this->makeUser(1, admin: true);
        $target = $this->makeUser(2);
        Impersonation::start($admin, $target, $this->config);

        Impersonation::stop($this->config);

        $this->assertFalse(Impersonation::isActive());
        $this->assertNull(Impersonation::admin());
        // No real Auth cookies were set, so the real front-end identity is null
        $this->assertNull(Auth::user());
    }

    // -------------------------------------------------------------------------
    // initialize() — invalid/missing cookie
    // -------------------------------------------------------------------------

    public function testInitializeDoesNothingForEmptyCookie(): void
    {
        Impersonation::initialize($this->config);
        $this->assertFalse(Impersonation::isActive());
    }

    public function testInitializeDoesNothingForNonBase64Cookie(): void
    {
        $_COOKIE['phorum_impersonate'] = '!!!not-base64===';
        Impersonation::initialize($this->config);
        $this->assertFalse(Impersonation::isActive());
    }

    public function testInitializeDoesNothingForMalformedCookie(): void
    {
        $_COOKIE['phorum_impersonate'] = base64_encode('only:three:parts');
        Impersonation::initialize($this->config);
        $this->assertFalse(Impersonation::isActive());
    }

    public function testInitializeIgnoresExpiredCookie(): void
    {
        $adminId   = 1;
        $targetId  = 2;
        $timestamp = time() - 3600;
        $hmac      = hash_hmac('sha256', "{$adminId}:{$targetId}:{$timestamp}", 'testsecretvalue');
        $_COOKIE['phorum_impersonate'] = base64_encode("{$adminId}:{$targetId}:{$timestamp}:{$hmac}");

        Impersonation::initialize($this->config);
        $this->assertFalse(Impersonation::isActive());
    }

    public function testInitializeRejectsTamperedHmac(): void
    {
        $adminId   = 1;
        $targetId  = 2;
        $timestamp = time();
        AdminAuth::login($this->makeUser($adminId, admin: true), $this->config);
        $_COOKIE['phorum_impersonate'] = base64_encode("{$adminId}:{$targetId}:{$timestamp}:deadbeef");

        Impersonation::initialize($this->config);
        $this->assertFalse(Impersonation::isActive());
    }

    public function testInitializeRejectsWhenAdminSessionMismatched(): void
    {
        $adminId   = 1;
        $targetId  = 2;
        $timestamp = time();
        $hmac      = hash_hmac('sha256', "{$adminId}:{$targetId}:{$timestamp}", 'testsecretvalue');
        $_COOKIE['phorum_impersonate'] = base64_encode("{$adminId}:{$targetId}:{$timestamp}:{$hmac}");

        // No AdminAuth session active at all
        Impersonation::initialize($this->config);
        $this->assertFalse(Impersonation::isActive());

        // A different admin is currently authenticated
        AdminAuth::login($this->makeUser(99, admin: true), $this->config);
        Impersonation::initialize($this->config);
        $this->assertFalse(Impersonation::isActive());
    }

    public function testInitializeRestoresImpersonationFromValidCookie(): void
    {
        $adminId   = 1;
        $targetId  = 2;
        $timestamp = time();
        $hmac      = hash_hmac('sha256', "{$adminId}:{$targetId}:{$timestamp}", 'testsecretvalue');
        $_COOKIE['phorum_impersonate'] = base64_encode("{$adminId}:{$targetId}:{$timestamp}:{$hmac}");

        AdminAuth::login($this->makeUser($adminId, admin: true), $this->config);

        $target = $this->makeUser($targetId);
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('load')->with($targetId)->willReturn($target);

        Impersonation::initialize($this->config, $mapper);

        $this->assertTrue(Impersonation::isActive());
        $this->assertSame($adminId, Impersonation::admin()?->user_id);
        $this->assertSame($target, Auth::user());
    }

    public function testInitializeRejectsTargetThatIsNowAdmin(): void
    {
        $adminId   = 1;
        $targetId  = 2;
        $timestamp = time();
        $hmac      = hash_hmac('sha256', "{$adminId}:{$targetId}:{$timestamp}", 'testsecretvalue');
        $_COOKIE['phorum_impersonate'] = base64_encode("{$adminId}:{$targetId}:{$timestamp}:{$hmac}");

        AdminAuth::login($this->makeUser($adminId, admin: true), $this->config);

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('load')->with($targetId)->willReturn($this->makeUser($targetId, admin: true));

        Impersonation::initialize($this->config, $mapper);

        $this->assertFalse(Impersonation::isActive());
    }

    /** Regression test — see testStartRejectsPendingTarget for why. */
    public function testInitializeRejectsTargetThatIsNowPending(): void
    {
        $adminId   = 1;
        $targetId  = 2;
        $timestamp = time();
        $hmac      = hash_hmac('sha256', "{$adminId}:{$targetId}:{$timestamp}", 'testsecretvalue');
        $_COOKIE['phorum_impersonate'] = base64_encode("{$adminId}:{$targetId}:{$timestamp}:{$hmac}");

        AdminAuth::login($this->makeUser($adminId, admin: true), $this->config);

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('load')->with($targetId)
            ->willReturn($this->makeUser($targetId, activeState: UserMapper::PENDING_MOD));

        Impersonation::initialize($this->config, $mapper);

        $this->assertFalse(Impersonation::isActive());
    }

    // -------------------------------------------------------------------------
    // makeHmac throws when secret is empty
    // -------------------------------------------------------------------------

    public function testStartThrowsWhenSecretNotConfigured(): void
    {
        $emptyConfig = $this->createMock(Config::class);
        $emptyConfig->method('get')->willReturnCallback(function (string $key, mixed $default = null) {
            return match ($key) {
                'admin_secret'   => '',
                'session_secure' => false,
                default          => $default,
            };
        });

        $admin  = $this->makeUser(1, admin: true);
        $target = $this->makeUser(2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('admin_secret');
        Impersonation::start($admin, $target, $emptyConfig);
    }
}

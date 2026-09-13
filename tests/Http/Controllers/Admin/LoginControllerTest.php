<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Core\AdminAuth;
use Phorum\Http\Controllers\Admin\LoginController;
use Phorum\Http\Request;
use Phorum\Mapper\UserMapper;
use Phorum\Service\LoginThrottleService;
use Phorum\Tests\Http\ControllerTestCase;

class LoginControllerTest extends ControllerTestCase
{
    /**
     * Build an admin LoginController with mocked collaborators. The throttle
     * defaults to "not throttled"; pass `retryAfter` to simulate one.
     *
     * @param array $deps Optional overrides: users, throttle, retryAfter.
     */
    private function makeController(array $deps = []): LoginController
    {
        $throttle = $deps['throttle'] ?? $this->createMock(LoginThrottleService::class);
        if (!isset($deps['throttle'])) {
            $throttle->method('loginRetryAfter')->willReturn($deps['retryAfter'] ?? 0);
        }

        return new LoginController(
            config:   $this->makeConfig(),
            twig:     $this->makeTwig(),
            users:    $deps['users'] ?? $this->createMock(UserMapper::class),
            throttle: $throttle,
        );
    }

    // -------------------------------------------------------------------------
    // login
    // -------------------------------------------------------------------------

    public function testLoginRedirectsIfAlreadyAdmin(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl     = $this->makeController();
        $response = $ctrl->login(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin', $response->headers['Location']);
    }

    public function testLoginReturnsFormOnGet(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->login($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testLoginPostReturns200WhenUserNotFound(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn(null);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->login($this->makePostRequest([
            'username' => 'nobody',
            'password' => 'secret',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testLoginPostReturns200WhenPasswordWrong(): void
    {
        $adminUser = $this->makeUser(1, true);

        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn($adminUser);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->login($this->makePostRequest([
            'username' => 'user1',
            'password' => 'wrongpassword',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testLoginPostReturns200ForNonAdminUser(): void
    {
        $nonAdmin = $this->makeUser(2, false);

        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn($nonAdmin);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->login($this->makePostRequest([
            'username' => 'user2',
            'password' => 'secret',
        ]));
        $this->assertSame(200, $response->status);
    }

    /**
     * Regression test: PHP treats any non-zero int (including negative
     * pending states) as truthy, so a naive `$user->active` check would
     * incorrectly let a pending admin account log in.
     */
    public function testLoginPostReturns200ForPendingAdminUser(): void
    {
        $adminUser           = $this->makeUser(1, true);
        $adminUser->password = password_hash('secret', PASSWORD_BCRYPT);
        $adminUser->active   = UserMapper::PENDING_MOD;

        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn($adminUser);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->login($this->makePostRequest([
            'username' => 'user1',
            'password' => 'secret',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testLoginPostSuccessRedirectsToDashboard(): void
    {
        $adminUser           = $this->makeUser(1, true);
        $adminUser->password = password_hash('secret', PASSWORD_BCRYPT);

        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn($adminUser);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->login($this->makePostRequest([
            'username' => 'user1',
            'password' => 'secret',
        ]));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin', $response->headers['Location']);
    }

    public function testLoginPostReturns403WithBadCsrf(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->login(new Request(
            post:   ['csrf_token' => 'invalid', 'username' => 'u', 'password' => 'p'],
            server: ['REQUEST_METHOD' => 'POST'],
        ));
        $this->assertSame(403, $response->status);
    }

    // -------------------------------------------------------------------------
    // logout
    // -------------------------------------------------------------------------

    public function testLogoutGetRedirectsToAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->logout($this->makeGetRequest());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin', $response->headers['Location']);
    }

    public function testLogoutPostClearsAdminAndRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl     = $this->makeController();
        $response = $ctrl->logout($this->makePostRequest());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
        $this->assertNull(AdminAuth::user());
    }

    public function testLogoutPostReturns403WithBadCsrf(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->logout(new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
        ));
        $this->assertSame(403, $response->status);
    }

    /**
     * With an unusable admin_secret, admin login is refused with an
     * explanation rather than attempting auth (which would throw on signing
     * and surface as an unexplained 500).
     */
    public function testLoginIsRefusedWhenAdminSecretIsPlaceholder(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->expects($this->never())->method('findByUsername');

        $throttle = $this->createMock(LoginThrottleService::class);
        $throttle->method('loginRetryAfter')->willReturn(0);

        $ctrl = new LoginController(
            config:   $this->makeConfig(['admin_secret' => 'change-me-to-a-long-random-string']),
            twig:     $this->makeTwig(),
            users:    $users,
            throttle: $throttle,
        );

        $response = $ctrl->login($this->makePostRequest(['username' => 'admin', 'password' => 'secret']));

        $this->assertSame(503, $response->status);
    }

    /**
     * The admin form checks passwords independently of the front-end login,
     * so it needs the same limit — throttling one and not the other leaves a
     * second unlimited oracle for the same accounts.
     */
    public function testThrottledAdminLoginDoesNotLookUpTheUser(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->expects($this->never())->method('findByUsername');

        $ctrl = $this->makeController(['users' => $users, 'retryAfter' => 60]);

        $response = $ctrl->login($this->makePostRequest(['username' => 'admin', 'password' => 'secret']));

        $this->assertSame(429, $response->status);
    }

    /** A failed admin login is recorded against the same buckets. */
    public function testFailedAdminLoginIsRecorded(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn(null);

        $throttle = $this->createMock(LoginThrottleService::class);
        $throttle->method('loginRetryAfter')->willReturn(0);
        $throttle->expects($this->once())->method('recordFailedLogin');

        $ctrl = $this->makeController(['users' => $users, 'throttle' => $throttle]);
        $ctrl->login($this->makePostRequest(['username' => 'admin', 'password' => 'wrong']));
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Core\AdminAuth;
use Phorum\Http\Controllers\Admin\LoginController;
use Phorum\Http\Request;
use Phorum\Mapper\UserMapper;
use Phorum\Tests\Http\ControllerTestCase;

class LoginControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): LoginController
    {
        return new LoginController(
            config: $this->makeConfig(),
            twig:   $this->makeTwig(),
            users:  $deps['users'] ?? $this->createMock(UserMapper::class),
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
}

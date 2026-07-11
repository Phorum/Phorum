<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\AuthController;
use Phorum\Http\Request;
use Phorum\Mapper\BanMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\AuthService;
use Phorum\Service\BanService;
use Phorum\Tests\Http\ControllerTestCase;

class AuthControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): AuthController
    {
        return new AuthController(
            config:      $this->makeConfig(),
            twig:        $this->makeTwig(),
            authService: $deps['authService'] ?? $this->createMock(AuthService::class),
            banService:  $deps['banService']  ?? $this->createMock(BanService::class),
            users:       $deps['users']       ?? $this->createMock(UserMapper::class),
        );
    }

    // -------------------------------------------------------------------------
    // login
    // -------------------------------------------------------------------------

    public function testLoginRedirectsIfAlreadyLoggedIn(): void
    {
        Auth::setUser($this->makeUser());
        $ctrl     = $this->makeController();
        $response = $ctrl->login(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }

    public function testLoginReturnsFormOnGet(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->login($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testLoginPostReturns200ForEmptyCredentials(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->login($this->makePostRequest(['username' => '', 'password' => '']));
        $this->assertSame(200, $response->status);
    }

    public function testLoginPostReturns200ForInvalidCredentials(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->method('login')->willReturn(null);

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->login($this->makePostRequest(['username' => 'bob', 'password' => 'wrong']));
        $this->assertSame(200, $response->status);
    }

    public function testLoginPostSuccessRedirects(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->method('login')->willReturn($this->makeUser());

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->login($this->makePostRequest(['username' => 'user1', 'password' => 'secret']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }

    public function testLoginPostRedirectsToRequestedPath(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->method('login')->willReturn($this->makeUser());

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->login($this->makePostRequest([
            'username' => 'user1',
            'password' => 'secret',
            'redirect' => '/forum/5',
        ]));
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/5', $response->headers['Location']);
    }

    public function testLoginPostBlocksExternalRedirect(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->method('login')->willReturn($this->makeUser());

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->login($this->makePostRequest([
            'username' => 'user1',
            'password' => 'secret',
            'redirect' => '//evil.example.com/steal',
        ]));
        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // logout
    // -------------------------------------------------------------------------

    public function testLogoutRedirectsToHome(): void
    {
        $authService = $this->createMock(AuthService::class);
        $user        = $this->makeUser();
        Auth::setUser($user);
        $authService->expects($this->once())->method('logout')->with($user);

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->logout(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }

    public function testLogoutWorksWhenNotLoggedIn(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->expects($this->never())->method('logout');

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->logout(new Request());
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // register
    // -------------------------------------------------------------------------

    public function testRegisterRedirectsIfLoggedIn(): void
    {
        Auth::setUser($this->makeUser());
        $ctrl     = $this->makeController();
        $response = $ctrl->register(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }

    public function testRegisterReturnsFormOnGet(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->register($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testRegisterValidationErrorForEmptyUsername(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn(null);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->register($this->makePostRequest([
            'username'  => '',
            'email'     => 'a@b.com',
            'password'  => 'secret1',
            'password2' => 'secret1',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testRegisterValidationErrorForShortPassword(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn(null);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->register($this->makePostRequest([
            'username'  => 'newuser',
            'email'     => 'a@b.com',
            'password'  => 'abc',
            'password2' => 'abc',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testRegisterValidationErrorForPasswordMismatch(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn(null);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->register($this->makePostRequest([
            'username'  => 'newuser',
            'email'     => 'a@b.com',
            'password'  => 'secret1',
            'password2' => 'secret2',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testRegisterSuccessRedirects(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn(null);

        $ban = $this->createMock(BanService::class);
        $ban->method('checkIp')->willReturn(false);
        $ban->method('checkEmail')->willReturn(false);
        $ban->method('checkUsername')->willReturn(false);

        $authService = $this->createMock(AuthService::class);
        $authService->method('login')->willReturn($this->makeUser());

        $ctrl     = $this->makeController([
            'users'       => $users,
            'banService'  => $ban,
            'authService' => $authService,
        ]);
        $response = $ctrl->register($this->makePostRequest([
            'username'  => 'newuser',
            'email'     => 'new@example.com',
            'password'  => 'secret1',
            'password2' => 'secret1',
        ]));
        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }

    public function testRegisterBannedReturns200WithError(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn(null);

        $ban = $this->createMock(BanService::class);
        $ban->method('checkIp')->willReturn(true);
        $ban->method('checkEmail')->willReturn(false);
        $ban->method('checkUsername')->willReturn(false);

        $ctrl     = $this->makeController(['users' => $users, 'banService' => $ban]);
        $response = $ctrl->register($this->makePostRequest([
            'username'  => 'newuser',
            'email'     => 'banned@example.com',
            'password'  => 'secret1',
            'password2' => 'secret1',
        ]));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // confirmEmail
    // -------------------------------------------------------------------------

    public function testConfirmEmailRedirectsOnSuccess(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->method('confirmEmail')->willReturn($this->makeUser());

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->confirmEmail(new Request(query: ['token' => 'validtoken']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }

    public function testConfirmEmailReturns200OnInvalidToken(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->method('confirmEmail')->willReturn(null);

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->confirmEmail(new Request(query: ['token' => 'badtoken']));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // forgotPassword
    // -------------------------------------------------------------------------

    public function testForgotPasswordRedirectsIfLoggedIn(): void
    {
        Auth::setUser($this->makeUser());
        $ctrl     = $this->makeController();
        $response = $ctrl->forgotPassword(new Request());
        $this->assertSame(302, $response->status);
    }

    public function testForgotPasswordReturnsFormOnGet(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->forgotPassword($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testForgotPasswordPostInvalidEmail(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->forgotPassword($this->makePostRequest(['email' => 'not-an-email']));
        $this->assertSame(200, $response->status);
    }

    public function testForgotPasswordPostValidEmail(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->expects($this->once())->method('requestPasswordReset');

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->forgotPassword($this->makePostRequest(['email' => 'user@example.com']));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // CSRF guard
    // -------------------------------------------------------------------------

    public function testLoginPostReturns403WithBadCsrf(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->login(new Request(
            post:   ['csrf_token' => 'invalid', 'username' => 'u', 'password' => 'p'],
            server: ['REQUEST_METHOD' => 'POST'],
        ));
        $this->assertSame(403, $response->status);
    }
}

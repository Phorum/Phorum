<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\AuthController;
use Phorum\Http\Request;
use Phorum\Mapper\BanMapper;
use Phorum\Mapper\SettingMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\AuthService;
use Phorum\Service\BanService;
use Phorum\Service\LoginThrottleService;
use Phorum\Tests\Http\ControllerTestCase;

class AuthControllerTest extends ControllerTestCase
{
    /**
     * Build an AuthController with mocked collaborators. The throttle defaults
     * to "not throttled" and must be mocked — the real one reaches for the
     * login_attempts table.
     *
     * @param array $deps Optional overrides, plus `retryAfter` to simulate a
     *                    throttled caller.
     */
    private function makeController(array $deps = []): AuthController
    {
        $throttle = $deps['throttle'] ?? $this->createMock(LoginThrottleService::class);
        if (!isset($deps['throttle'])) {
            $throttle->method('loginRetryAfter')->willReturn($deps['retryAfter'] ?? 0);
            $throttle->method('resetRetryAfter')->willReturn($deps['retryAfter'] ?? 0);
        }

        return new AuthController(
            config:      $this->makeConfig(),
            twig:        $this->makeTwig(),
            authService: $deps['authService'] ?? $this->createMock(AuthService::class),
            banService:  $deps['banService']  ?? $this->createMock(BanService::class),
            users:       $deps['users']       ?? $this->createMock(UserMapper::class),
            settings:    $deps['settings']    ?? $this->createMock(SettingMapper::class),
            throttle:    $throttle,
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

    public function testLoginPostRedirectsToChangePasswordWhenForced(): void
    {
        $user = $this->makeUser();
        $user->force_password_change = 1;

        $authService = $this->createMock(AuthService::class);
        $authService->method('login')->willReturn($user);

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->login($this->makePostRequest([
            'username' => 'user1',
            'password' => 'secret',
            'redirect' => '/forum/5',
        ]));
        $this->assertSame(302, $response->status);
        $this->assertSame('/user/change-password?redirect=%2Fforum%2F5', $response->headers['Location']);
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
        $response = $ctrl->logout($this->makePostRequest());
        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }

    public function testLogoutWorksWhenNotLoggedIn(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->expects($this->never())->method('logout');

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->logout($this->makePostRequest());
        $this->assertSame(302, $response->status);
    }

    /**
     * A GET must not log anyone out. Any site could otherwise force it with a
     * top-level navigation — window.location, a meta refresh, a 302 — which
     * also destroys the year-long remember-me cookie. (SameSite=Lax already
     * stopped the silent <img src="/logout"> variant, but not a navigation.)
     */
    public function testLogoutOnGetShowsConfirmationInsteadOfLoggingOut(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->expects($this->never())->method('logout');
        Auth::setUser($this->makeUser());

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->logout($this->makeGetRequest());

        $this->assertSame(200, $response->status);
    }

    /** A POST without a valid CSRF token is refused. */
    public function testLogoutPostWithoutCsrfIsRefused(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->expects($this->never())->method('logout');
        Auth::setUser($this->makeUser());

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->logout(new Request(
            post:   ['csrf_token' => 'wrong'],
            server: ['REQUEST_METHOD' => 'POST'],
        ));

        $this->assertSame(403, $response->status);
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

    public function testRegisterShowsPendingApprovalPageWhenModApprovalRequired(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn(null);

        $ban = $this->createMock(BanService::class);
        $ban->method('checkIp')->willReturn(false);
        $ban->method('checkEmail')->willReturn(false);
        $ban->method('checkUsername')->willReturn(false);

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturnMap([
            ['require_mod_approval', true],
        ]);

        $authService = $this->createMock(AuthService::class);
        $authService->expects($this->never())->method('login');

        $ctrl     = $this->makeController([
            'users'       => $users,
            'banService'  => $ban,
            'settings'    => $settings,
            'authService' => $authService,
        ]);
        $response = $ctrl->register($this->makePostRequest([
            'username'  => 'newuser',
            'email'     => 'new@example.com',
            'password'  => 'secret1',
            'password2' => 'secret1',
        ]));
        $this->assertSame(200, $response->status);
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

    /**
     * Confirming activates the account and sends the visitor to the login
     * form — it no longer logs them in, since the link is long-lived and
     * ends up in access logs.
     */
    public function testConfirmEmailRedirectsToLoginOnSuccess(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->method('confirmEmail')->willReturn($this->makeUser());

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->confirmEmail(new Request(query: ['token' => 'validtoken']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/login?confirmed=1', $response->headers['Location']);
    }

    public function testConfirmEmailShowsPendingApprovalPageWhenStillPendingModApproval(): void
    {
        $user         = $this->makeUser();
        $user->active = UserMapper::PENDING_MOD;

        $authService = $this->createMock(AuthService::class);
        $authService->method('confirmEmail')->willReturn($user);

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->confirmEmail(new Request(query: ['token' => 'validtoken']));
        $this->assertSame(200, $response->status);
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

    // -------------------------------------------------------------------------
    // Rate limiting
    // -------------------------------------------------------------------------

    /**
     * A throttled caller must not reach password verification at all —
     * otherwise the limit would only change the message, not the work done.
     */
    public function testThrottledLoginDoesNotAttemptAuthentication(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->expects($this->never())->method('login');

        $ctrl = $this->makeController(['authService' => $authService, 'retryAfter' => 42]);

        $response = $ctrl->login($this->makePostRequest(['username' => 'alice', 'password' => 'secret']));

        $this->assertSame(200, $response->status);
    }

    /** A failed login is recorded against the throttle. */
    public function testFailedLoginIsRecorded(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->method('login')->willReturn(null);

        $throttle = $this->createMock(LoginThrottleService::class);
        $throttle->method('loginRetryAfter')->willReturn(0);
        $throttle->expects($this->once())->method('recordFailedLogin');

        $ctrl = $this->makeController(['authService' => $authService, 'throttle' => $throttle]);
        $ctrl->login($this->makePostRequest(['username' => 'alice', 'password' => 'wrong']));
    }

    /** A successful login clears that account's bucket. */
    public function testSuccessfulLoginClearsTheAccountBucket(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->method('login')->willReturn($this->makeUser());

        $throttle = $this->createMock(LoginThrottleService::class);
        $throttle->method('loginRetryAfter')->willReturn(0);
        $throttle->expects($this->once())->method('clearAccount')->with('alice');
        $throttle->expects($this->never())->method('recordFailedLogin');

        $ctrl = $this->makeController(['authService' => $authService, 'throttle' => $throttle]);
        $ctrl->login($this->makePostRequest(['username' => 'alice', 'password' => 'secret']));
    }

    /**
     * The reset endpoint sends mail to an address the caller chooses, so a
     * throttled caller must not trigger a send.
     */
    public function testThrottledForgotPasswordDoesNotSendMail(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->expects($this->never())->method('requestPasswordReset');

        $ctrl = $this->makeController(['authService' => $authService, 'retryAfter' => 30]);

        $response = $ctrl->forgotPassword($this->makePostRequest(['email' => 'victim@example.com']));

        $this->assertSame(200, $response->status);
    }

    /** Resend-confirmation is the same shape and shares the limit. */
    public function testThrottledResendConfirmationDoesNotSendMail(): void
    {
        $authService = $this->createMock(AuthService::class);
        $authService->expects($this->never())->method('resendConfirmation');

        $ctrl = $this->makeController(['authService' => $authService, 'retryAfter' => 30]);

        $response = $ctrl->resendConfirmation($this->makePostRequest(['email' => 'victim@example.com']));

        $this->assertSame(200, $response->status);
    }

    /** An accepted reset request is recorded so repeats count toward the limit. */
    public function testForgotPasswordRecordsTheRequest(): void
    {
        $throttle = $this->createMock(LoginThrottleService::class);
        $throttle->method('resetRetryAfter')->willReturn(0);
        $throttle->expects($this->once())->method('recordResetRequest');

        $ctrl = $this->makeController(['throttle' => $throttle]);
        $ctrl->forgotPassword($this->makePostRequest(['email' => 'someone@example.com']));
    }

    // -------------------------------------------------------------------------
    // Reset token is taken out of the URL
    // -------------------------------------------------------------------------

    /**
     * A token arriving in the query string is stashed in the session and the
     * visitor is bounced to a clean URL, so it stops being a working URL in
     * browser history and the form POST doesn't carry it into the access log
     * a second time.
     */
    public function testResetPasswordMovesUrlTokenIntoSessionAndRedirects(): void
    {
        unset($_SESSION['phorum_reset_token']);

        $ctrl     = $this->makeController();
        $response = $ctrl->resetPassword(new Request(query: ['token' => 'a-real-token']));

        $this->assertSame(302, $response->status);
        $this->assertSame('/reset-password', $response->headers['Location']);
        $this->assertSame('a-real-token', $_SESSION['phorum_reset_token'] ?? null);
    }

    /** The clean URL then validates using the stashed token. */
    public function testResetPasswordValidatesTheStashedToken(): void
    {
        $_SESSION['phorum_reset_token'] = 'a-real-token';

        $authService = $this->createMock(AuthService::class);
        $authService->expects($this->once())->method('validateResetToken')
            ->with('a-real-token')->willReturn($this->makeUser());

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->resetPassword($this->makeGetRequest());

        $this->assertSame(200, $response->status);
    }

    /** Landing on the clean URL with nothing stashed shows the invalid page. */
    public function testResetPasswordWithNoStashedTokenIsInvalid(): void
    {
        unset($_SESSION['phorum_reset_token']);

        $authService = $this->createMock(AuthService::class);
        $authService->expects($this->never())->method('validateResetToken');

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->resetPassword($this->makeGetRequest());

        $this->assertSame(200, $response->status);
    }

    /** A completed reset clears the stashed token so it can't be replayed. */
    public function testSuccessfulResetClearsTheStashedToken(): void
    {
        $_SESSION['phorum_reset_token'] = 'a-real-token';

        $authService = $this->createMock(AuthService::class);
        $authService->method('validateResetToken')->willReturn($this->makeUser());
        $authService->expects($this->once())->method('resetPassword');

        $ctrl     = $this->makeController(['authService' => $authService]);
        $response = $ctrl->resetPassword($this->makePostRequest([
            'password'  => 'newsecret',
            'password2' => 'newsecret',
        ]));

        $this->assertSame(302, $response->status);
        $this->assertArrayNotHasKey('phorum_reset_token', $_SESSION);
    }

    /** An invalid stashed token is discarded rather than retried forever. */
    public function testInvalidStashedTokenIsDiscarded(): void
    {
        $_SESSION['phorum_reset_token'] = 'expired-token';

        $authService = $this->createMock(AuthService::class);
        $authService->method('validateResetToken')->willReturn(null);

        $ctrl = $this->makeController(['authService' => $authService]);
        $ctrl->resetPassword($this->makeGetRequest());

        $this->assertArrayNotHasKey('phorum_reset_token', $_SESSION);
    }
}

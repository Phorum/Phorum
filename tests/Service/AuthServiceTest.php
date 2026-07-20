<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Core\Auth;
use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\UserMapper;
use Phorum\Model\User;
use Phorum\Service\AuthService;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        HookDispatcher::reset();
        require_once dirname(__DIR__, 2) . '/src/Hook/functions.php';
        Auth::clear();
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
        Auth::clear();
    }

    private function makeUser(
        string $password   = '',
        bool   $active     = true,
        bool   $admin      = false,
        ?int   $activeState = null,
    ): User {
        $u           = new User();
        $u->user_id  = 1;
        $u->username = 'alice';
        $u->email    = 'alice@example.com';
        $u->active   = $activeState ?? ($active ? 1 : 0);
        $u->admin    = $admin  ? 1 : 0;
        $u->password = $password ?: password_hash('secret', PASSWORD_BCRYPT);
        return $u;
    }

    // -------------------------------------------------------------------------
    // login()
    // -------------------------------------------------------------------------

    public function testLoginReturnsUserOnValidCredentials(): void
    {
        $user   = $this->makeUser();
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByUsername')->willReturn($user);
        $mapper->method('save')->willReturnArgument(0);

        $result = (new AuthService($mapper))->login('alice', 'secret');
        $this->assertSame($user, $result);
    }

    public function testLoginReturnsNullForWrongPassword(): void
    {
        $user   = $this->makeUser();
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByUsername')->willReturn($user);

        $result = (new AuthService($mapper))->login('alice', 'wrong');
        $this->assertNull($result);
    }

    public function testLoginReturnsNullForInactiveUser(): void
    {
        $user   = $this->makeUser(active: false);
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByUsername')->willReturn($user);

        $result = (new AuthService($mapper))->login('alice', 'secret');
        $this->assertNull($result);
    }

    /**
     * Regression test: PHP treats any non-zero int (including negative
     * pending states) as truthy, so a naive `!$user->active` check would
     * incorrectly let a pending account log in. Must be a strict comparison.
     */
    public function testLoginReturnsNullForPendingModUser(): void
    {
        $user   = $this->makeUser(activeState: UserMapper::PENDING_MOD);
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByUsername')->willReturn($user);

        $result = (new AuthService($mapper))->login('alice', 'secret');
        $this->assertNull($result);
    }

    public function testLoginReturnsNullForUnknownUser(): void
    {
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByUsername')->willReturn(null);

        $result = (new AuthService($mapper))->login('nobody', 'secret');
        $this->assertNull($result);
    }

    public function testLoginUpgradesMd5PasswordToBcrypt(): void
    {
        $user           = $this->makeUser();
        $user->password = md5('secret'); // legacy hash

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByUsername')->willReturn($user);
        $mapper->method('save')->willReturnArgument(0);

        $result = (new AuthService($mapper))->login('alice', 'secret');

        $this->assertNotNull($result);
        $this->assertTrue(password_verify('secret', $user->password));
    }

    public function testLoginSetsAuthUser(): void
    {
        $user   = $this->makeUser();
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByUsername')->willReturn($user);
        $mapper->method('save')->willReturnArgument(0);

        (new AuthService($mapper))->login('alice', 'secret');
        $this->assertSame($user, Auth::user());
    }

    // -------------------------------------------------------------------------
    // loginUser()
    // -------------------------------------------------------------------------

    public function testLoginUserSetsAuthUser(): void
    {
        $user   = $this->makeUser();
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnArgument(0);

        (new AuthService($mapper))->loginUser($user);
        $this->assertSame($user, Auth::user());
    }

    public function testLoginUserFiresAfterLoginHook(): void
    {
        $user   = $this->makeUser();
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnArgument(0);

        $seen = null;
        HookDispatcher::getInstance()->register('after_login', function ($u) use (&$seen) {
            $seen = $u;
            return null;
        });

        (new AuthService($mapper))->loginUser($user);
        $this->assertSame($user, $seen);
    }

    public function testLoginUserSetsLongTermSessionOnlyWhenRemembered(): void
    {
        $user   = $this->makeUser();
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnArgument(0);

        (new AuthService($mapper))->loginUser($user, remember: false);
        $this->assertSame('', $user->sessid_lt);

        (new AuthService($mapper))->loginUser($user, remember: true);
        $this->assertNotSame('', $user->sessid_lt);
    }

    // -------------------------------------------------------------------------
    // register()
    // -------------------------------------------------------------------------

    public function testRegisterCreatesActiveUserWithoutConfirmation(): void
    {
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnArgument(0);

        $user = (new AuthService($mapper))->register('bob', 'bob@example.com', 'pass123');

        $this->assertSame('bob', $user->username);
        $this->assertSame(1, $user->active);
        $this->assertTrue(password_verify('pass123', $user->password));
    }

    public function testRegisterCapturesRemoteAddrAsRegIp(): void
    {
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnArgument(0);

        $previous = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';

        $user = (new AuthService($mapper))->register('bob', 'bob@example.com', 'pass123');

        if ($previous === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $previous;
        }

        $this->assertSame('203.0.113.5', $user->reg_ip);
    }

    public function testRegisterCreatesInactiveUserWithConfirmation(): void
    {
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnArgument(0);

        $user = (new AuthService($mapper))->register(
            'bob', 'bob@example.com', 'pass123', requireConfirmation: true
        );

        $this->assertSame(UserMapper::PENDING_EMAIL, $user->active);
    }

    public function testRegisterCreatesPendingModUserWithModApprovalOnly(): void
    {
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnArgument(0);

        $user = (new AuthService($mapper))->register(
            'bob', 'bob@example.com', 'pass123', requireModApproval: true
        );

        $this->assertSame(UserMapper::PENDING_MOD, $user->active);
    }

    public function testRegisterCreatesPendingBothUserWhenBothRequired(): void
    {
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnArgument(0);

        $user = (new AuthService($mapper))->register(
            'bob', 'bob@example.com', 'pass123', requireConfirmation: true, requireModApproval: true
        );

        $this->assertSame(UserMapper::PENDING_BOTH, $user->active);
    }

    // -------------------------------------------------------------------------
    // validateResetToken()
    // -------------------------------------------------------------------------

    public function testValidateResetTokenReturnsNullForEmptyToken(): void
    {
        $mapper = $this->createMock(UserMapper::class);
        $result = (new AuthService($mapper))->validateResetToken('');
        $this->assertNull($result);
    }

    public function testValidateResetTokenReturnsNullForUnknownToken(): void
    {
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturn(null);

        $result = (new AuthService($mapper))->validateResetToken('bad-token');
        $this->assertNull($result);
    }

    public function testValidateResetTokenReturnsNullForExpiredToken(): void
    {
        $user             = $this->makeUser();
        $user->email_temp = (string) (time() - 1); // already expired

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturn($user);

        $result = (new AuthService($mapper))->validateResetToken('expired-token');
        $this->assertNull($result);
    }

    public function testValidateResetTokenReturnsUserForValidToken(): void
    {
        $user             = $this->makeUser();
        $user->email_temp = (string) (time() + 3600); // not expired

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturn($user);

        $result = (new AuthService($mapper))->validateResetToken('valid-token');
        $this->assertSame($user, $result);
    }

    public function testValidateResetTokenReturnsNullForInactiveUser(): void
    {
        $user             = $this->makeUser(active: false);
        $user->email_temp = (string) (time() + 3600);

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturn($user);

        $result = (new AuthService($mapper))->validateResetToken('some-token');
        $this->assertNull($result);
    }

    /**
     * Regression test: a pending account's password_temp holds an email-
     * confirmation token, not a reset token — a naive truthy check would
     * otherwise let a PENDING_MOD/PENDING_BOTH account "reset" via that token.
     */
    public function testValidateResetTokenReturnsNullForPendingModUser(): void
    {
        $user             = $this->makeUser(activeState: UserMapper::PENDING_MOD);
        $user->email_temp = (string) (time() + 3600);

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturn($user);

        $result = (new AuthService($mapper))->validateResetToken('some-token');
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // requestPasswordReset()
    // -------------------------------------------------------------------------

    public function testRequestPasswordResetSilentlyNoOpsForPendingModUser(): void
    {
        $user   = $this->makeUser(activeState: UserMapper::PENDING_MOD);
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByEmail')->willReturn($user);
        $mapper->expects($this->never())->method('save');

        $result = (new AuthService($mapper))->requestPasswordReset('alice@example.com', 'https://example.com');
        $this->assertTrue($result);
        $this->assertSame('', $user->password_temp);
    }

    public function testRequestPasswordResetSetsTokenForActiveUser(): void
    {
        $user   = $this->makeUser(active: true);
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByEmail')->willReturn($user);
        $mapper->expects($this->once())->method('save');

        $result = (new AuthService($mapper))->requestPasswordReset('alice@example.com', 'https://example.com');
        $this->assertTrue($result);
        $this->assertNotSame('', $user->password_temp);
    }

    // -------------------------------------------------------------------------
    // resendConfirmation()
    // -------------------------------------------------------------------------

    public function testResendConfirmationSendsForPendingEmailUser(): void
    {
        $user   = $this->makeUser(activeState: UserMapper::PENDING_EMAIL);
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByEmail')->willReturn($user);
        $mapper->expects($this->once())->method('save');

        $result = (new AuthService($mapper))->resendConfirmation('alice@example.com', 'https://example.com');
        $this->assertTrue($result);
        $this->assertNotSame('', $user->password_temp);
    }

    public function testResendConfirmationSendsForPendingBothUser(): void
    {
        $user   = $this->makeUser(activeState: UserMapper::PENDING_BOTH);
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByEmail')->willReturn($user);
        $mapper->expects($this->once())->method('save');

        $result = (new AuthService($mapper))->resendConfirmation('alice@example.com', 'https://example.com');
        $this->assertTrue($result);
    }

    public function testResendConfirmationSilentlyNoOpsForActiveUser(): void
    {
        $user   = $this->makeUser(active: true);
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByEmail')->willReturn($user);
        $mapper->expects($this->never())->method('save');

        $result = (new AuthService($mapper))->resendConfirmation('alice@example.com', 'https://example.com');
        $this->assertTrue($result);
    }

    public function testResendConfirmationSilentlyNoOpsForPendingModUser(): void
    {
        $user   = $this->makeUser(activeState: UserMapper::PENDING_MOD);
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByEmail')->willReturn($user);
        $mapper->expects($this->never())->method('save');

        $result = (new AuthService($mapper))->resendConfirmation('alice@example.com', 'https://example.com');
        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // confirmEmail()
    // -------------------------------------------------------------------------

    public function testConfirmEmailReturnsNullForEmptyToken(): void
    {
        $mapper = $this->createMock(UserMapper::class);
        $result = (new AuthService($mapper))->confirmEmail('');
        $this->assertNull($result);
    }

    public function testConfirmEmailReturnsNullForAlreadyActiveUser(): void
    {
        $user           = $this->makeUser(active: true);
        $user->email_temp = (string) (time() + 1000);

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturn($user);

        $result = (new AuthService($mapper))->confirmEmail('some-token');
        $this->assertNull($result);
    }

    public function testConfirmEmailReturnsNullForExpiredToken(): void
    {
        $user           = $this->makeUser(activeState: UserMapper::PENDING_EMAIL);
        $user->email_temp = (string) (time() - 10);

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturn($user);

        $result = (new AuthService($mapper))->confirmEmail('some-token');
        $this->assertNull($result);
    }

    public function testConfirmEmailActivatesUserOnValidToken(): void
    {
        $user             = $this->makeUser(activeState: UserMapper::PENDING_EMAIL);
        $user->email_temp = (string) (time() + 3600);

        $saved  = null;
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturn($user);
        $mapper->method('save')->willReturnCallback(function (User $u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        $result = (new AuthService($mapper))->confirmEmail('good-token');

        $this->assertNotNull($result);
        $this->assertSame(UserMapper::ACTIVE, $result->active);
        $this->assertSame('', $result->password_temp);
        $this->assertSame($user, Auth::user(), 'a fully-activated account should be logged in');
    }

    public function testConfirmEmailPendingBothTransitionsToPendingModWithoutLoggingIn(): void
    {
        $user             = $this->makeUser(activeState: UserMapper::PENDING_BOTH);
        $user->email_temp = (string) (time() + 3600);

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturn($user);
        $mapper->method('save')->willReturnArgument(0);

        $result = (new AuthService($mapper))->confirmEmail('good-token');

        $this->assertNotNull($result);
        $this->assertSame(UserMapper::PENDING_MOD, $result->active);
        $this->assertNull(Auth::user(), 'still needs moderator approval — must not be logged in yet');
    }

    // -------------------------------------------------------------------------
    // resetPassword()
    // -------------------------------------------------------------------------

    public function testResetPasswordHashesNewPassword(): void
    {
        $user = $this->makeUser();

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnArgument(0);

        (new AuthService($mapper))->resetPassword($user, 'newpassword');

        $this->assertTrue(password_verify('newpassword', $user->password));
        $this->assertSame('', $user->password_temp);
        $this->assertSame('', $user->email_temp);
    }
}

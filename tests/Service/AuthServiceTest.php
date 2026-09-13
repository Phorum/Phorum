<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Core\Auth;
use Phorum\Core\CsrfGuard;
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

        // Deliberately not logged in: the confirmation link lives 48 hours in
        // a mailbox and in access logs, so creating a session here would make
        // it a two-day login credential rather than an activation link.
        $this->assertNull(Auth::user(), 'confirming must not create a session');
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

    // -------------------------------------------------------------------------
    // Session rotation on identity change
    // -------------------------------------------------------------------------

    /**
     * A successful login must re-key the PHP session, so a session id an
     * attacker fixed beforehand — and the CSRF token they could read from it —
     * doesn't carry into the authenticated session.
     */
    public function testLoginRotatesTheSessionAndCsrfToken(): void
    {
        $user   = $this->makeUser();
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByUsername')->willReturn($user);

        $staleToken = CsrfGuard::token();
        $staleId    = session_id();

        (new AuthService($mapper))->login('alice', 'secret');

        $this->assertNotSame($staleId, session_id(), 'session id survived login');
        $this->assertFalse(CsrfGuard::validate($staleToken), 'CSRF token survived login');
    }

    /** Logging out re-keys the session too, so the old token stops working. */
    public function testLogoutRotatesTheSessionAndCsrfToken(): void
    {
        $user   = $this->makeUser();
        $mapper = $this->createMock(UserMapper::class);

        $staleToken = CsrfGuard::token();
        $staleId    = session_id();

        (new AuthService($mapper))->logout($user);

        $this->assertNotSame($staleId, session_id(), 'session id survived logout');
        $this->assertFalse(CsrfGuard::validate($staleToken), 'CSRF token survived logout');
    }

    // -------------------------------------------------------------------------
    // Password changes end existing sessions
    // -------------------------------------------------------------------------

    /**
     * A password reset must drop the remember-me token.
     *
     * resetPassword() always invalidated the short-term session as a side
     * effect (sessid_st is a single-valued column that createSession()
     * overwrites), but sessid_lt was only touched when `remember` was true —
     * which it never is on this path. A stolen year-long cookie therefore kept
     * working after the victim reset their password.
     */
    public function testResetPasswordClearsTheRememberMeToken(): void
    {
        $user                    = $this->makeUser();
        $user->sessid_lt         = 'stolen-long-term-token';
        $user->sessid_st         = 'stolen-short-term-token';
        $user->sessid_st_timeout = time() + 3600;

        $saved  = null;
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnCallback(function ($u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        (new AuthService($mapper))->resetPassword($user, 'brand-new-password');

        $this->assertSame('', $saved->sessid_lt, 'remember-me token survived the reset');
        $this->assertNotSame('stolen-short-term-token', $saved->sessid_st);
    }

    /** The person doing the reset is left logged in on this device. */
    public function testResetPasswordIssuesAFreshSession(): void
    {
        $user = $this->makeUser();

        $saved  = null;
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnCallback(function ($u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        (new AuthService($mapper))->resetPassword($user, 'brand-new-password');

        $this->assertNotSame('', $saved->sessid_st);
        $this->assertGreaterThan(time(), $saved->sessid_st_timeout);
        $this->assertTrue(password_verify('brand-new-password', $saved->password));
    }

    /** Callers acting on someone else's behalf can skip the new session. */
    public function testApplyNewPasswordCanSkipStartingASession(): void
    {
        $user            = $this->makeUser();
        $user->sessid_lt = 'stolen-long-term-token';

        $saved  = null;
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnCallback(function ($u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        (new AuthService($mapper))->applyNewPassword($user, 'brand-new-password', startSession: false);

        $this->assertSame('', $saved->sessid_lt);
        $this->assertSame('', $saved->sessid_st);
    }

    /** verifyCurrentPassword() accepts the real password and nothing else. */
    public function testVerifyCurrentPassword(): void
    {
        $svc  = new AuthService($this->createMock(UserMapper::class));
        $user = $this->makeUser();

        $this->assertTrue($svc->verifyCurrentPassword($user, 'secret'));
        $this->assertFalse($svc->verifyCurrentPassword($user, 'wrong'));
        $this->assertFalse($svc->verifyCurrentPassword($user, ''), 'an empty password must never pass');
    }

    // -------------------------------------------------------------------------
    // One-time tokens are hashed at rest
    // -------------------------------------------------------------------------

    /**
     * password_temp must never hold a token that works as-is. It used to store
     * the raw value, so any database read — a backup, a replica, a SQL
     * injection elsewhere — was a working password reset for every account
     * with one pending.
     */
    public function testPasswordResetStoresOnlyAHashOfTheToken(): void
    {
        $user = $this->makeUser();

        $saved  = null;
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByEmail')->willReturn($user);
        $mapper->method('save')->willReturnCallback(function (User $u) use (&$saved) {
            $saved = $u;
            return $u;
        });
        // By reference, not an arrow function: $saved is still null when this
        // stub is created and is only filled in by the save() callback above.
        $mapper->method('findByPasswordTemp')->willReturnCallback(
            function (string $stored) use (&$saved) {
                return ($saved !== null && $stored === $saved->password_temp) ? $saved : null;
            }
        );

        $svc = new AuthService($mapper);
        $svc->requestPasswordReset('alice@example.com', 'https://site');

        $this->assertNotNull($saved);
        $this->assertNotSame('', $saved->password_temp);

        // The discriminating check: feed the stored value back in as if it had
        // been read out of the database. If password_temp held the raw token
        // it would validate and grant a reset; because it holds a digest, it
        // doesn't. Asserting the shape instead would prove nothing — a raw
        // token and its sha256 are both 64 hex characters.
        $this->assertNull(
            $svc->validateResetToken($saved->password_temp),
            'the value stored in password_temp is usable as a reset token',
        );
    }

    /**
     * Hashing at rest is worthless if lookups then fail, so the raw token from
     * the emailed link must still resolve — and the query must be made with
     * the digest, not the raw value.
     */
    public function testLookupHashesTheTokenBeforeQuerying(): void
    {
        $rawToken            = bin2hex(random_bytes(32));
        $user                = $this->makeUser();
        $user->password_temp = hash('sha256', $rawToken);
        $user->email_temp    = (string) (time() + 3600);

        $queriedWith = null;
        $mapper      = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturnCallback(
            function (string $stored) use (&$queriedWith, $user) {
                $queriedWith = $stored;
                return $stored === $user->password_temp ? $user : null;
            }
        );

        $this->assertNotNull((new AuthService($mapper))->validateResetToken($rawToken));
        $this->assertSame(
            hash('sha256', $rawToken),
            $queriedWith,
            'the lookup queried the raw token instead of its digest',
        );
    }

    /**
     * Email confirmation shares the column and must hash the same way.
     */
    public function testConfirmationLookupAlsoHashesTheToken(): void
    {
        $rawToken            = bin2hex(random_bytes(32));
        $user                = $this->makeUser(activeState: UserMapper::PENDING_EMAIL);
        $user->password_temp = hash('sha256', $rawToken);
        $user->email_temp    = (string) (time() + 3600);

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturnCallback(
            fn(string $stored) => $stored === $user->password_temp ? $user : null
        );

        $this->assertNotNull((new AuthService($mapper))->confirmEmail($rawToken));
    }

    /** A raw token that happens to match the stored digest must not validate. */
    public function testStoredHashIsNotItselfUsableAsAToken(): void
    {
        $user                = $this->makeUser();
        $rawToken            = bin2hex(random_bytes(32));
        $user->password_temp = hash('sha256', $rawToken);
        $user->email_temp    = (string) (time() + 3600);

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturnCallback(
            fn(string $stored) => $stored === $user->password_temp ? $user : null
        );

        $svc = new AuthService($mapper);

        $this->assertNotNull($svc->validateResetToken($rawToken), 'the real token should work');
        $this->assertNull(
            $svc->validateResetToken($user->password_temp),
            'the stored digest must not be usable as a token',
        );
    }

    // -------------------------------------------------------------------------
    // email_verified lifecycle
    // -------------------------------------------------------------------------

    /** A self-declared address at signup is not proved. */
    public function testRegisterLeavesEmailUnverified(): void
    {
        $saved  = null;
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnCallback(function (User $u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        (new AuthService($mapper))->register('newbie', 'newbie@example.com', 'secret');

        $this->assertSame(0, $saved->email_verified);
    }

    /** Following the confirmation link proves it. */
    public function testConfirmEmailMarksTheAddressVerified(): void
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

        (new AuthService($mapper))->confirmEmail('good-token');

        $this->assertSame(1, $saved->email_verified);
    }

    /**
     * Completing a password reset proves mailbox control too — and is how
     * accounts backfilled as unverified by the schema patch earn it back.
     */
    public function testResetPasswordMarksTheAddressVerified(): void
    {
        $user                 = $this->makeUser();
        $user->email_verified = 0;

        $saved  = null;
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnCallback(function (User $u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        (new AuthService($mapper))->resetPassword($user, 'brand-new-password');

        $this->assertSame(1, $saved->email_verified);
    }

    /** Changing a password on its own proves nothing about the address. */
    public function testApplyNewPasswordDoesNotMarkTheAddressVerified(): void
    {
        $user                 = $this->makeUser();
        $user->email_verified = 0;

        $saved  = null;
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnCallback(function (User $u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        (new AuthService($mapper))->applyNewPassword($user, 'brand-new-password');

        $this->assertSame(0, $saved->email_verified);
    }
}

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
        string $password = '',
        bool $active     = true,
        bool $admin      = false,
    ): User {
        $u           = new User();
        $u->user_id  = 1;
        $u->username = 'alice';
        $u->email    = 'alice@example.com';
        $u->active   = $active ? 1 : 0;
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

    public function testRegisterCreatesInactiveUserWithConfirmation(): void
    {
        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('save')->willReturnArgument(0);

        $user = (new AuthService($mapper))->register(
            'bob', 'bob@example.com', 'pass123', requireConfirmation: true
        );

        $this->assertSame(0, $user->active);
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
        $user           = $this->makeUser(active: false);
        $user->email_temp = (string) (time() - 10);

        $mapper = $this->createMock(UserMapper::class);
        $mapper->method('findByPasswordTemp')->willReturn($user);

        $result = (new AuthService($mapper))->confirmEmail('some-token');
        $this->assertNull($result);
    }

    public function testConfirmEmailActivatesUserOnValidToken(): void
    {
        $user             = $this->makeUser(active: false);
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
        $this->assertSame(1, $result->active);
        $this->assertSame('', $result->password_temp);
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

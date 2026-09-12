<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\UserController;
use Phorum\Http\Request;
use Phorum\Mapper\FileMapper;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\PmBuddyMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\User;
use Phorum\Service\FileService;
use Phorum\Service\PermissionService;
use Phorum\Tests\Http\ControllerTestCase;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

class UserControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): UserController
    {
        $fileMapper = $deps['fileMapper'] ?? $this->createMock(FileMapper::class);
        return new UserController(
            config:      $this->makeConfig(),
            twig:        $deps['twig']        ?? $this->makeTwig(),
            users:       $deps['users']       ?? $this->createMock(UserMapper::class),
            messages:    $deps['messages']    ?? $this->createMock(MessageMapper::class),
            fileService: $deps['fileService'] ?? $this->createMock(FileService::class),
            fileMapper:  $fileMapper,
            buddies:     $deps['buddies']     ?? $this->createMock(PmBuddyMapper::class),
            perms:       $deps['perms']       ?? $this->createMock(PermissionService::class),
            forums:      $deps['forums']      ?? $this->createMock(ForumMapper::class),
        );
    }

    private function makeCapturingTwig(callable $assertion): Environment
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('getLoader')->willReturn($this->createMock(LoaderInterface::class));
        $twig->expects($this->once())->method('render')->with(
            'user/profile.html.twig',
            $this->callback($assertion),
        )->willReturn('<html>ok</html>');
        return $twig;
    }

    // -------------------------------------------------------------------------
    // profile
    // -------------------------------------------------------------------------

    public function testProfileReturns404ForUnknownUser(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->profile(new Request(tokens: ['user_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testProfileReturns404ForInactiveUser(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(1, false));

        // Override active flag
        $inactive         = $this->makeUser(1);
        $inactive->active = 0;
        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($inactive);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->profile(new Request(tokens: ['user_id' => '1']));
        $this->assertSame(404, $response->status);
    }

    public function testProfileReturns200ForActiveUser(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByUser')->willReturn([]);

        $ctrl     = $this->makeController(['users' => $users, 'messages' => $messages]);
        $response = $ctrl->profile(new Request(tokens: ['user_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testProfileShowsHiddenFieldsForAdminViewer(): void
    {
        Auth::setUser($this->makeUser(2, admin: true));

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByUser')->willReturn([]);

        $twig = $this->makeCapturingTwig(fn(array $data) => ($data['can_view_hidden'] ?? null) === true);

        $ctrl = $this->makeController(['users' => $users, 'messages' => $messages, 'twig' => $twig]);
        $ctrl->profile(new Request(tokens: ['user_id' => '1']));
    }

    public function testProfileShowsHiddenFieldsForUserModerator(): void
    {
        Auth::setUser($this->makeUser(2));

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByUser')->willReturn([]);

        $perms = $this->createMock(PermissionService::class);
        $perms->method('canModerateUsersAnywhere')->willReturn(true);

        $twig = $this->makeCapturingTwig(fn(array $data) => ($data['can_view_hidden'] ?? null) === true);

        $ctrl = $this->makeController(['users' => $users, 'messages' => $messages, 'twig' => $twig, 'perms' => $perms]);
        $ctrl->profile(new Request(tokens: ['user_id' => '1']));
    }

    public function testProfileHidesFieldsForRegularViewer(): void
    {
        Auth::setUser($this->makeUser(2));

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByUser')->willReturn([]);

        $perms = $this->createMock(PermissionService::class);
        $perms->method('canModerateUsersAnywhere')->willReturn(false);

        $twig = $this->makeCapturingTwig(fn(array $data) => ($data['can_view_hidden'] ?? null) === false);

        $ctrl = $this->makeController(['users' => $users, 'messages' => $messages, 'twig' => $twig, 'perms' => $perms]);
        $ctrl->profile(new Request(tokens: ['user_id' => '1']));
    }

    public function testProfileHidesFieldsForAnonymousViewer(): void
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByUser')->willReturn([]);

        $twig = $this->makeCapturingTwig(fn(array $data) => ($data['can_view_hidden'] ?? null) === false);

        $ctrl = $this->makeController(['users' => $users, 'messages' => $messages, 'twig' => $twig]);
        $ctrl->profile(new Request(tokens: ['user_id' => '1']));
    }

    public function testProfilePassesLastActiveForumOnOwnProfile(): void
    {
        $viewer = $this->makeUser(1);
        $viewer->last_active_forum = 5;
        Auth::setUser($viewer);

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByUser')->willReturn([]);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->with(5)->willReturn($this->makeForum(5, ['name' => 'General', 'active' => 1]));

        $twig = $this->makeCapturingTwig(
            fn(array $data) => ($data['last_active_forum'] ?? null)?->forum_id === 5
        );

        $ctrl = $this->makeController(['users' => $users, 'messages' => $messages, 'forums' => $forums, 'twig' => $twig]);
        $ctrl->profile(new Request(tokens: ['user_id' => '1']));
    }

    public function testProfileOmitsLastActiveForumWhenViewingSomeoneElse(): void
    {
        Auth::setUser($this->makeUser(2));

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByUser')->willReturn([]);

        $forums = $this->createMock(ForumMapper::class);
        $forums->expects($this->never())->method('load');

        $twig = $this->makeCapturingTwig(fn(array $data) => array_key_exists('last_active_forum', $data) && $data['last_active_forum'] === null);

        $ctrl = $this->makeController(['users' => $users, 'messages' => $messages, 'forums' => $forums, 'twig' => $twig]);
        $ctrl->profile(new Request(tokens: ['user_id' => '1']));
    }

    public function testProfileOmitsLastActiveForumWhenForumInactive(): void
    {
        $viewer = $this->makeUser(1);
        $viewer->last_active_forum = 5;
        Auth::setUser($viewer);

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByUser')->willReturn([]);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(5, ['active' => 0]));

        $twig = $this->makeCapturingTwig(fn(array $data) => array_key_exists('last_active_forum', $data) && $data['last_active_forum'] === null);

        $ctrl = $this->makeController(['users' => $users, 'messages' => $messages, 'forums' => $forums, 'twig' => $twig]);
        $ctrl->profile(new Request(tokens: ['user_id' => '1']));
    }

    // -------------------------------------------------------------------------
    // settings
    // -------------------------------------------------------------------------

    public function testSettingsRedirectsIfNotLoggedIn(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->settings(new Request());
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('/login', $response->headers['Location']);
    }

    public function testSettingsReturnsFormOnGetWhenLoggedIn(): void
    {
        Auth::setUser($this->makeUser());
        $ctrl     = $this->makeController();
        $response = $ctrl->settings($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testSettingsPostValidationErrorForEmptyDisplayName(): void
    {
        $user = $this->makeUser();
        Auth::setUser($user);

        $ctrl     = $this->makeController();
        $response = $ctrl->settings($this->makePostRequest([
            'display_name' => '',
            'email'        => 'user1@example.com',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testSettingsPostValidationErrorForInvalidEmail(): void
    {
        Auth::setUser($this->makeUser());

        $ctrl     = $this->makeController();
        $response = $ctrl->settings($this->makePostRequest([
            'display_name' => 'Valid Name',
            'email'        => 'not-an-email',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testSettingsPostValidationErrorForShortPassword(): void
    {
        Auth::setUser($this->makeUser());

        $ctrl     = $this->makeController();
        $response = $ctrl->settings($this->makePostRequest([
            'display_name' => 'Valid Name',
            'email'        => 'user1@example.com',
            'password'     => 'abc',
            'password2'    => 'abc',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testSettingsPostSavesAndReturns200(): void
    {
        $user = $this->makeUser();
        Auth::setUser($user);

        $users = $this->createMock(UserMapper::class);
        $users->expects($this->once())->method('save');
        $users->method('findByEmail')->willReturn(null);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->settings($this->makePostRequest([
            'display_name' => 'New Name',
            'email'        => 'user1@example.com',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testSettingsPostSavesValidEmailNotifyValue(): void
    {
        $user = $this->makeUser();
        Auth::setUser($user);

        $saved = null;
        $users = $this->createMock(UserMapper::class);
        $users->method('findByEmail')->willReturn(null);
        $users->method('save')->willReturnCallback(function ($u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->settings($this->makePostRequest([
            'display_name' => 'New Name',
            'email'        => 'user1@example.com',
            'email_notify' => '2',
        ]));
        $this->assertSame(200, $response->status);
        $this->assertSame(2, $saved->email_notify);
    }

    public function testSettingsPostRejectsInvalidEmailNotifyValue(): void
    {
        $user = $this->makeUser();
        Auth::setUser($user);

        $users = $this->createMock(UserMapper::class);
        $users->method('findByEmail')->willReturn(null);
        $users->expects($this->never())->method('save');

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->settings($this->makePostRequest([
            'display_name' => 'New Name',
            'email'        => 'user1@example.com',
            'email_notify' => '99',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testSettingsPostSavesValidLanguageAndTheme(): void
    {
        $user = $this->makeUser();
        Auth::setUser($user);

        $saved = null;
        $users = $this->createMock(UserMapper::class);
        $users->method('findByEmail')->willReturn(null);
        $users->method('save')->willReturnCallback(function ($u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->settings($this->makePostRequest([
            'display_name'  => 'New Name',
            'email'         => 'user1@example.com',
            'user_language' => 'fr',
            'user_template' => 'ruby',
        ]));
        $this->assertSame(200, $response->status);
        $this->assertSame('fr', $saved->user_language);
        $this->assertSame('ruby', $saved->user_template);
    }

    public function testSettingsPostFallsBackToSiteDefaultForUnknownLanguageAndTheme(): void
    {
        $user = $this->makeUser();
        Auth::setUser($user);

        $saved = null;
        $users = $this->createMock(UserMapper::class);
        $users->method('findByEmail')->willReturn(null);
        $users->method('save')->willReturnCallback(function ($u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->settings($this->makePostRequest([
            'display_name'  => 'New Name',
            'email'         => 'user1@example.com',
            'user_language' => 'not-a-real-locale',
            'user_template' => 'not-a-real-theme',
        ]));
        $this->assertSame(200, $response->status);
        $this->assertSame('', $saved->user_language);
        $this->assertSame('', $saved->user_template);
    }

    public function testSettingsPostReturns403WithBadCsrf(): void
    {
        Auth::setUser($this->makeUser());

        $ctrl     = $this->makeController();
        $response = $ctrl->settings(new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
        ));
        $this->assertSame(403, $response->status);
    }

    public function testSettingsPostWithNewPasswordClearsForcePasswordChange(): void
    {
        $user = $this->makeUser();
        $user->force_password_change = 1;
        Auth::setUser($user);

        $saved = null;
        $users = $this->createMock(UserMapper::class);
        $users->method('findByEmail')->willReturn(null);
        $users->method('save')->willReturnCallback(function ($u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->settings($this->makePostRequest([
            'display_name'     => 'New Name',
            'email'            => 'user1@example.com',
            'current_password' => 'secret',
            'password'         => 'newsecret',
            'password2'        => 'newsecret',
        ]));
        $this->assertSame(200, $response->status);
        $this->assertSame(0, $saved->force_password_change);
    }

    // -------------------------------------------------------------------------
    // forcePasswordChange
    // -------------------------------------------------------------------------

    public function testForcePasswordChangeRedirectsIfNotLoggedIn(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->forcePasswordChange(new Request());
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('/login', $response->headers['Location']);
    }

    public function testForcePasswordChangeReturnsFormOnGet(): void
    {
        Auth::setUser($this->makeUser());
        $ctrl     = $this->makeController();
        $response = $ctrl->forcePasswordChange($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testForcePasswordChangeValidationErrorForShortPassword(): void
    {
        Auth::setUser($this->makeUser());
        $ctrl     = $this->makeController();
        $response = $ctrl->forcePasswordChange($this->makePostRequest([
            'password'  => 'abc',
            'password2' => 'abc',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testForcePasswordChangeValidationErrorForMismatch(): void
    {
        Auth::setUser($this->makeUser());
        $ctrl     = $this->makeController();
        $response = $ctrl->forcePasswordChange($this->makePostRequest([
            'password'  => 'secret1',
            'password2' => 'secret2',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testForcePasswordChangeSuccessClearsFlagAndRedirects(): void
    {
        $user = $this->makeUser();
        $user->force_password_change = 1;
        Auth::setUser($user);

        $saved = null;
        $users = $this->createMock(UserMapper::class);
        $users->method('save')->willReturnCallback(function ($u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->forcePasswordChange($this->makePostRequest([
            'password'  => 'newsecret',
            'password2' => 'newsecret',
            'redirect'  => '/forum/5',
        ]));
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/5', $response->headers['Location']);
        $this->assertSame(0, $saved->force_password_change);
    }

    public function testForcePasswordChangeReturns403WithBadCsrf(): void
    {
        Auth::setUser($this->makeUser());
        $ctrl     = $this->makeController();
        $response = $ctrl->forcePasswordChange(new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
        ));
        $this->assertSame(403, $response->status);
    }

    // -------------------------------------------------------------------------
    // Settings — re-authentication and session invalidation
    // -------------------------------------------------------------------------

    /**
     * Build a users mock that records the last saved User.
     *
     * @param mixed $saved Receives the saved User, by reference.
     */
    private function makeCapturingUsers(&$saved, ?User $emailOwner = null): UserMapper
    {
        $users = $this->createMock(UserMapper::class);
        $users->method('findByEmail')->willReturn($emailOwner);
        $users->method('save')->willReturnCallback(function ($u) use (&$saved) {
            $saved = $u;
            return $u;
        });
        return $users;
    }

    /**
     * A new password without the current one must be refused: otherwise a
     * borrowed or hijacked session is enough to take the account over.
     */
    public function testSettingsRejectsPasswordChangeWithoutCurrentPassword(): void
    {
        Auth::setUser($this->makeUser());

        $saved = null;
        $ctrl  = $this->makeController(['users' => $this->makeCapturingUsers($saved)]);

        $ctrl->settings($this->makePostRequest([
            'display_name' => 'User 1',
            'email'        => 'user1@example.com',
            'password'     => 'newsecret',
            'password2'    => 'newsecret',
        ]));

        $this->assertNull($saved, 'the change was saved without re-authentication');
    }

    /** A wrong current password is refused too. */
    public function testSettingsRejectsPasswordChangeWithWrongCurrentPassword(): void
    {
        Auth::setUser($this->makeUser());

        $saved = null;
        $ctrl  = $this->makeController(['users' => $this->makeCapturingUsers($saved)]);

        $ctrl->settings($this->makePostRequest([
            'display_name'     => 'User 1',
            'email'            => 'user1@example.com',
            'current_password' => 'not-the-password',
            'password'         => 'newsecret',
            'password2'        => 'newsecret',
        ]));

        $this->assertNull($saved);
    }

    /**
     * Changing the email address needs it as well — a new address can be used
     * to request a password reset, so it takes the account over just as surely.
     */
    public function testSettingsRejectsEmailChangeWithoutCurrentPassword(): void
    {
        Auth::setUser($this->makeUser());

        $saved = null;
        $ctrl  = $this->makeController(['users' => $this->makeCapturingUsers($saved)]);

        $ctrl->settings($this->makePostRequest([
            'display_name' => 'User 1',
            'email'        => 'attacker@example.com',
        ]));

        $this->assertNull($saved, 'the email was changed without re-authentication');
    }

    /** Everything else on the page still saves without re-authenticating. */
    public function testSettingsSavesOtherFieldsWithoutCurrentPassword(): void
    {
        Auth::setUser($this->makeUser());

        $saved = null;
        $ctrl  = $this->makeController(['users' => $this->makeCapturingUsers($saved)]);

        $ctrl->settings($this->makePostRequest([
            'display_name' => 'Renamed',
            'email'        => 'user1@example.com',
            'signature'    => 'hello',
        ]));

        $this->assertNotNull($saved);
        $this->assertSame('Renamed', $saved->display_name);
    }

    /**
     * Changing the password must end every other session on the account,
     * including the year-long remember-me token.
     */
    public function testSettingsPasswordChangeClearsExistingSessionTokens(): void
    {
        $user                    = $this->makeUser();
        $user->sessid_lt         = 'stolen-long-term-token';
        $user->sessid_st         = 'stolen-short-term-token';
        $user->sessid_st_timeout = time() + 3600;
        Auth::setUser($user);

        $saved = null;
        $ctrl  = $this->makeController(['users' => $this->makeCapturingUsers($saved)]);

        $ctrl->settings($this->makePostRequest([
            'display_name'     => 'User 1',
            'email'            => 'user1@example.com',
            'current_password' => 'secret',
            'password'         => 'newsecret',
            'password2'        => 'newsecret',
        ]));

        $this->assertNotNull($saved);
        $this->assertSame('', $saved->sessid_lt, 'remember-me token survived the password change');
        $this->assertNotSame('stolen-short-term-token', $saved->sessid_st);
    }

    /**
     * A changed address is unproven again. Without this, someone could verify
     * one address, switch to a victim's, and keep the flag — handing OAuth
     * exactly the link the verification check exists to refuse.
     */
    public function testChangingEmailClearsTheVerifiedFlag(): void
    {
        $user                 = $this->makeUser();
        $user->email_verified = 1;
        Auth::setUser($user);

        $saved = null;
        $ctrl  = $this->makeController(['users' => $this->makeCapturingUsers($saved)]);

        $ctrl->settings($this->makePostRequest([
            'display_name'     => 'User 1',
            'email'            => 'somewhere-else@example.com',
            'current_password' => 'secret',
        ]));

        $this->assertNotNull($saved);
        $this->assertSame(0, $saved->email_verified);
    }

    /** Saving other fields leaves an already-verified address verified. */
    public function testSavingWithoutChangingEmailKeepsTheVerifiedFlag(): void
    {
        $user                 = $this->makeUser();
        $user->email_verified = 1;
        Auth::setUser($user);

        $saved = null;
        $ctrl  = $this->makeController(['users' => $this->makeCapturingUsers($saved)]);

        $ctrl->settings($this->makePostRequest([
            'display_name' => 'Renamed',
            'email'        => 'user1@example.com',
        ]));

        $this->assertNotNull($saved);
        $this->assertSame(1, $saved->email_verified);
    }
}

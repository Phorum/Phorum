<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\UserController;
use Phorum\Http\Request;
use Phorum\Mapper\FileMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\PmBuddyMapper;
use Phorum\Mapper\UserMapper;
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
            'display_name' => 'New Name',
            'email'        => 'user1@example.com',
            'password'     => 'newsecret',
            'password2'    => 'newsecret',
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
}

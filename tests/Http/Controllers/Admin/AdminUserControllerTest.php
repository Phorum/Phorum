<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Core\Auth;
use Phorum\Core\Impersonation;
use Phorum\Http\Controllers\Admin\UserController;
use Phorum\Http\Request;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\CustomFieldService;
use Phorum\Tests\Http\ControllerTestCase;

class AdminUserControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): UserController
    {
        $cfService = $deps['cfService'] ?? $this->createMock(CustomFieldService::class);
        $cfService->method('saveUserFields')->willReturn([]);
        $cfService->method('getAdminUserFields')->willReturn([]);

        return new UserController(
            config:    $this->makeConfig(),
            twig:      $this->makeTwig(),
            users:     $deps['users']     ?? $this->createMock(UserMapper::class),
            cfService: $cfService,
            modLog:    $deps['modLog']    ?? $this->createMock(ModLogMapper::class),
        );
    }

    // -------------------------------------------------------------------------
    // Auth guards
    // -------------------------------------------------------------------------

    public function testIndexRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    public function testEditRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->edit(new Request(tokens: ['user_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // edit
    // -------------------------------------------------------------------------

    public function testEditReturns404WhenUserNotFound(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->edit(new Request(tokens: ['user_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testEditGetReturns200(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(2));

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->edit($this->makeGetRequest(tokens: ['user_id' => '2']));
        $this->assertSame(200, $response->status);
    }

    public function testEditPostValidationErrorForEmptyDisplayName(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(2));

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   ['display_name' => '', 'email' => 'user2@example.com'],
            tokens: ['user_id' => '2'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testEditPostValidationErrorForInvalidEmail(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(2));

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   ['display_name' => 'User Two', 'email' => 'not-an-email'],
            tokens: ['user_id' => '2'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testEditPostSavesAndReturns200(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $target = $this->makeUser(2);
        $users  = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($target);
        $users->method('findByEmail')->willReturn(null);
        $users->expects($this->once())->method('save');

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   ['display_name' => 'New Name', 'email' => 'user2@example.com', 'active' => '1'],
            tokens: ['user_id' => '2'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testEditPostReturns403WithBadCsrf(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(2));

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->edit(new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
            tokens: ['user_id' => '2'],
        ));
        $this->assertSame(403, $response->status);
    }

    // -------------------------------------------------------------------------
    // impersonate
    // -------------------------------------------------------------------------

    public function testImpersonateRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->impersonate($this->makePostRequest(tokens: ['user_id' => '2']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    public function testImpersonateReturns403WithBadCsrf(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl     = $this->makeController();
        $response = $ctrl->impersonate(new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
            tokens: ['user_id' => '2'],
        ));
        $this->assertSame(403, $response->status);
    }

    public function testImpersonateReturns404WhenTargetNotFound(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->impersonate($this->makePostRequest(tokens: ['user_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testImpersonateForbidsAdminTarget(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($this->makeUser(2, true));

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->impersonate($this->makePostRequest(tokens: ['user_id' => '2']));
        $this->assertSame(403, $response->status);
        $this->assertFalse(Impersonation::isActive());
    }

    public function testImpersonateForbidsSelfTarget(): void
    {
        $admin = $this->makeUser(1, true);
        $this->setAdminUser($admin);

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($admin);

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->impersonate($this->makePostRequest(tokens: ['user_id' => '1']));
        $this->assertSame(403, $response->status);
        $this->assertFalse(Impersonation::isActive());
    }

    public function testImpersonateStartsSessionAndRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $target = $this->makeUser(2);
        $users  = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($target);

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(1, 'impersonate_start', 'user', 2, 0, $target->username);

        $ctrl     = $this->makeController(['users' => $users, 'modLog' => $modLog]);
        $response = $ctrl->impersonate($this->makePostRequest(tokens: ['user_id' => '2']));

        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['Location']);
        $this->assertTrue(Impersonation::isActive());
        $this->assertSame($target, Auth::user());
    }

    // -------------------------------------------------------------------------
    // stopImpersonate
    // -------------------------------------------------------------------------

    public function testStopImpersonateRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->stopImpersonate($this->makePostRequest());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    public function testStopImpersonateReturns403WithBadCsrf(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl     = $this->makeController();
        $response = $ctrl->stopImpersonate(new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
        ));
        $this->assertSame(403, $response->status);
    }

    public function testStopImpersonateClearsSessionAndRedirects(): void
    {
        $admin  = $this->makeUser(1, true);
        $target = $this->makeUser(2);
        $this->setAdminUser($admin);
        Impersonation::start($admin, $target, $this->makeConfig());

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(1, 'impersonate_stop', 'user', 2, 0, $target->username);

        $ctrl     = $this->makeController(['modLog' => $modLog]);
        $response = $ctrl->stopImpersonate($this->makePostRequest());

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/users', $response->headers['Location']);
        $this->assertFalse(Impersonation::isActive());
        $this->assertNull(Auth::user());
    }
}

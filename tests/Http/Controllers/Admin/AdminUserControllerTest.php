<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Core\Auth;
use Phorum\Core\Impersonation;
use Phorum\Hook\HookDispatcher;
use Phorum\Http\Controllers\Admin\UserController;
use Phorum\Http\Request;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mapper\SearchMapper;
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
            config:      $this->makeConfig(),
            twig:        $this->makeTwig(),
            users:       $deps['users']       ?? $this->createMock(UserMapper::class),
            cfService:   $cfService,
            modLog:      $deps['modLog']      ?? $this->createMock(ModLogMapper::class),
            messages:    $deps['messages']    ?? $this->createMock(MessageMapper::class),
            searchIndex: $deps['searchIndex'] ?? $this->createMock(SearchMapper::class),
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

    public function testEditPostSetsForcePasswordChangeFlag(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $saved  = null;
        $target = $this->makeUser(2);
        $users  = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($target);
        $users->method('findByEmail')->willReturn(null);
        $users->method('save')->willReturnCallback(function ($u) use (&$saved) {
            $saved = $u;
            return $u;
        });

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   [
                'display_name'           => 'User Two',
                'email'                  => 'user2@example.com',
                'force_password_change'  => '1',
            ],
            tokens: ['user_id' => '2'],
        ));
        $this->assertSame(200, $response->status);
        $this->assertSame(1, $saved->force_password_change);
    }

    public function testEditPostEnablingShadowBanFlipsMessagesAndLogsAction(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $target = $this->makeUser(2);
        $target->shadow_banned = 0;
        $users  = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($target);
        $users->method('findByEmail')->willReturn(null);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findIdsByUserStatus')->with(2, MessageMapper::STATUS_APPROVED)->willReturn([10, 11]);
        $messages->expects($this->once())->method('setStatusForUser')
            ->with(2, MessageMapper::STATUS_APPROVED, MessageMapper::STATUS_SHADOW);

        $searchIndex = $this->createMock(SearchMapper::class);
        $searchIndex->expects($this->exactly(2))->method('removeMessage');

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(1, 'shadow_ban_enable', 'user', 2, 0, $target->username);

        $ctrl     = $this->makeController([
            'users'       => $users,
            'messages'    => $messages,
            'searchIndex' => $searchIndex,
            'modLog'      => $modLog,
        ]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   ['display_name' => 'User Two', 'email' => 'user2@example.com', 'shadow_banned' => '1'],
            tokens: ['user_id' => '2'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testEditPostEnablingShadowBanFiresAfterShadowBanChangeHook(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $target = $this->makeUser(2);
        $target->shadow_banned = 0;
        $users  = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($target);
        $users->method('findByEmail')->willReturn(null);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findIdsByUserStatus')->willReturn([]);

        $fired = null;
        HookDispatcher::getInstance()->register('after_shadow_ban_change', function (array $payload) use (&$fired) {
            $fired = $payload;
            return null;
        });

        $ctrl = $this->makeController(['users' => $users, 'messages' => $messages]);
        $ctrl->edit($this->makePostRequest(
            post:   ['display_name' => 'User Two', 'email' => 'user2@example.com', 'shadow_banned' => '1'],
            tokens: ['user_id' => '2'],
        ));

        $this->assertNotNull($fired);
        $this->assertSame($target, $fired['user']);
        $this->assertTrue($fired['enabled']);
    }

    public function testEditPostDisablingShadowBanRestoresMessagesAndLogsAction(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $target = $this->makeUser(2);
        $target->shadow_banned = 1;
        $users  = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($target);
        $users->method('findByEmail')->willReturn(null);

        $reindexed = new \Phorum\Model\Message();
        $reindexed->message_id = 20;
        $reindexed->forum_id   = 3;
        $reindexed->author     = 'user2';
        $reindexed->subject    = 'Subj';
        $reindexed->body       = 'Body';

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findIdsByUserStatus')->with(2, MessageMapper::STATUS_SHADOW)->willReturn([20]);
        $messages->method('load')->with(20)->willReturn($reindexed);
        $messages->expects($this->once())->method('setStatusForUser')
            ->with(2, MessageMapper::STATUS_SHADOW, MessageMapper::STATUS_APPROVED);

        $searchIndex = $this->createMock(SearchMapper::class);
        $searchIndex->expects($this->once())->method('indexMessage')->with(20, 3, 'user2', 'Subj', 'Body');

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(1, 'shadow_ban_disable', 'user', 2, 0, $target->username);

        $ctrl     = $this->makeController([
            'users'       => $users,
            'messages'    => $messages,
            'searchIndex' => $searchIndex,
            'modLog'      => $modLog,
        ]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   ['display_name' => 'User Two', 'email' => 'user2@example.com'],
            tokens: ['user_id' => '2'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testEditPostRejectsShadowBanningAnAdmin(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $target = $this->makeUser(2, admin: true);
        $users  = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($target);
        $users->method('findByEmail')->willReturn(null);
        $users->expects($this->never())->method('save');

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   ['display_name' => 'User Two', 'email' => 'user2@example.com', 'admin' => '1', 'shadow_banned' => '1'],
            tokens: ['user_id' => '2'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testEditPostRejectsShadowBanningSelf(): void
    {
        $admin = $this->makeUser(1, true);
        $this->setAdminUser($admin);

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->willReturn($admin);
        $users->method('findByEmail')->willReturn(null);
        $users->expects($this->never())->method('save');

        $ctrl     = $this->makeController(['users' => $users]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   ['display_name' => 'Admin', 'email' => 'user1@example.com', 'shadow_banned' => '1'],
            tokens: ['user_id' => '1'],
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

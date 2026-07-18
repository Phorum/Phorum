<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Hook\HookDispatcher;
use Phorum\Http\Controllers\Admin\BanController;
use Phorum\Http\Request;
use Phorum\Mapper\BanMapper;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Model\Ban;
use Phorum\Tests\Http\ControllerTestCase;

class AdminBanControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): BanController
    {
        return new BanController(
            config: $this->makeConfig(),
            twig:   $this->makeTwig(),
            bans:   $deps['bans']   ?? $this->createMock(BanMapper::class),
            forums: $deps['forums'] ?? $this->createMock(ForumMapper::class),
            modLog: $deps['modLog'] ?? $this->createMock(ModLogMapper::class),
        );
    }

    private function makeBan(int $id = 1, array $override = []): Ban
    {
        $ban         = new Ban();
        $ban->id     = $id;
        $ban->type   = 1;
        $ban->string = 'evil.com';
        foreach ($override as $k => $v) {
            $ban->$k = $v;
        }
        return $ban;
    }

    // -------------------------------------------------------------------------
    // Auth guards — all methods redirect when not admin
    // -------------------------------------------------------------------------

    public function testIndexRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    public function testCreateRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->create(new Request());
        $this->assertSame(302, $response->status);
    }

    public function testEditRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->edit(new Request(tokens: ['ban_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    public function testDeleteRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->delete(new Request(tokens: ['ban_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function testIndexReturns200WhenAdmin(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $bans = $this->createMock(BanMapper::class);
        $bans->method('find')->willReturn([]);
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['bans' => $bans, 'forums' => $forums]);
        $response = $ctrl->index(new Request());
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    public function testCreateGetReturns200WhenAdmin(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->create($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testCreatePostValidationErrorForEmptyString(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->create($this->makePostRequest(['type' => '1', 'string' => '']));
        $this->assertSame(200, $response->status);
    }

    public function testCreatePostValidationErrorForInvalidType(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->create($this->makePostRequest(['type' => '99', 'string' => 'evil.com']));
        $this->assertSame(200, $response->status);
    }

    public function testCreatePostSuccessRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $bans = $this->createMock(BanMapper::class);
        $bans->expects($this->once())->method('save');
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);
        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(1, 'create', 'ban', 0, 0, 'evil.com');

        $ctrl     = $this->makeController(['bans' => $bans, 'forums' => $forums, 'modLog' => $modLog]);
        $response = $ctrl->create($this->makePostRequest(['type' => '1', 'string' => 'evil.com']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/bans', $response->headers['Location']);
    }

    public function testCreatePostFiresAfterBanCreateHook(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $fired = null;
        HookDispatcher::getInstance()->register('after_ban_create', function (Ban $ban) use (&$fired) {
            $fired = $ban;
            return null;
        });

        $bans = $this->createMock(BanMapper::class);
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl = $this->makeController(['bans' => $bans, 'forums' => $forums]);
        $ctrl->create($this->makePostRequest(['type' => '1', 'string' => 'evil.com']));

        $this->assertInstanceOf(Ban::class, $fired);
        $this->assertSame('evil.com', $fired->string);
    }

    // -------------------------------------------------------------------------
    // edit
    // -------------------------------------------------------------------------

    public function testEditReturns404WhenBanNotFound(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $bans = $this->createMock(BanMapper::class);
        $bans->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['bans' => $bans]);
        $response = $ctrl->edit(new Request(tokens: ['ban_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testEditGetReturns200(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $bans = $this->createMock(BanMapper::class);
        $bans->method('load')->willReturn($this->makeBan(1));
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['bans' => $bans, 'forums' => $forums]);
        $response = $ctrl->edit($this->makeGetRequest(tokens: ['ban_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testEditPostSuccessRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $bans = $this->createMock(BanMapper::class);
        $bans->method('load')->willReturn($this->makeBan(1));
        $bans->expects($this->once())->method('save');
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);
        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(1, 'update', 'ban', 1, 0, 'spammer');

        $ctrl     = $this->makeController(['bans' => $bans, 'forums' => $forums, 'modLog' => $modLog]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   ['type' => '2', 'string' => 'spammer'],
            tokens: ['ban_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/bans', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    public function testDeleteReturns404WhenBanNotFound(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $bans = $this->createMock(BanMapper::class);
        $bans->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['bans' => $bans]);
        $response = $ctrl->delete(new Request(tokens: ['ban_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testDeleteGetReturnsConfirmForm(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $bans = $this->createMock(BanMapper::class);
        $bans->method('load')->willReturn($this->makeBan(1));

        $ctrl     = $this->makeController(['bans' => $bans]);
        $response = $ctrl->delete($this->makeGetRequest(tokens: ['ban_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testDeletePostDeletesAndRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $bans = $this->createMock(BanMapper::class);
        $bans->method('load')->willReturn($this->makeBan(1));
        $bans->expects($this->once())->method('delete')->with(1);
        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(1, 'delete', 'ban', 1, 0, 'evil.com');

        $ctrl     = $this->makeController(['bans' => $bans, 'modLog' => $modLog]);
        $response = $ctrl->delete($this->makePostRequest(tokens: ['ban_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/bans', $response->headers['Location']);
    }
}

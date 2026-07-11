<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Http\Controllers\Admin\AuditLogController;
use Phorum\Http\Request;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\ModLog;
use Phorum\Tests\Http\ControllerTestCase;

class AdminAuditLogControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): AuditLogController
    {
        return new AuditLogController(
            config: $this->makeConfig(),
            twig:   $this->makeTwig(),
            modLog: $deps['modLog'] ?? $this->createMock(ModLogMapper::class),
            users:  $deps['users']  ?? $this->createMock(UserMapper::class),
            forums: $deps['forums'] ?? $this->createMock(ForumMapper::class),
        );
    }

    public function testIndexRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    public function testIndexReturns200WithNoEntries(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->method('findRecent')->willReturn(null);

        $ctrl     = $this->makeController(['modLog' => $modLog]);
        $response = $ctrl->index(new Request());
        $this->assertSame(200, $response->status);
    }

    public function testIndexReturns200WithEntries(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $entry              = new ModLog();
        $entry->user_id     = 2;
        $entry->forum_id    = 1;
        $entry->action      = 'delete';
        $entry->object_type = 'message';
        $entry->object_id   = 9;
        $entry->details     = 'Some Subject';
        $entry->time        = 1000;

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->method('findRecent')->willReturn([$entry]);

        $users = $this->createMock(UserMapper::class);
        $users->method('findByIds')->with([2])->willReturn([2 => $this->makeUser(2)]);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('loadMulti')->with([1])->willReturn([$this->makeForum(1)]);

        $ctrl     = $this->makeController(['modLog' => $modLog, 'users' => $users, 'forums' => $forums]);
        $response = $ctrl->index(new Request());
        $this->assertSame(200, $response->status);
    }
}

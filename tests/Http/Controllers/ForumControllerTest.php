<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\ForumController;
use Phorum\Http\Request;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\NewflagMapper;
use Phorum\Service\AnnouncementService;
use Phorum\Service\NewflagService;
use Phorum\Service\PermissionService;
use Phorum\Tests\Http\ControllerTestCase;

class ForumControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): ForumController
    {
        $perms = $deps['perms'] ?? $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn($deps['canRead'] ?? true);
        $perms->method('canPost')->willReturn(false);
        $perms->method('canModerate')->willReturn(false);

        $forums        = $deps['forums']        ?? $this->createMock(ForumMapper::class);
        $messages      = $deps['messages']      ?? $this->createMock(MessageMapper::class);
        $newflags      = $deps['newflags']      ?? $this->createMock(NewflagService::class);
        $announcements = $deps['announcements'] ?? $this->createMock(AnnouncementService::class);

        return new ForumController(
            config:        $this->makeConfig(),
            twig:          $this->makeTwig(),
            forums:        $forums,
            messages:      $messages,
            perms:         $perms,
            newflags:      $newflags,
            announcements: $announcements,
        );
    }

    public function testIndexReturns200(): void
    {
        $forums  = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->index(new Request());
        $this->assertSame(200, $response->status);
    }

    public function testShowReturns404ForUnknownForum(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->show(new Request(tokens: ['forum_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testShowRendersFolderView(): void
    {
        $folder = $this->makeForum(1, ['folder_flag' => 1, 'name' => 'General Discussion']);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($folder);
        $forums->method('find')->willReturn([]);

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->method('getLoader')->willReturn($this->createMock(\Twig\Loader\LoaderInterface::class));
        $twig->expects($this->once())->method('render')->with(
            'forum/folder.html.twig',
            $this->callback(fn(array $data) => ($data['folder'] ?? null) === $folder),
        )->willReturn('<html>ok</html>');

        $ctrl = new ForumController(
            config:        $this->makeConfig(),
            twig:          $twig,
            forums:        $forums,
            announcements: $this->createMock(AnnouncementService::class),
        );

        $response = $ctrl->show(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testShowReturns403WhenCannotRead(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $perms = $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn(false);
        $perms->method('canPost')->willReturn(false);
        $perms->method('canModerate')->willReturn(false);

        $ctrl     = $this->makeController(['forums' => $forums, 'perms' => $perms, 'canRead' => false]);
        $response = $ctrl->show(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(403, $response->status);
    }

    public function testShowReturns200WhenReadable(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findThreadsInForum')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->show(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testMarkForumReadReturns404ForUnknownForum(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->markForumRead(new Request(tokens: ['forum_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testMarkForumReadReturns404ForFolder(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['folder_flag' => 1]));

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->markForumRead(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(404, $response->status);
    }

    public function testMarkForumReadRedirectsAnonymousUser(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->markForumRead(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
    }

    public function testMarkForumReadRedirectsGetRequest(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));
        Auth::setUser($this->makeUser());

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->markForumRead($this->makeGetRequest(tokens: ['forum_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/1', $response->headers['Location']);
    }

    public function testMarkForumReadMarksAndRedirectsOnPost(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));
        Auth::setUser($this->makeUser());

        $newflags = $this->createMock(NewflagService::class);
        $newflags->expects($this->once())->method('markForumRead')->with(1, 1);

        $ctrl     = $this->makeController(['forums' => $forums, 'newflags' => $newflags]);
        $response = $ctrl->markForumRead($this->makePostRequest(tokens: ['forum_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/1', $response->headers['Location']);
    }

    public function testMarkForumReadReturns403WithBadCsrf(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));
        Auth::setUser($this->makeUser());

        $ctrl     = $this->makeController(['forums' => $forums]);
        $badPost  = new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
            tokens: ['forum_id' => '1'],
        );
        $response = $ctrl->markForumRead($badPost);
        $this->assertSame(403, $response->status);
    }
}

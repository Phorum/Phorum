<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Http\Controllers\Admin\ForumController;
use Phorum\Http\Request;
use Phorum\Mapper\ForumMapper;
use Phorum\Tests\Http\ControllerTestCase;

class AdminForumControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): ForumController
    {
        return new ForumController(
            config: $this->makeConfig(),
            twig:   $this->makeTwig(),
            forums: $deps['forums'] ?? $this->createMock(ForumMapper::class),
        );
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
        $response = $ctrl->edit(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    public function testDeleteRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->delete(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function testIndexReturns200WhenAdmin(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->index(new Request());
        $this->assertSame(200, $response->status);
    }

    public function testIndexScopesToRootLevelOnly(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->expects($this->once())->method('find')->with(
            $this->callback(fn(array $filter) => $filter === ['parent_id' => 0]),
            $this->anything(),
        )->willReturn([]);

        $ctrl = $this->makeController(['forums' => $forums]);
        $ctrl->index(new Request());
    }

    // -------------------------------------------------------------------------
    // folder
    // -------------------------------------------------------------------------

    public function testFolderReturns404ForUnknownId(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->folder(new Request(tokens: ['forum_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testFolderReturns404ForNonFolderForum(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['folder_flag' => 0]));

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->folder(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(404, $response->status);
    }

    public function testFolderReturns200AndScopesToItsChildren(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['folder_flag' => 1]));
        $forums->expects($this->once())->method('find')->with(
            $this->callback(fn(array $filter) => $filter === ['parent_id' => 1]),
            $this->anything(),
        )->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->folder(new Request(tokens: ['forum_id' => '1']));
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

    public function testCreatePostValidationErrorForEmptyName(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->create($this->makePostRequest(['name' => '']));
        $this->assertSame(200, $response->status);
    }

    public function testCreatePostSuccessRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);
        $forums->expects($this->once())->method('save');

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->create($this->makePostRequest(['name' => 'New Forum', 'active' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/forums', $response->headers['Location']);
    }

    public function testCreatePostSuccessRedirectsToFolderWhenParentSet(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);
        $forums->expects($this->once())->method('save');

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->create($this->makePostRequest(['name' => 'New Forum', 'active' => '1', 'vroot' => '5']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/forums/folder/5', $response->headers['Location']);
    }

    public function testCreateGetPreselectsParentFolderFromQueryParam(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->method('getLoader')->willReturn($this->createMock(\Twig\Loader\LoaderInterface::class));
        $twig->expects($this->once())->method('render')->with(
            'admin/forums/edit.html.twig',
            $this->callback(fn(array $data) => ($data['forum']->vroot ?? null) === 5
                && ($data['back_url'] ?? null) === '/admin/forums/folder/5'),
        )->willReturn('<html>ok</html>');

        $ctrl = new ForumController(config: $this->makeConfig(), twig: $twig, forums: $forums);

        $response = $ctrl->create($this->makeGetRequest(query: ['parent_id' => '5']));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // edit
    // -------------------------------------------------------------------------

    public function testEditReturns404WhenForumNotFound(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->edit(new Request(tokens: ['forum_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testEditGetReturns200(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->edit($this->makeGetRequest(tokens: ['forum_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testEditPostSuccessRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));
        $forums->method('find')->willReturn([]);
        $forums->expects($this->once())->method('save');

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   ['name' => 'Updated Name', 'active' => '1'],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/forums', $response->headers['Location']);
    }

    public function testEditPostSuccessRedirectsToFolderWhenParentSet(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['parent_id' => 5]));
        $forums->method('find')->willReturn([]);
        $forums->expects($this->once())->method('save');

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->edit($this->makePostRequest(
            post:   ['name' => 'Updated Name', 'active' => '1', 'vroot' => '5'],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/forums/folder/5', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    public function testDeleteReturns404WhenForumNotFound(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->delete(new Request(tokens: ['forum_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testDeleteGetReturnsConfirmForm(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->delete($this->makeGetRequest(tokens: ['forum_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testDeletePostDeactivatesAndRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forum = $this->makeForum(1);
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($forum);
        $forums->expects($this->once())->method('save');

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->delete($this->makePostRequest(tokens: ['forum_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/forums', $response->headers['Location']);
        $this->assertSame(0, $forum->active);
    }

    public function testDeletePostRedirectsToFolderWhenForumHasParent(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forum  = $this->makeForum(1, ['parent_id' => 5]);
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($forum);
        $forums->expects($this->once())->method('save');

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->delete($this->makePostRequest(tokens: ['forum_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/forums/folder/5', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // moveUp / moveDown
    // -------------------------------------------------------------------------

    public function testMoveUpRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->moveUp($this->makePostRequest(tokens: ['forum_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    public function testMoveUpReturns404ForGetRequest(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl     = $this->makeController();
        $response = $ctrl->moveUp(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(404, $response->status);
    }

    public function testMoveUpReturns403WithBadCsrf(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl    = $this->makeController();
        $badPost = new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
            tokens: ['forum_id' => '1'],
        );
        $response = $ctrl->moveUp($badPost);
        $this->assertSame(403, $response->status);
    }

    public function testMoveUpReturns404ForUnknownForum(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->moveUp($this->makePostRequest(tokens: ['forum_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testMoveUpNoOpForFirstSibling(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $a = $this->makeForum(1, ['name' => 'A']);
        $b = $this->makeForum(2, ['name' => 'B']);
        $c = $this->makeForum(3, ['name' => 'C']);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($a);
        $forums->method('find')->willReturn([$a, $b, $c]);
        $forums->expects($this->never())->method('save');

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->moveUp($this->makePostRequest(tokens: ['forum_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/forums', $response->headers['Location']);
    }

    public function testMoveDownNoOpForLastSibling(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $a = $this->makeForum(1, ['name' => 'A']);
        $b = $this->makeForum(2, ['name' => 'B']);
        $c = $this->makeForum(3, ['name' => 'C']);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($c);
        $forums->method('find')->willReturn([$a, $b, $c]);
        $forums->expects($this->never())->method('save');

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->moveDown($this->makePostRequest(tokens: ['forum_id' => '3']));
        $this->assertSame(302, $response->status);
    }

    public function testMoveUpSwapsWithPreviousSiblingAndRenumbers(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        // All three start tied at display_order 0, the common case (nothing
        // ever sets it explicitly until a move happens).
        $a = $this->makeForum(1, ['name' => 'A']);
        $b = $this->makeForum(2, ['name' => 'B']);
        $c = $this->makeForum(3, ['name' => 'C']);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($b);
        $forums->method('find')->willReturn([$a, $b, $c]);

        $saved = [];
        $forums->expects($this->exactly(2))->method('save')->willReturnCallback(function ($forum) use (&$saved) {
            $saved[$forum->forum_id] = $forum->display_order;
            return $forum;
        });

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->moveUp($this->makePostRequest(tokens: ['forum_id' => '2']));

        $this->assertSame(302, $response->status);
        // New order is B, A, C (0, 1, 2) — B's value doesn't change (already
        // 0), so only A and C actually get saved.
        $this->assertArrayNotHasKey(2, $saved);
        $this->assertSame(1, $saved[1]);
        $this->assertSame(2, $saved[3]);
    }

    public function testMoveUpRedirectsToFolderWhenForumHasParent(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $a = $this->makeForum(1, ['name' => 'A', 'parent_id' => 5]);
        $b = $this->makeForum(2, ['name' => 'B', 'parent_id' => 5]);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($b);
        $forums->method('find')->willReturn([$a, $b]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->moveUp($this->makePostRequest(tokens: ['forum_id' => '2']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/forums/folder/5', $response->headers['Location']);
    }
}

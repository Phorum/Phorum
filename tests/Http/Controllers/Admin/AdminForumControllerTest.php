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
}

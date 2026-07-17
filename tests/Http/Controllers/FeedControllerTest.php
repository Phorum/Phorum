<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\FeedStatus;
use Phorum\Http\Controllers\FeedController;
use Phorum\Http\Request;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\SettingMapper;
use Phorum\Service\FeedService;
use Phorum\Service\PermissionService;
use Phorum\Tests\Http\ControllerTestCase;

class FeedControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): FeedController
    {
        $perms = $deps['perms'] ?? $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn($deps['canRead'] ?? true);

        $feed = $deps['feed'] ?? $this->createMock(FeedService::class);
        $feed->method('siteWide')->willReturn('<rss/>');
        $feed->method('forumThreads')->willReturn('<rss/>');
        $feed->method('threadReplies')->willReturn('<rss/>');

        return new FeedController(
            config:   $this->makeConfig(),
            twig:     $this->makeTwig(),
            forums:   $deps['forums']   ?? $this->createMock(ForumMapper::class),
            messages: $deps['messages'] ?? $this->createMock(MessageMapper::class),
            perms:    $perms,
            feed:     $feed,
        );
    }

    /** ControllerTestCase::setUp()/tearDown() already reset FeedStatus to enabled=true between tests. */
    private function disableFeeds(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(false);
        FeedStatus::initialize($settings);
    }

    // -------------------------------------------------------------------------
    // enable_rss gate
    // -------------------------------------------------------------------------

    public function testSiteReturns404WhenFeedsDisabled(): void
    {
        $this->disableFeeds();

        $ctrl     = $this->makeController();
        $response = $ctrl->site(new Request(tokens: ['format' => 'rss']));
        $this->assertSame(404, $response->status);
    }

    public function testSiteReturns200WhenFeedsSettingIsUnset(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->site(new Request(tokens: ['format' => 'rss']));
        $this->assertSame(200, $response->status);
    }

    public function testForumReturns404WhenFeedsDisabled(): void
    {
        $this->disableFeeds();

        $ctrl     = $this->makeController();
        $response = $ctrl->forum(new Request(tokens: ['forum_id' => '1', 'format' => 'rss']));
        $this->assertSame(404, $response->status);
    }

    public function testThreadReturns404WhenFeedsDisabled(): void
    {
        $this->disableFeeds();

        $ctrl     = $this->makeController();
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '1', 'format' => 'rss']));
        $this->assertSame(404, $response->status);
    }

    // -------------------------------------------------------------------------
    // site()
    // -------------------------------------------------------------------------

    public function testSiteUsesFindRecentInForumsNotFindRecent(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([$this->makeForum(1)]);

        $messages = $this->createMock(MessageMapper::class);
        $messages->expects($this->once())->method('findRecentInForums')->willReturn([]);
        $messages->expects($this->never())->method('findRecent');

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->site(new Request(tokens: ['format' => 'rss']));
        $this->assertSame(200, $response->status);
    }

    public function testSiteReturnsCorrectContentTypeForAtom(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->site(new Request(tokens: ['format' => 'atom']));
        $this->assertSame('application/atom+xml; charset=UTF-8', $response->headers['Content-Type']);
    }

    public function testSiteReturnsCorrectContentTypeForRss(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->site(new Request(tokens: ['format' => 'rss']));
        $this->assertSame('application/rss+xml; charset=UTF-8', $response->headers['Content-Type']);
    }

    public function testSiteReturnsCorrectContentTypeForJson(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->site(new Request(tokens: ['format' => 'json']));
        $this->assertSame('application/feed+json; charset=UTF-8', $response->headers['Content-Type']);
    }

    // -------------------------------------------------------------------------
    // forum()
    // -------------------------------------------------------------------------

    public function testForumReturns404ForUnknownForum(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->forum(new Request(tokens: ['forum_id' => '99', 'format' => 'rss']));
        $this->assertSame(404, $response->status);
    }

    public function testForumReturns404ForFolder(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['folder_flag' => 1]));

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->forum(new Request(tokens: ['forum_id' => '1', 'format' => 'rss']));
        $this->assertSame(404, $response->status);
    }

    public function testForumReturns403WhenCannotRead(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $ctrl     = $this->makeController(['forums' => $forums, 'canRead' => false]);
        $response = $ctrl->forum(new Request(tokens: ['forum_id' => '1', 'format' => 'rss']));
        $this->assertSame(403, $response->status);
    }

    public function testForumReturns200OnHappyPath(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findThreadsInForum')->willReturn([]);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->forum(new Request(tokens: ['forum_id' => '1', 'format' => 'rss']));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // thread()
    // -------------------------------------------------------------------------

    public function testThreadReturns404ForUnknownForum(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '99', 'thread_id' => '1', 'format' => 'rss']));
        $this->assertSame(404, $response->status);
    }

    public function testThreadReturns403WhenCannotRead(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $ctrl     = $this->makeController(['forums' => $forums, 'canRead' => false]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '1', 'format' => 'rss']));
        $this->assertSame(403, $response->status);
    }

    public function testThreadReturns404WhenThreadNotFound(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByThread')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '1', 'format' => 'rss']));
        $this->assertSame(404, $response->status);
    }

    public function testThreadReturns404WhenRootMessageMissingFromResults(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByThread')->willReturn([$this->makeMessage(2, 1, 1)]);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '1', 'format' => 'rss']));
        $this->assertSame(404, $response->status);
    }

    public function testThreadReturns200OnHappyPath(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $root     = $this->makeMessage(1, 1, 1);
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByThread')->willReturn([$root]);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '1', 'format' => 'rss']));
        $this->assertSame(200, $response->status);
    }
}

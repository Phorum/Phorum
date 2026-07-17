<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Core\Config;
use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\ForumMapper;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Service\FeedService;
use PHPUnit\Framework\TestCase;

class FeedServiceTest extends TestCase
{
    protected function setUp(): void
    {
        HookDispatcher::reset();
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    private function makeConfig(): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnMap([
            ['base_url', '', 'https://forum.example.com'],
        ]);
        return $config;
    }

    private function makeForum(int $id, string $name): Forum
    {
        $forum           = new Forum();
        $forum->forum_id = $id;
        $forum->name     = $name;
        return $forum;
    }

    private function makeMessage(int $id, string $subject, string $author, array $override = []): Message
    {
        $msg              = new Message();
        $msg->message_id  = $id;
        $msg->forum_id    = $override['forum_id']    ?? 1;
        $msg->thread      = $override['thread']       ?? $id;
        $msg->subject     = $subject;
        $msg->author      = $author;
        $msg->user_id     = $override['user_id']      ?? 1;
        $msg->status      = 2;
        $msg->datestamp   = $override['datestamp']    ?? 1700000000;
        $msg->modifystamp = $override['modifystamp']  ?? 1700000000;
        $msg->body        = $override['body']         ?? 'Hello world';
        $msg->thread_count = $override['thread_count'] ?? 1;
        return $msg;
    }

    private function assertWellFormedXml(string $xml): void
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $ok  = $doc->loadXML($xml);
        $this->assertTrue($ok, 'Expected well-formed XML, got: ' . $xml);
    }

    // -------------------------------------------------------------------------
    // siteWide
    // -------------------------------------------------------------------------

    public function testSiteWideRssIsWellFormedAndHasExpectedShape(): void
    {
        $forums  = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, 'General'));

        $messages = [$this->makeMessage(1, 'Hello', 'alice')];
        $service  = new FeedService($this->makeConfig());
        $xml      = $service->siteWide($messages, $forums, 'rss', 'Test Forum');

        $this->assertWellFormedXml($xml);
        $this->assertStringContainsString('<rss version="2.0"', $xml);
        $this->assertStringContainsString('xmlns:dc="http://purl.org/dc/elements/1.1/"', $xml);
        $this->assertStringContainsString('<title>Test Forum</title>', $xml);
        $this->assertStringContainsString('<dc:creator>alice</dc:creator>', $xml);
        $this->assertStringContainsString('<category>General</category>', $xml);
        $this->assertStringContainsString('<pubDate>' . date(DATE_RSS, 1700000000) . '</pubDate>', $xml);
        $this->assertStringContainsString('<![CDATA[', $xml);
        $this->assertStringContainsString('https://forum.example.com/forum/1/thread/1', $xml);
    }

    public function testSiteWideAtomIsWellFormedAndHasExpectedShape(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, 'General'));

        $messages = [$this->makeMessage(1, 'Hello', 'alice')];
        $service  = new FeedService($this->makeConfig());
        $xml      = $service->siteWide($messages, $forums, 'atom', 'Test Forum');

        $this->assertWellFormedXml($xml);
        $this->assertStringContainsString('<feed xmlns="http://www.w3.org/2005/Atom">', $xml);
        $this->assertStringContainsString('<published>' . date(DATE_ATOM, 1700000000) . '</published>', $xml);
        $this->assertStringContainsString('<summary type="html">', $xml);
        $this->assertStringContainsString('<![CDATA[', $xml);
    }

    public function testSiteWideItemCountMatchesInputCount(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, 'General'));

        $messages = [
            $this->makeMessage(1, 'One', 'alice'),
            $this->makeMessage(2, 'Two', 'bob'),
            $this->makeMessage(3, 'Three', 'carol'),
        ];
        $service = new FeedService($this->makeConfig());
        $xml     = $service->siteWide($messages, $forums, 'rss', 'Test Forum');

        $this->assertSame(3, substr_count($xml, '<item>'));
    }

    // -------------------------------------------------------------------------
    // forumThreads
    // -------------------------------------------------------------------------

    public function testForumThreadsAppendsReplyCountToTitle(): void
    {
        $forum   = $this->makeForum(1, 'General');
        $thread  = $this->makeMessage(1, 'Hello', 'alice', ['thread_count' => 4]);
        $service = new FeedService($this->makeConfig());

        $xml = $service->forumThreads($forum, [$thread], 'rss', 'Test Forum');

        $this->assertWellFormedXml($xml);
        $this->assertStringContainsString('<title>Hello (3 replies)</title>', $xml);
    }

    public function testForumThreadsOmitsReplyCountForSingleMessageThread(): void
    {
        $forum   = $this->makeForum(1, 'General');
        $thread  = $this->makeMessage(1, 'Hello', 'alice', ['thread_count' => 1]);
        $service = new FeedService($this->makeConfig());

        $xml = $service->forumThreads($forum, [$thread], 'rss', 'Test Forum');

        $this->assertStringContainsString('<title>Hello</title>', $xml);
        $this->assertStringNotContainsString('replies', $xml);
    }

    // -------------------------------------------------------------------------
    // threadReplies
    // -------------------------------------------------------------------------

    public function testThreadRepliesUsesRootSubjectForEveryItem(): void
    {
        $forum = $this->makeForum(1, 'General');
        $root  = $this->makeMessage(1, 'Root Subject', 'alice');
        $reply = $this->makeMessage(2, 'Ignored', 'bob', ['thread' => 1]);

        $service = new FeedService($this->makeConfig());
        $xml     = $service->threadReplies($forum, $root, [$root, $reply], 'atom', 'Test Forum');

        $this->assertWellFormedXml($xml);
        // 1 feed-level <title> ("{siteName} — {subject}") + 1 per entry (2 entries) = 3.
        $this->assertSame(3, substr_count($xml, 'Root Subject'));
        $this->assertStringNotContainsString('Ignored', $xml);
    }

    // -------------------------------------------------------------------------
    // CDATA guard
    // -------------------------------------------------------------------------

    public function testBodyContainingCdataTerminatorDoesNotBreakXml(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, 'General'));

        $messages = [$this->makeMessage(1, 'Hello', 'alice', ['body' => 'a ]]> b'])];
        $service  = new FeedService($this->makeConfig());
        $xml      = $service->siteWide($messages, $forums, 'rss', 'Test Forum');

        $this->assertWellFormedXml($xml);
    }

    // -------------------------------------------------------------------------
    // JSON Feed 1.1
    // -------------------------------------------------------------------------

    public function testSiteWideJsonFeedIsValidJsonWithExpectedShape(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, 'General'));

        $messages = [$this->makeMessage(1, 'Hello', 'alice')];
        $service  = new FeedService($this->makeConfig());
        $json     = $service->siteWide($messages, $forums, 'json', 'Test Forum');

        $data = json_decode($json, true);
        $this->assertIsArray($data, 'Expected valid JSON, got: ' . $json);

        $this->assertSame('https://jsonfeed.org/version/1.1', $data['version']);
        $this->assertSame('Test Forum', $data['title']);
        $this->assertSame('https://forum.example.com/', $data['home_page_url']);
        $this->assertSame('https://forum.example.com/feed.json', $data['feed_url']);
        $this->assertCount(1, $data['items']);

        $item = $data['items'][0];
        $this->assertSame('https://forum.example.com/forum/1/thread/1#msg-1', $item['id']);
        $this->assertSame($item['id'], $item['url']);
        $this->assertSame('Hello', $item['title']);
        $this->assertSame('Hello world', $item['content_html']);
        $this->assertSame(date(DATE_ATOM, 1700000000), $item['date_published']);
        $this->assertSame([['name' => 'alice']], $item['authors']);
        $this->assertSame(['General'], $item['tags']);
    }

    public function testJsonFeedItemCountMatchesInputCount(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, 'General'));

        $messages = [
            $this->makeMessage(1, 'One', 'alice'),
            $this->makeMessage(2, 'Two', 'bob'),
        ];
        $service = new FeedService($this->makeConfig());
        $json    = $service->siteWide($messages, $forums, 'json', 'Test Forum');

        $data = json_decode($json, true);
        $this->assertCount(2, $data['items']);
    }

    public function testJsonFeedBodyContainingSpecialCharsDoesNotBreakJson(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, 'General'));

        $messages = [$this->makeMessage(1, 'Hello "world"', 'alice', ['body' => 'a </script> & "quotes" b'])];
        $service  = new FeedService($this->makeConfig());
        $json     = $service->siteWide($messages, $forums, 'json', 'Test Forum');

        $data = json_decode($json, true);
        $this->assertIsArray($data, 'Expected valid JSON, got: ' . $json);
        $this->assertSame('Hello "world"', $data['items'][0]['title']);
    }

    public function testForumThreadsJsonFeedUsesForumUrlAsHomePage(): void
    {
        $forum   = $this->makeForum(1, 'General');
        $thread  = $this->makeMessage(1, 'Hello', 'alice');
        $service = new FeedService($this->makeConfig());

        $json = $service->forumThreads($forum, [$thread], 'json', 'Test Forum');
        $data = json_decode($json, true);

        $this->assertSame('https://forum.example.com/forum/1', $data['home_page_url']);
        $this->assertSame('https://forum.example.com/forum/1/feed.json', $data['feed_url']);
    }
}

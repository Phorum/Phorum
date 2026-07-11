<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use DealNews\SchemaOrg\Type\CollectionPage;
use DealNews\SchemaOrg\Type\DiscussionForumPosting;
use Phorum\Core\Config;
use Phorum\Hook\HookDispatcher;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Service\SchemaOrgService;
use PHPUnit\Framework\TestCase;

class SchemaOrgServiceTest extends TestCase
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
            ['site_name', 'Phorum', 'Test Forum'],
        ]);
        return $config;
    }

    private function makeForum(int $id, string $name, int $folderFlag = 0): Forum
    {
        $forum             = new Forum();
        $forum->forum_id   = $id;
        $forum->name       = $name;
        $forum->folder_flag = $folderFlag;
        return $forum;
    }

    private function makeMessage(int $id, string $subject, string $author, int $userId = 1): Message
    {
        $msg              = new Message();
        $msg->message_id  = $id;
        $msg->thread      = $id;
        $msg->subject     = $subject;
        $msg->author      = $author;
        $msg->user_id     = $userId;
        $msg->status      = 2;
        $msg->datestamp   = 1700000000;
        $msg->modifystamp = 1700000000;
        $msg->body        = 'Hello world';
        return $msg;
    }

    // -------------------------------------------------------------------------
    // forumIndex
    // -------------------------------------------------------------------------

    public function testForumIndexSkipsFolders(): void
    {
        $service = new SchemaOrgService($this->makeConfig());
        $forums  = [
            $this->makeForum(1, 'A Folder', folderFlag: 1),
            $this->makeForum(2, 'General'),
        ];

        [$page] = $service->forumIndex($forums, 'Test Forum');

        $this->assertInstanceOf(CollectionPage::class, $page);
        $this->assertSame(1, $page->mainEntity->numberOfItems);
        $this->assertSame('https://forum.example.com/forum/2', $page->mainEntity->itemListElement[0]->item->id);
    }

    public function testForumIndexUsesAbsoluteUrls(): void
    {
        $service = new SchemaOrgService($this->makeConfig());
        [$page]  = $service->forumIndex([], 'Test Forum');

        $this->assertSame('https://forum.example.com/', $page->id);
        $this->assertSame('Test Forum', $page->name);
    }

    // -------------------------------------------------------------------------
    // forumShow
    // -------------------------------------------------------------------------

    public function testForumShowSetsBreadcrumbAndCommentCount(): void
    {
        $service = new SchemaOrgService($this->makeConfig());
        $forum   = $this->makeForum(2, 'General');

        $thread              = $this->makeMessage(10, 'Hello', 'jane');
        $thread->thread_count = 5;

        [$page] = $service->forumShow($forum, [$thread], 'Test Forum');

        $this->assertNotNull($page->breadcrumb);
        $this->assertCount(2, $page->breadcrumb->itemListElement);
        $this->assertSame('General', $page->breadcrumb->itemListElement[1]->item->name);

        $posting = $page->mainEntity->itemListElement[0]->item;
        $this->assertInstanceOf(DiscussionForumPosting::class, $posting);
        $this->assertSame(4, $posting->commentCount);
        $this->assertSame('https://forum.example.com/forum/2/thread/10', $posting->id);
    }

    // -------------------------------------------------------------------------
    // thread
    // -------------------------------------------------------------------------

    public function testThreadExcludesRootAndUnapprovedRepliesFromComments(): void
    {
        $service = new SchemaOrgService($this->makeConfig());
        $forum   = $this->makeForum(2, 'General');

        $root               = $this->makeMessage(10, 'Hello', 'jane');
        $root->thread_count = 3;

        $reply1 = $this->makeMessage(11, 'Re: Hello', 'joe');
        $reply1->datestamp = 1700000100;

        $unapproved         = $this->makeMessage(12, 'Re: Hello', 'spammer');
        $unapproved->status = 0;

        [$posting, $breadcrumb] = $service->thread($forum, $root, [$root, $reply1, $unapproved], 'Test Forum');

        $this->assertInstanceOf(DiscussionForumPosting::class, $posting);
        $this->assertCount(1, $posting->comment);
        $this->assertSame('joe', $posting->comment[0]->author->name);
        $this->assertSame(2, $posting->commentCount);
        $this->assertCount(3, $breadcrumb->itemListElement);
    }

    public function testThreadCapsEmbeddedCommentsButKeepsTrueCount(): void
    {
        $service = new SchemaOrgService($this->makeConfig());
        $forum   = $this->makeForum(2, 'General');

        $root               = $this->makeMessage(1, 'Hello', 'jane');
        $root->thread_count = 61;

        $messages = [$root];
        for ($i = 2; $i <= 61; $i++) {
            $reply             = $this->makeMessage($i, 'Re: Hello', "user{$i}");
            $reply->datestamp  = 1700000000 + $i;
            $messages[]        = $reply;
        }

        [$posting] = $service->thread($forum, $root, $messages, 'Test Forum');

        $this->assertCount(50, $posting->comment);
        $this->assertSame(60, $posting->commentCount);
    }

    public function testThreadPlainTextStripsMarkup(): void
    {
        $service = new SchemaOrgService($this->makeConfig());
        $forum   = $this->makeForum(2, 'General');

        $root       = $this->makeMessage(1, 'Hello', 'jane');
        $root->body = "Hello <b>World</b>\nLine2";
        $root->meta = null;

        [$posting] = $service->thread($forum, $root, [$root], 'Test Forum');

        $this->assertStringNotContainsString('<b>', $posting->text);
        $this->assertStringContainsString('World', $posting->text);
    }
}

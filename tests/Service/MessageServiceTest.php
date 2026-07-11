<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\MessageTrackingMapper;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Model\MessageMeta;
use Phorum\Model\User;
use Phorum\Service\MessageService;
use PHPUnit\Framework\TestCase;

class MessageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        HookDispatcher::reset();
        require_once dirname(__DIR__, 2) . '/src/Hook/functions.php';
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    private function makeForum(int $moderation = 0): Forum
    {
        $f              = new Forum();
        $f->forum_id    = 10;
        $f->moderation  = $moderation;
        return $f;
    }

    private function makeUser(): User
    {
        $u               = new User();
        $u->user_id      = 5;
        $u->username     = 'alice';
        $u->display_name = 'Alice';
        $u->email        = 'alice@example.com';
        return $u;
    }

    /** Build MessageMapper mock that returns the saved object as-is. */
    private function makeSavingMapper(): MessageMapper
    {
        $mapper = $this->createMock(MessageMapper::class);
        $mapper->method('save')->willReturnArgument(0);
        $mapper->method('load')->willReturn(null);
        return $mapper;
    }

    // -------------------------------------------------------------------------
    // post() — new thread
    // -------------------------------------------------------------------------

    public function testPostNewThreadCreatesApprovedMessageInNonModeratedForum(): void
    {
        $msgMapper   = $this->makeSavingMapper();
        $forumMapper = $this->createMock(ForumMapper::class);
        $svc         = new MessageService($msgMapper, $forumMapper);

        $msg = $svc->post($this->makeForum(moderation: 0), $this->makeUser(), 'Hello', 'Body');

        $this->assertSame(MessageMapper::STATUS_APPROVED, $msg->status);
        $this->assertSame(10, $msg->forum_id);
        $this->assertSame('Hello', $msg->subject);
        $this->assertSame('Body', $msg->body);
        $this->assertSame('Alice', $msg->author);
    }

    public function testPostNewThreadCreatesUnapprovedMessageInModeratedForum(): void
    {
        $msgMapper   = $this->makeSavingMapper();
        $forumMapper = $this->createMock(ForumMapper::class);
        $svc         = new MessageService($msgMapper, $forumMapper);

        $msg = $svc->post($this->makeForum(moderation: 1), $this->makeUser(), 'Subject', 'Body');

        $this->assertSame(MessageMapper::STATUS_UNAPPROVED, $msg->status);
    }

    public function testPostNewThreadHasZeroParentId(): void
    {
        $msgMapper   = $this->makeSavingMapper();
        $forumMapper = $this->createMock(ForumMapper::class);
        $svc         = new MessageService($msgMapper, $forumMapper);

        $msg = $svc->post($this->makeForum(), $this->makeUser(), 'S', 'B');
        $this->assertSame(0, $msg->parent_id);
    }

    public function testPostNewThreadSetsMetaFormatMarkdown(): void
    {
        $msgMapper   = $this->makeSavingMapper();
        $forumMapper = $this->createMock(ForumMapper::class);
        $svc         = new MessageService($msgMapper, $forumMapper);

        $msg    = $svc->post($this->makeForum(), $this->makeUser(), 'S', 'B');
        $format = MessageMeta::decode($msg->meta)->format();
        $this->assertSame('markdown', $format);
    }

    // -------------------------------------------------------------------------
    // post() — reply
    // -------------------------------------------------------------------------

    public function testPostReplyUsesParentThread(): void
    {
        $parent           = new Message();
        $parent->message_id = 100;
        $parent->thread     = 100;

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->with(100)->willReturn($parent);
        $msgMapper->method('save')->willReturnArgument(0);

        $forumMapper = $this->createMock(ForumMapper::class);
        $svc         = new MessageService($msgMapper, $forumMapper);

        $msg = $svc->post($this->makeForum(), $this->makeUser(), 'Re', 'Reply body', parentId: 100);

        $this->assertSame(100, $msg->thread);
        $this->assertSame(100, $msg->parent_id);
    }

    public function testPostReplyThrowsOnMissingParent(): void
    {
        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn(null);

        $svc = new MessageService($msgMapper, $this->createMock(ForumMapper::class));

        $this->expectException(\InvalidArgumentException::class);
        $svc->post($this->makeForum(), $this->makeUser(), 'S', 'B', parentId: 999);
    }

    // -------------------------------------------------------------------------
    // post() — hook interaction
    // -------------------------------------------------------------------------

    public function testCheckPostHookCanModifyMessage(): void
    {
        HookDispatcher::getInstance()->register('check_post', function (Message $m): Message {
            $m->subject = 'hooked';
            return $m;
        });

        $msgMapper   = $this->makeSavingMapper();
        $forumMapper = $this->createMock(ForumMapper::class);
        $svc         = new MessageService($msgMapper, $forumMapper);

        $msg = $svc->post($this->makeForum(), $this->makeUser(), 'original', 'body');
        $this->assertSame('hooked', $msg->subject);
    }

    // -------------------------------------------------------------------------
    // edit()
    // -------------------------------------------------------------------------

    public function testEditUpdatesSubjectAndBody(): void
    {
        $original         = new Message();
        $original->meta   = MessageMeta::fromArray([])->encode();
        $original->subject = 'old subject';
        $original->body    = 'old body';

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('save')->willReturnArgument(0);
        $svc = new MessageService($msgMapper, $this->createMock(ForumMapper::class));

        $edited = $svc->edit($original, 'new subject', 'new body');

        $this->assertSame('new subject', $edited->subject);
        $this->assertSame('new body', $edited->body);
    }

    public function testEditIncrementsEditCount(): void
    {
        $msg      = new Message();
        $msg->meta = MessageMeta::fromArray(['edit_count' => 2])->encode();

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('save')->willReturnArgument(0);
        $svc = new MessageService($msgMapper, $this->createMock(ForumMapper::class));

        $edited    = $svc->edit($msg, 's', 'b');
        $editCount = MessageMeta::decode($edited->meta)->editCount();
        $this->assertSame(3, $editCount);
    }

    public function testEditWithTrackerCallsRecord(): void
    {
        $msg           = new Message();
        $msg->message_id = 7;
        $msg->meta      = MessageMeta::fromArray([])->encode();
        $msg->body      = 'original';
        $msg->subject   = 'original subject';

        $tracker = $this->createMock(MessageTrackingMapper::class);
        $tracker->expects($this->once())->method('record')
            ->with(7, 99, 'original', 'original subject');

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('save')->willReturnArgument(0);
        $svc = new MessageService($msgMapper, $this->createMock(ForumMapper::class));

        $svc->edit($msg, 'new subject', 'new body', editorUserId: 99, tracker: $tracker);
    }

    public function testEditWithoutTrackerDoesNotRecord(): void
    {
        $msg      = new Message();
        $msg->meta = MessageMeta::fromArray([])->encode();

        $tracker = $this->createMock(MessageTrackingMapper::class);
        $tracker->expects($this->never())->method('record');

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('save')->willReturnArgument(0);
        $svc = new MessageService($msgMapper, $this->createMock(ForumMapper::class));

        $svc->edit($msg, 's', 'b'); // tracker = null
    }
}

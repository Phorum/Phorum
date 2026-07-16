<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\NewflagMapper;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Model\Message;
use Phorum\Service\ModerationService;
use PHPUnit\Framework\TestCase;

class ModerationServiceTest extends TestCase
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

    private function makeMessage(int $id, int $parentId, int $forumId = 1, int $thread = 0): Message
    {
        $m             = new Message();
        $m->message_id = $id;
        $m->parent_id  = $parentId;
        $m->forum_id   = $forumId;
        $m->thread     = $thread ?: $id;
        $m->status     = MessageMapper::STATUS_APPROVED;
        return $m;
    }

    // -------------------------------------------------------------------------
    // deleteMessage()
    // -------------------------------------------------------------------------

    public function testDeleteMessageOnReplyDeletesSingleMessage(): void
    {
        $reply = $this->makeMessage(2, 1, thread: 1);

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->with(2)->willReturn($reply);
        $msgMapper->expects($this->once())->method('setStatus')
            ->with(2, MessageMapper::STATUS_DELETED);

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->deleteMessage(2);
    }

    public function testDeleteMessageOnRootDelegatesDeleteThread(): void
    {
        $root = $this->makeMessage(1, 0, thread: 1);

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn($root);
        $msgMapper->method('findIdsByThread')->willReturn([1]);
        $msgMapper->expects($this->once())->method('setStatusForThread')
            ->with(1, MessageMapper::STATUS_DELETED);

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->deleteMessage(1);
    }

    public function testDeleteMessageDoesNothingForMissingId(): void
    {
        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn(null);
        $msgMapper->expects($this->never())->method('setStatus');

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->deleteMessage(999);
    }

    // -------------------------------------------------------------------------
    // deleteThread()
    // -------------------------------------------------------------------------

    public function testDeleteThreadCallsSetStatusForThread(): void
    {
        $root = $this->makeMessage(1, 0, thread: 1);

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn($root);
        $msgMapper->method('findIdsByThread')->willReturn([1, 2, 3]);
        $msgMapper->expects($this->once())->method('setStatusForThread')
            ->with(1, MessageMapper::STATUS_DELETED);

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->deleteThread(1);
    }

    public function testDeleteThreadFiresDeleteHookWithAllMessageIds(): void
    {
        $root = $this->makeMessage(1, 0, thread: 1);

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn($root);
        $msgMapper->method('findIdsByThread')->willReturn([1, 2, 3]);

        $deleted = null;
        HookDispatcher::getInstance()->register('delete', function (array $ids) use (&$deleted) {
            $deleted = $ids;
            return $ids;
        });

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->deleteThread(1);

        $this->assertSame([1, 2, 3], $deleted);
    }

    public function testDeleteThreadDoesNothingForMissingId(): void
    {
        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn(null);
        $msgMapper->expects($this->never())->method('setStatusForThread');

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->deleteThread(999);
    }

    // -------------------------------------------------------------------------
    // approveMessage()
    // -------------------------------------------------------------------------

    public function testApproveMessageSetsApprovedStatus(): void
    {
        $msg = $this->makeMessage(5, 0);
        $msg->status = MessageMapper::STATUS_UNAPPROVED;

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn($msg);
        $msgMapper->expects($this->once())->method('setStatus')
            ->with(5, MessageMapper::STATUS_APPROVED);

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->approveMessage(5);
    }

    public function testApproveMessageFiresAfterApproveHook(): void
    {
        $msg = $this->makeMessage(5, 0);

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn($msg);

        $fired = false;
        HookDispatcher::getInstance()->register('after_approve', function ($m) use (&$fired) {
            $fired = true;
            return $m;
        });

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->approveMessage(5);

        $this->assertTrue($fired);
    }

    // -------------------------------------------------------------------------
    // closeThread() / openThread()
    // -------------------------------------------------------------------------

    public function testCloseThreadSetsFlagOne(): void
    {
        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->expects($this->once())->method('setClosedForThread')->with(7, 1);

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->closeThread(7);
    }

    public function testOpenThreadSetsFlagZero(): void
    {
        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->expects($this->once())->method('setClosedForThread')->with(7, 0);

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->openThread(7);
    }

    // -------------------------------------------------------------------------
    // moveThread()
    // -------------------------------------------------------------------------

    public function testMoveThreadUpdatesForum(): void
    {
        $root           = new Message();
        $root->message_id = 3;
        $root->parent_id  = 0;
        $root->thread     = 3;
        $root->forum_id   = 1;

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn($root);
        $msgMapper->expects($this->once())->method('setForumForThread')->with(3, 2);

        $forumMapper = $this->createMock(ForumMapper::class);
        $forumMapper->expects($this->exactly(2))->method('recalcStats');

        $svc = new ModerationService($msgMapper, $forumMapper);
        $svc->moveThread(3, 2);
    }

    public function testMoveThreadNoOpWhenSameForum(): void
    {
        $root           = new Message();
        $root->message_id = 3;
        $root->parent_id  = 0;
        $root->forum_id   = 2;

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn($root);
        $msgMapper->expects($this->never())->method('setForumForThread');

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->moveThread(3, 2);
    }

    // -------------------------------------------------------------------------
    // stickyThread()
    // -------------------------------------------------------------------------

    public function testStickyThreadSetsStickySort(): void
    {
        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->expects($this->once())->method('setSortForThread')
            ->with(4, MessageMapper::SORT_STICKY);

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->stickyThread(4, true);
    }

    public function testUnstickyThreadSetsDefaultSort(): void
    {
        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->expects($this->once())->method('setSortForThread')
            ->with(4, MessageMapper::SORT_DEFAULT);

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->stickyThread(4, false);
    }

    // -------------------------------------------------------------------------
    // mergeThread()
    // -------------------------------------------------------------------------

    private function makeLoadMap(Message $source, Message $target): \Closure
    {
        return fn(int $id) => match ($id) {
            $source->message_id => $source,
            $target->message_id => $target,
            default => null,
        };
    }

    public function testMergeThreadUpdatesMessagesAndRecalculatesStatsSameForum(): void
    {
        $source = $this->makeMessage(1, 0, forumId: 1, thread: 1);
        $target = $this->makeMessage(2, 0, forumId: 1, thread: 2);

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturnCallback($this->makeLoadMap($source, $target));
        $msgMapper->expects($this->once())->method('mergeThread')->with(1, 2, 1);
        $msgMapper->expects($this->once())->method('recalcThreadStats')->with(2);

        $forumMapper = $this->createMock(ForumMapper::class);
        $forumMapper->expects($this->once())->method('recalcStats')->with(1);

        $svc = new ModerationService($msgMapper, $forumMapper);
        $this->assertTrue($svc->mergeThread(1, 2));
    }

    public function testMergeThreadRecalculatesBothForumsWhenDifferent(): void
    {
        $source = $this->makeMessage(1, 0, forumId: 1, thread: 1);
        $target = $this->makeMessage(2, 0, forumId: 9, thread: 2);

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturnCallback($this->makeLoadMap($source, $target));
        $msgMapper->expects($this->once())->method('mergeThread')->with(1, 2, 9);

        $forumMapper = $this->createMock(ForumMapper::class);
        $forumMapper->expects($this->exactly(2))->method('recalcStats')
            ->with($this->logicalOr(1, 9));

        $svc = new ModerationService($msgMapper, $forumMapper);
        $this->assertTrue($svc->mergeThread(1, 2));
    }

    public function testMergeThreadRekeysNewflagsToTargetForumWhenForumsDiffer(): void
    {
        $source = $this->makeMessage(1, 0, forumId: 4, thread: 1);
        $target = $this->makeMessage(2, 0, forumId: 9, thread: 2);

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturnCallback($this->makeLoadMap($source, $target));
        $msgMapper->method('findIdsByThread')->with(1)->willReturn([1, 5, 6]);

        $newflags = $this->createMock(NewflagMapper::class);
        $newflags->expects($this->once())->method('moveForumForMessages')->with(4, 9, [1, 5, 6]);

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class), null, null, $newflags);
        $this->assertTrue($svc->mergeThread(1, 2));
    }

    public function testMergeThreadDoesNotRekeyNewflagsWhenSameForum(): void
    {
        $source = $this->makeMessage(1, 0, forumId: 1, thread: 1);
        $target = $this->makeMessage(2, 0, forumId: 1, thread: 2);

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturnCallback($this->makeLoadMap($source, $target));

        $newflags = $this->createMock(NewflagMapper::class);
        $newflags->expects($this->never())->method('moveForumForMessages');

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class), null, null, $newflags);
        $this->assertTrue($svc->mergeThread(1, 2));
    }

    public function testMergeThreadSyncsClosedFlagToTargetState(): void
    {
        $source          = $this->makeMessage(1, 0, forumId: 1, thread: 1);
        $target          = $this->makeMessage(2, 0, forumId: 1, thread: 2);
        $target->closed  = 1;

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturnCallback($this->makeLoadMap($source, $target));
        $msgMapper->expects($this->once())->method('setClosedForThread')->with(2, 1);

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $this->assertTrue($svc->mergeThread(1, 2));
    }

    public function testMergeThreadDeletesSourceSubscriptions(): void
    {
        $source = $this->makeMessage(1, 0, forumId: 1, thread: 1);
        $target = $this->makeMessage(2, 0, forumId: 1, thread: 2);

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturnCallback($this->makeLoadMap($source, $target));

        $subscribers = $this->createMock(SubscriberMapper::class);
        $subscribers->expects($this->once())->method('deleteForThread')->with(1, 1);

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class), null, $subscribers);
        $svc->mergeThread(1, 2);
    }

    public function testMergeThreadFiresAfterMergeHook(): void
    {
        $source = $this->makeMessage(1, 0, forumId: 1, thread: 1);
        $target = $this->makeMessage(2, 0, forumId: 1, thread: 2);

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturnCallback($this->makeLoadMap($source, $target));

        $fired = null;
        HookDispatcher::getInstance()->register('after_merge', function ($ids) use (&$fired) {
            $fired = $ids;
            return $ids;
        });

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $svc->mergeThread(1, 2);

        $this->assertSame([1, 2], $fired);
    }

    public function testMergeThreadNoOpWhenSameThread(): void
    {
        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->expects($this->never())->method('load');
        $msgMapper->expects($this->never())->method('mergeThread');

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $this->assertFalse($svc->mergeThread(5, 5));
    }

    public function testMergeThreadNoOpWhenSourceMissing(): void
    {
        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn(null);
        $msgMapper->expects($this->never())->method('mergeThread');

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $this->assertFalse($svc->mergeThread(1, 2));
    }

    public function testMergeThreadNoOpWhenSourceNotRoot(): void
    {
        $source = $this->makeMessage(1, 99, thread: 1); // parent_id != 0

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturn($source);
        $msgMapper->expects($this->never())->method('mergeThread');

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $this->assertFalse($svc->mergeThread(1, 2));
    }

    public function testMergeThreadNoOpWhenTargetNotRoot(): void
    {
        $source = $this->makeMessage(1, 0, thread: 1);
        $target = $this->makeMessage(2, 99, thread: 2); // parent_id != 0

        $msgMapper = $this->createMock(MessageMapper::class);
        $msgMapper->method('load')->willReturnCallback($this->makeLoadMap($source, $target));
        $msgMapper->expects($this->never())->method('mergeThread');

        $svc = new ModerationService($msgMapper, $this->createMock(ForumMapper::class));
        $this->assertFalse($svc->mergeThread(1, 2));
    }
}

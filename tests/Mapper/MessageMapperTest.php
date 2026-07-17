<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\MessageMapper;
use Phorum\Model\Message;

class MessageMapperTest extends MapperTestCase
{
    private function makeMapper(): MessageMapper
    {
        return new class extends MessageMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    private function seedMessage(array $override = []): int
    {
        return $this->insert('phorum_messages', array_merge([
            'forum_id'   => 1,
            'thread'     => 0,
            'parent_id'  => 0,
            'user_id'    => 1,
            'author'     => 'tester',
            'subject'    => 'Hello',
            'body'       => 'World',
            'status'     => MessageMapper::STATUS_APPROVED,
            'datestamp'  => 1000,
            'modifystamp' => 1000,
            'sort'       => MessageMapper::SORT_DEFAULT,
        ], $override));
    }

    // -------------------------------------------------------------------------
    // Basic CRUD
    // -------------------------------------------------------------------------

    public function testSaveInsertAndLoad(): void
    {
        $mapper = $this->makeMapper();
        $m = new Message();
        $m->forum_id = 1;
        $m->author   = 'alice';
        $m->subject  = 'Hi';
        $m->body     = 'Test body';
        $m->status   = MessageMapper::STATUS_APPROVED;
        $mapper->save($m);

        $this->assertGreaterThan(0, $m->message_id);
        $loaded = $mapper->load($m->message_id);
        $this->assertSame('alice', $loaded->author);
    }

    public function testSaveUpdate(): void
    {
        $id = $this->seedMessage(['subject' => 'Old']);
        $mapper = $this->makeMapper();
        $m = $mapper->load($id);
        $m->subject = 'New';
        $mapper->save($m);
        $this->assertSame('New', $mapper->load($id)->subject);
    }

    public function testDelete(): void
    {
        $id = $this->seedMessage();
        $mapper = $this->makeMapper();
        $mapper->delete($id);
        $this->assertNull($mapper->load($id));
    }

    // -------------------------------------------------------------------------
    // findThreadsInForum
    // -------------------------------------------------------------------------

    public function testFindThreadsInForumReturnsTreadStarters(): void
    {
        $this->seedMessage(['forum_id' => 1, 'parent_id' => 0, 'datestamp' => 2000, 'modifystamp' => 2000, 'status' => MessageMapper::STATUS_APPROVED]);
        $this->seedMessage(['forum_id' => 1, 'parent_id' => 0, 'datestamp' => 1000, 'modifystamp' => 1000, 'status' => MessageMapper::STATUS_APPROVED]);
        $replyId = $this->seedMessage(['forum_id' => 1, 'parent_id' => 1, 'thread' => 1, 'datestamp' => 1500, 'modifystamp' => 1500]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findThreadsInForum(1);
        $this->assertCount(2, $results);
        // ordered by sort DESC, modifystamp DESC
        $this->assertSame(2000, $results[0]->modifystamp);
    }

    public function testFindThreadsInForumExcludesUnapproved(): void
    {
        $this->seedMessage(['forum_id' => 2, 'parent_id' => 0, 'status' => MessageMapper::STATUS_UNAPPROVED]);

        $mapper  = $this->makeMapper();
        $this->assertNull($mapper->findThreadsInForum(2));
    }

    public function testFindThreadsInForumLimitAndOffset(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedMessage(['forum_id' => 3, 'parent_id' => 0, 'datestamp' => $i]);
        }
        $mapper = $this->makeMapper();
        $results = $mapper->findThreadsInForum(3, limit: 2, offset: 1);
        $this->assertCount(2, $results);
    }

    // -------------------------------------------------------------------------
    // findUnapprovedInForums
    // -------------------------------------------------------------------------

    public function testFindUnapprovedInForumsReturnsOnlyUnapproved(): void
    {
        $this->seedMessage(['forum_id' => 1, 'status' => MessageMapper::STATUS_UNAPPROVED, 'datestamp' => 100]);
        $this->seedMessage(['forum_id' => 1, 'status' => MessageMapper::STATUS_APPROVED, 'datestamp' => 200]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findUnapprovedInForums([1]);
        $this->assertCount(1, $results);
        $this->assertSame(MessageMapper::STATUS_UNAPPROVED, $results[0]->status);
    }

    public function testFindUnapprovedInForumsScopesToGivenForums(): void
    {
        $this->seedMessage(['forum_id' => 1, 'status' => MessageMapper::STATUS_UNAPPROVED]);
        $this->seedMessage(['forum_id' => 2, 'status' => MessageMapper::STATUS_UNAPPROVED]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findUnapprovedInForums([1]);
        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]->forum_id);
    }

    public function testFindUnapprovedInForumsOrdersOldestFirst(): void
    {
        $this->seedMessage(['forum_id' => 1, 'status' => MessageMapper::STATUS_UNAPPROVED, 'datestamp' => 200]);
        $this->seedMessage(['forum_id' => 1, 'status' => MessageMapper::STATUS_UNAPPROVED, 'datestamp' => 100]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findUnapprovedInForums([1]);
        $this->assertSame(100, $results[0]->datestamp);
        $this->assertSame(200, $results[1]->datestamp);
    }

    public function testFindUnapprovedInForumsReturnsNullForEmptyForumList(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findUnapprovedInForums([]));
    }

    public function testFindUnapprovedInForumsReturnsNullWhenNoneFound(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findUnapprovedInForums([1]));
    }

    // -------------------------------------------------------------------------
    // findByThread
    // -------------------------------------------------------------------------

    public function testFindByThreadReturnsApprovedMessages(): void
    {
        $t = $this->seedMessage(['thread' => 0, 'parent_id' => 0, 'forum_id' => 1, 'datestamp' => 1]);
        // Update thread to self-reference
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$t} WHERE message_id = {$t}");
        $r = $this->seedMessage(['thread' => $t, 'parent_id' => $t, 'forum_id' => 1, 'datestamp' => 2, 'status' => MessageMapper::STATUS_APPROVED]);
        $this->seedMessage(['thread' => $t, 'parent_id' => $t, 'forum_id' => 1, 'datestamp' => 3, 'status' => MessageMapper::STATUS_UNAPPROVED]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findByThread($t);
        $this->assertCount(2, $results);
    }

    // -------------------------------------------------------------------------
    // setThreadId
    // -------------------------------------------------------------------------

    public function testSetThreadId(): void
    {
        $id = $this->seedMessage(['thread' => 0]);
        $mapper = $this->makeMapper();
        $mapper->setThreadId($id);

        $row = self::$pdo->query("SELECT thread FROM phorum_messages WHERE message_id = {$id}")->fetch();
        $this->assertSame($id, (int) $row['thread']);
    }

    // -------------------------------------------------------------------------
    // updateThreadStats
    // -------------------------------------------------------------------------

    public function testUpdateThreadStats(): void
    {
        $t = $this->seedMessage(['thread' => 0, 'parent_id' => 0, 'thread_count' => 1]);
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$t} WHERE message_id = {$t}");

        $mapper = $this->makeMapper();
        $mapper->updateThreadStats($t, 9999, 42, 7, 'bob');

        $m = $mapper->load($t);
        $this->assertSame(2, $m->thread_count);
        $this->assertSame(9999, $m->modifystamp);
        $this->assertSame(42, $m->recent_message_id);
        $this->assertSame(7, $m->recent_user_id);
        $this->assertSame('bob', $m->recent_author);
    }

    // -------------------------------------------------------------------------
    // findIdsByThread
    // -------------------------------------------------------------------------

    public function testFindIdsByThread(): void
    {
        $t = $this->seedMessage(['thread' => 0]);
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$t} WHERE message_id = {$t}");
        $r1 = $this->seedMessage(['thread' => $t]);
        $r2 = $this->seedMessage(['thread' => $t]);

        $mapper = $this->makeMapper();
        $ids    = $mapper->findIdsByThread($t);
        sort($ids);
        $this->assertSame([$t, $r1, $r2], $ids);
    }

    public function testFindIdsByThreadReturnsEmptyForMissing(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->findIdsByThread(9999));
    }

    // -------------------------------------------------------------------------
    // setStatus / setStatusForThread
    // -------------------------------------------------------------------------

    public function testSetStatus(): void
    {
        $id = $this->seedMessage(['status' => MessageMapper::STATUS_APPROVED]);
        $mapper = $this->makeMapper();
        $mapper->setStatus($id, MessageMapper::STATUS_DELETED);

        $row = self::$pdo->query("SELECT status FROM phorum_messages WHERE message_id = {$id}")->fetch();
        $this->assertSame(MessageMapper::STATUS_DELETED, (int) $row['status']);
    }

    public function testSetStatusForThread(): void
    {
        $t  = $this->seedMessage(['thread' => 0, 'status' => MessageMapper::STATUS_APPROVED]);
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$t} WHERE message_id = {$t}");
        $r1 = $this->seedMessage(['thread' => $t, 'status' => MessageMapper::STATUS_APPROVED]);

        $mapper = $this->makeMapper();
        $mapper->setStatusForThread($t, MessageMapper::STATUS_DELETED);

        foreach ([$t, $r1] as $mid) {
            $row = self::$pdo->query("SELECT status FROM phorum_messages WHERE message_id = {$mid}")->fetch();
            $this->assertSame(MessageMapper::STATUS_DELETED, (int) $row['status']);
        }
    }

    // -------------------------------------------------------------------------
    // reparentChildren
    // -------------------------------------------------------------------------

    public function testReparentChildren(): void
    {
        $t  = $this->seedMessage(['thread' => 0, 'parent_id' => 0, 'forum_id' => 1]);
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$t} WHERE message_id = {$t}");
        $r1 = $this->seedMessage(['thread' => $t, 'parent_id' => $t, 'forum_id' => 1]);
        $r2 = $this->seedMessage(['thread' => $t, 'parent_id' => $r1, 'forum_id' => 1]);

        $mapper = $this->makeMapper();
        // Re-parent r1's children to root ($t)
        $mapper->reparentChildren($r1, $t, 1);

        $row = self::$pdo->query("SELECT parent_id FROM phorum_messages WHERE message_id = {$r2}")->fetch();
        $this->assertSame($t, (int) $row['parent_id']);
    }

    // -------------------------------------------------------------------------
    // setClosedForThread / setSortForThread / setForumForThread
    // -------------------------------------------------------------------------

    public function testSetClosedForThread(): void
    {
        $t = $this->seedMessage(['thread' => 0]);
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$t} WHERE message_id = {$t}");

        $mapper = $this->makeMapper();
        $mapper->setClosedForThread($t, 1);

        $row = self::$pdo->query("SELECT closed FROM phorum_messages WHERE message_id = {$t}")->fetch();
        $this->assertSame(1, (int) $row['closed']);
    }

    public function testSetSortForThread(): void
    {
        $t = $this->seedMessage(['thread' => 0, 'sort' => MessageMapper::SORT_DEFAULT]);
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$t} WHERE message_id = {$t}");

        $mapper = $this->makeMapper();
        $mapper->setSortForThread($t, MessageMapper::SORT_STICKY);

        $row = self::$pdo->query("SELECT sort FROM phorum_messages WHERE message_id = {$t}")->fetch();
        $this->assertSame(MessageMapper::SORT_STICKY, (int) $row['sort']);
    }

    public function testSetForumForThread(): void
    {
        $t = $this->seedMessage(['thread' => 0, 'forum_id' => 1]);
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$t} WHERE message_id = {$t}");
        $this->seedMessage(['thread' => $t, 'forum_id' => 1]);

        $mapper = $this->makeMapper();
        $mapper->setForumForThread($t, 99);

        $rows = self::$pdo->query("SELECT forum_id FROM phorum_messages WHERE thread = {$t}")->fetchAll();
        foreach ($rows as $row) {
            $this->assertSame(99, (int) $row['forum_id']);
        }
    }

    // -------------------------------------------------------------------------
    // mergeThread
    // -------------------------------------------------------------------------

    public function testMergeThreadRethreadsMessagesAndReparentsOldRoot(): void
    {
        // Source thread: root + one reply, in forum 1
        $sourceRoot = $this->seedMessage(['thread' => 0, 'parent_id' => 0, 'forum_id' => 1]);
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$sourceRoot} WHERE message_id = {$sourceRoot}");
        $sourceReply = $this->seedMessage(['thread' => $sourceRoot, 'parent_id' => $sourceRoot, 'forum_id' => 1]);

        // Target thread: root only, in forum 2
        $targetRoot = $this->seedMessage(['thread' => 0, 'parent_id' => 0, 'forum_id' => 2]);
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$targetRoot} WHERE message_id = {$targetRoot}");

        $mapper = $this->makeMapper();
        $mapper->mergeThread($sourceRoot, $targetRoot, 2);

        $rows = self::$pdo->query(
            "SELECT message_id, thread, forum_id, parent_id FROM phorum_messages WHERE message_id IN ({$sourceRoot}, {$sourceReply}, {$targetRoot})"
        )->fetchAll(\PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE);

        // Both source messages now belong to the target thread and forum
        $this->assertSame($targetRoot, (int) $rows[$sourceRoot]['thread']);
        $this->assertSame(2, (int) $rows[$sourceRoot]['forum_id']);
        $this->assertSame($targetRoot, (int) $rows[$sourceReply]['thread']);
        $this->assertSame(2, (int) $rows[$sourceReply]['forum_id']);

        // The old source root is no longer a root — it's a reply under the target root
        $this->assertSame($targetRoot, (int) $rows[$sourceRoot]['parent_id']);

        // The reply's own parent (the old root) is untouched
        $rows2 = self::$pdo->query("SELECT parent_id FROM phorum_messages WHERE message_id = {$sourceReply}")->fetch();
        $this->assertSame($sourceRoot, (int) $rows2['parent_id']);
    }

    // -------------------------------------------------------------------------
    // recalcThreadStats
    // -------------------------------------------------------------------------

    public function testRecalcThreadStats(): void
    {
        $t = $this->seedMessage(['thread' => 0, 'parent_id' => 0, 'forum_id' => 1, 'datestamp' => 100, 'status' => MessageMapper::STATUS_APPROVED, 'user_id' => 1, 'author' => 'alice']);
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$t} WHERE message_id = {$t}");
        $this->seedMessage(['thread' => $t, 'parent_id' => $t, 'forum_id' => 1, 'datestamp' => 200, 'status' => MessageMapper::STATUS_APPROVED, 'user_id' => 2, 'author' => 'bob']);

        $mapper = $this->makeMapper();
        $mapper->recalcThreadStats($t);

        $m = $mapper->load($t);
        $this->assertSame(2, $m->thread_count);
        $this->assertSame(200, $m->modifystamp);
        $this->assertSame('bob', $m->recent_author);
    }

    // -------------------------------------------------------------------------
    // incrementViewCounts
    // -------------------------------------------------------------------------

    public function testIncrementViewCounts(): void
    {
        $id = $this->seedMessage(['viewcount' => 5, 'threadviewcount' => 10]);
        $mapper = $this->makeMapper();
        $mapper->incrementViewCounts($id);

        $m = $mapper->load($id);
        $this->assertSame(6, $m->viewcount);
        $this->assertSame(11, $m->threadviewcount);
    }

    // -------------------------------------------------------------------------
    // findByUser / findRecent
    // -------------------------------------------------------------------------

    public function testFindByUser(): void
    {
        $uid = 42;
        $this->seedMessage(['user_id' => $uid, 'status' => MessageMapper::STATUS_APPROVED, 'datestamp' => 1000]);
        $this->seedMessage(['user_id' => $uid, 'status' => MessageMapper::STATUS_APPROVED, 'datestamp' => 2000]);
        $this->seedMessage(['user_id' => 99,   'status' => MessageMapper::STATUS_APPROVED, 'datestamp' => 3000]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findByUser($uid);
        $this->assertCount(2, $results);
        $this->assertSame($uid, $results[0]->user_id);
    }

    public function testFindRecent(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedMessage(['status' => MessageMapper::STATUS_APPROVED, 'datestamp' => $i]);
        }
        $mapper  = $this->makeMapper();
        $results = $mapper->findRecent(3);
        $this->assertCount(3, $results);
    }

    // -------------------------------------------------------------------------
    // findRecentInForums
    // -------------------------------------------------------------------------

    public function testFindRecentInForumsReturnsNullForEmptyForumList(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findRecentInForums([]));
    }

    public function testFindRecentInForumsExcludesForumsNotInList(): void
    {
        $this->seedMessage(['forum_id' => 1, 'status' => MessageMapper::STATUS_APPROVED, 'datestamp' => 100]);
        $this->seedMessage(['forum_id' => 2, 'status' => MessageMapper::STATUS_APPROVED, 'datestamp' => 999]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findRecentInForums([1]);
        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]->forum_id);
    }

    public function testFindRecentInForumsUnionsMultipleForumsOrderedByDatestampDesc(): void
    {
        $this->seedMessage(['forum_id' => 1, 'status' => MessageMapper::STATUS_APPROVED, 'datestamp' => 100]);
        $this->seedMessage(['forum_id' => 2, 'status' => MessageMapper::STATUS_APPROVED, 'datestamp' => 300]);
        $this->seedMessage(['forum_id' => 3, 'status' => MessageMapper::STATUS_APPROVED, 'datestamp' => 200]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findRecentInForums([1, 2]);
        $this->assertCount(2, $results);
        $this->assertSame(300, $results[0]->datestamp);
        $this->assertSame(100, $results[1]->datestamp);
    }

    public function testFindRecentInForumsRespectsLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedMessage(['forum_id' => 1, 'status' => MessageMapper::STATUS_APPROVED, 'datestamp' => $i]);
        }
        $mapper  = $this->makeMapper();
        $results = $mapper->findRecentInForums([1], 3);
        $this->assertCount(3, $results);
    }

    public function testFindRecentInForumsExcludesUnapproved(): void
    {
        $this->seedMessage(['forum_id' => 1, 'status' => MessageMapper::STATUS_UNAPPROVED, 'datestamp' => 100]);

        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findRecentInForums([1]));
    }

    // -------------------------------------------------------------------------
    // findLastByUser
    // -------------------------------------------------------------------------

    public function testFindLastByUserReturnsMostRecentMessageId(): void
    {
        $this->seedMessage(['user_id' => 5, 'datestamp' => 100]);
        $this->seedMessage(['user_id' => 5, 'datestamp' => 200]);

        $mapper = $this->makeMapper();
        $result = $mapper->findLastByUser(5);
        $this->assertSame(200, $result->datestamp);
    }

    public function testFindLastByUserIncludesAnyStatus(): void
    {
        $this->seedMessage(['user_id' => 5, 'status' => MessageMapper::STATUS_UNAPPROVED, 'datestamp' => 100]);

        $mapper = $this->makeMapper();
        $result = $mapper->findLastByUser(5);
        $this->assertNotNull($result);
        $this->assertSame(MessageMapper::STATUS_UNAPPROVED, $result->status);
    }

    public function testFindLastByUserReturnsNullWhenNoPosts(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findLastByUser(999));
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\NewflagMapper;

class NewflagMapperTest extends MapperTestCase
{
    private function makeMapper(): NewflagMapper
    {
        return new class extends NewflagMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    // -------------------------------------------------------------------------
    // getFlags / countFlags / deleteAllFlags
    // -------------------------------------------------------------------------

    public function testGetFlagsAndCountFlags(): void
    {
        $this->insert('phorum_user_newflags', ['user_id' => 1, 'forum_id' => 10, 'message_id' => 100]);
        $this->insert('phorum_user_newflags', ['user_id' => 1, 'forum_id' => 10, 'message_id' => 200]);
        $this->insert('phorum_user_newflags', ['user_id' => 1, 'forum_id' => 99, 'message_id' => 300]);

        $mapper = $this->makeMapper();
        $flags  = $mapper->getFlags(1, 10);
        $this->assertArrayHasKey(100, $flags);
        $this->assertArrayHasKey(200, $flags);
        $this->assertArrayNotHasKey(300, $flags);
        $this->assertSame(2, $mapper->countFlags(1, 10));
    }

    public function testGetFlagsReturnsEmptyWhenNone(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->getFlags(9, 9));
        $this->assertSame(0, $mapper->countFlags(9, 9));
    }

    public function testDeleteAllFlags(): void
    {
        $this->insert('phorum_user_newflags', ['user_id' => 2, 'forum_id' => 5, 'message_id' => 50]);
        $this->insert('phorum_user_newflags', ['user_id' => 2, 'forum_id' => 5, 'message_id' => 60]);

        $mapper = $this->makeMapper();
        $mapper->deleteAllFlags(2, 5);
        $this->assertSame(0, $mapper->countFlags(2, 5));
    }

    // -------------------------------------------------------------------------
    // moveForumForMessages
    // -------------------------------------------------------------------------

    public function testMoveForumForMessagesRekeysOnlyGivenMessagesUnderOldForum(): void
    {
        $this->insert('phorum_user_newflags', ['user_id' => 1, 'forum_id' => 4, 'message_id' => 100]);
        $this->insert('phorum_user_newflags', ['user_id' => 1, 'forum_id' => 4, 'message_id' => 200]);
        // Unrelated flag under the same old forum, for a message not being moved.
        $this->insert('phorum_user_newflags', ['user_id' => 1, 'forum_id' => 4, 'message_id' => 300]);

        $mapper = $this->makeMapper();
        $mapper->moveForumForMessages(4, 9, [100, 200]);

        $movedFlags = $mapper->getFlags(1, 9);
        $this->assertArrayHasKey(100, $movedFlags);
        $this->assertArrayHasKey(200, $movedFlags);

        $remainingUnderOldForum = $mapper->getFlags(1, 4);
        $this->assertArrayHasKey(300, $remainingUnderOldForum);
        $this->assertArrayNotHasKey(100, $remainingUnderOldForum);
        $this->assertArrayNotHasKey(200, $remainingUnderOldForum);
    }

    public function testMoveForumForMessagesIsNoOpForEmptyMessageList(): void
    {
        $this->insert('phorum_user_newflags', ['user_id' => 1, 'forum_id' => 4, 'message_id' => 100]);

        $mapper = $this->makeMapper();
        $mapper->moveForumForMessages(4, 9, []);

        $this->assertArrayHasKey(100, $mapper->getFlags(1, 4));
    }

    // -------------------------------------------------------------------------
    // getMinFlagId
    // -------------------------------------------------------------------------

    public function testGetMinFlagId(): void
    {
        $this->insert('phorum_user_newflags', ['user_id' => 3, 'forum_id' => 7, 'message_id' => 40]);
        $this->insert('phorum_user_newflags', ['user_id' => 3, 'forum_id' => 7, 'message_id' => 80]);

        $mapper = $this->makeMapper();
        $this->assertSame(40, $mapper->getMinFlagId(3, 7));
    }

    public function testGetMinFlagIdReturnsZeroWhenEmpty(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame(0, $mapper->getMinFlagId(9, 9));
    }

    // -------------------------------------------------------------------------
    // getMaxMessageId
    // -------------------------------------------------------------------------

    public function testGetMaxMessageId(): void
    {
        $this->insert('phorum_messages', ['forum_id' => 50, 'status' => 2, 'thread' => 1, 'parent_id' => 0]);
        $this->insert('phorum_messages', ['forum_id' => 50, 'status' => 2, 'thread' => 2, 'parent_id' => 0]);
        $this->insert('phorum_messages', ['forum_id' => 50, 'status' => -1, 'thread' => 3, 'parent_id' => 0]);

        $mapper   = $this->makeMapper();
        $maxId    = $mapper->getMaxMessageId(50);
        $this->assertGreaterThan(0, $maxId);

        // deleted message should not be counted
        $rows = self::$pdo->query("SELECT message_id FROM phorum_messages WHERE forum_id = 50 AND status = 2 ORDER BY message_id DESC")->fetchAll();
        $this->assertSame((int) $rows[0]['message_id'], $maxId);
    }

    public function testGetMaxMessageIdReturnsZeroWhenEmpty(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame(0, $mapper->getMaxMessageId(9999));
    }

    // -------------------------------------------------------------------------
    // countNewPerForum
    // -------------------------------------------------------------------------

    public function testCountNewPerForum(): void
    {
        // Two approved messages in forum 60; user has minId=0, no flags
        $m1 = $this->insert('phorum_messages', ['forum_id' => 60, 'status' => 2, 'thread' => 0, 'parent_id' => 0]);
        $m2 = $this->insert('phorum_messages', ['forum_id' => 60, 'status' => 2, 'thread' => 0, 'parent_id' => 0]);
        // Mark m1 as read via flag
        $this->insert('phorum_user_newflags', ['user_id' => 1, 'forum_id' => 60, 'message_id' => $m1]);

        $mapper = $this->makeMapper();
        $result = $mapper->countNewPerForum(1, [60]);
        $this->assertArrayHasKey(60, $result);
        $this->assertSame(1, $result[60]);
    }

    public function testCountNewPerForumReturnsEmptyForEmptyInput(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->countNewPerForum(1, []));
    }

    /**
     * Mirrors markForumRead(): flagging only the highest message_id as read
     * should derive a min_id that suppresses every message at or below it —
     * not just the one exact flagged row — and independently per forum.
     */
    public function testCountNewPerForumDerivesMinIdAcrossMultipleForums(): void
    {
        $this->insert('phorum_messages', ['forum_id' => 61, 'status' => 2, 'thread' => 0, 'parent_id' => 0]);
        $this->insert('phorum_messages', ['forum_id' => 61, 'status' => 2, 'thread' => 0, 'parent_id' => 0]);
        $m3 = $this->insert('phorum_messages', ['forum_id' => 61, 'status' => 2, 'thread' => 0, 'parent_id' => 0]);
        $this->insert('phorum_user_newflags', ['user_id' => 1, 'forum_id' => 61, 'message_id' => $m3]);

        $this->insert('phorum_messages', ['forum_id' => 62, 'status' => 2, 'thread' => 0, 'parent_id' => 0]);
        $this->insert('phorum_messages', ['forum_id' => 62, 'status' => 2, 'thread' => 0, 'parent_id' => 0]);

        $mapper = $this->makeMapper();
        $result = $mapper->countNewPerForum(1, [61, 62]);

        // Forum 61: all 3 messages read via the derived min_id boundary
        $this->assertArrayNotHasKey(61, $result);
        // Forum 62: both messages still unread (no flags at all)
        $this->assertSame(2, $result[62]);
    }

    // -------------------------------------------------------------------------
    // countNewInThreads
    // -------------------------------------------------------------------------

    public function testCountNewInThreads(): void
    {
        $thread = $this->insert('phorum_messages', ['forum_id' => 70, 'status' => 2, 'thread' => 0, 'parent_id' => 0]);
        self::$pdo->exec("UPDATE phorum_messages SET thread = {$thread} WHERE message_id = {$thread}");
        $reply  = $this->insert('phorum_messages', ['forum_id' => 70, 'status' => 2, 'thread' => $thread, 'parent_id' => $thread]);

        $mapper = $this->makeMapper();
        $result = $mapper->countNewInThreads(1, 70, 0);
        $this->assertArrayHasKey($thread, $result);
        $this->assertSame(2, $result[$thread]);
    }
}

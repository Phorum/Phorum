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
    // getMinId / setMinId
    // -------------------------------------------------------------------------

    public function testGetMinIdReturnsZeroWhenMissing(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame(0, $mapper->getMinId(1, 10));
    }

    public function testSetMinIdAndGetMinId(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setMinId(1, 10, 500);
        $this->assertSame(500, $mapper->getMinId(1, 10));
    }

    public function testSetMinIdReplaces(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setMinId(1, 10, 100);
        $mapper->setMinId(1, 10, 200);
        $this->assertSame(200, $mapper->getMinId(1, 10));
    }

    // -------------------------------------------------------------------------
    // getMinIds
    // -------------------------------------------------------------------------

    public function testGetMinIdsReturnsMultipleForums(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setMinId(5, 1, 10);
        $mapper->setMinId(5, 2, 20);

        $result = $mapper->getMinIds(5, [1, 2, 3]);
        $this->assertSame(10, $result[1]);
        $this->assertSame(20, $result[2]);
        $this->assertArrayNotHasKey(3, $result); // forum 3 has no row
    }

    public function testGetMinIdsReturnsEmptyForEmptyInput(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->getMinIds(1, []));
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

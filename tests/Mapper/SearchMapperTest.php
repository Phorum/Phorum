<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\SearchMapper;

class SearchMapperTest extends MapperTestCase
{
    private function makeMapper(): SearchMapper
    {
        return new class extends SearchMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    // -------------------------------------------------------------------------
    // indexMessage (insert then update)
    // -------------------------------------------------------------------------

    public function testIndexMessageInserts(): void
    {
        $mapper = $this->makeMapper();
        $mapper->indexMessage(1, 10, 'alice', 'Hello', 'World');

        $row = self::$pdo->query("SELECT search_text FROM phorum_search WHERE message_id = 1")->fetch();
        $this->assertStringContainsString('alice', $row['search_text']);
        $this->assertStringContainsString('Hello', $row['search_text']);
        $this->assertStringContainsString('World', $row['search_text']);
    }

    public function testIndexMessageUpdatesOnDuplicate(): void
    {
        $mapper = $this->makeMapper();
        $mapper->indexMessage(1, 10, 'alice', 'Old Subject', 'Old Body');
        $mapper->indexMessage(1, 10, 'alice', 'New Subject', 'New Body');

        $row = self::$pdo->query("SELECT search_text FROM phorum_search WHERE message_id = 1")->fetch();
        $this->assertStringContainsString('New Subject', $row['search_text']);
        $this->assertStringNotContainsString('Old Subject', $row['search_text']);
    }

    // -------------------------------------------------------------------------
    // removeMessage
    // -------------------------------------------------------------------------

    public function testRemoveMessage(): void
    {
        $this->insert('phorum_search', ['message_id' => 5, 'forum_id' => 1, 'search_text' => 'foo']);
        $mapper = $this->makeMapper();
        $mapper->removeMessage(5);

        $row = self::$pdo->query("SELECT COUNT(*) AS cnt FROM phorum_search WHERE message_id = 5")->fetch();
        $this->assertSame(0, (int) $row['cnt']);
    }

    // -------------------------------------------------------------------------
    // removeThread (uses sub-select from messages table)
    // -------------------------------------------------------------------------

    public function testRemoveThread(): void
    {
        // Seed two messages in the same thread
        $this->insert('phorum_messages', ['thread' => 10, 'forum_id' => 1, 'author' => 'a', 'status' => 2]);
        $this->insert('phorum_messages', ['thread' => 10, 'forum_id' => 1, 'author' => 'b', 'status' => 2]);
        // Seed search rows for those messages (using raw IDs from the sequence)
        $rows = self::$pdo->query("SELECT message_id FROM phorum_messages WHERE thread = 10")->fetchAll();
        foreach ($rows as $row) {
            $this->insert('phorum_search', ['message_id' => $row['message_id'], 'forum_id' => 1, 'search_text' => 'test']);
        }

        $mapper = $this->makeMapper();
        $mapper->removeThread(10);

        $cnt = self::$pdo->query("SELECT COUNT(*) AS c FROM phorum_search")->fetch()['c'];
        $this->assertSame(0, (int) $cnt);
    }

    // -------------------------------------------------------------------------
    // updateForum
    // -------------------------------------------------------------------------

    public function testUpdateForum(): void
    {
        $mid = $this->insert('phorum_messages', ['thread' => 20, 'forum_id' => 1, 'author' => 'x', 'status' => 2]);
        $this->insert('phorum_search', ['message_id' => $mid, 'forum_id' => 1, 'search_text' => 'test']);

        $mapper = $this->makeMapper();
        $mapper->updateForum(20, 99);

        $row = self::$pdo->query("SELECT forum_id FROM phorum_search WHERE message_id = {$mid}")->fetch();
        $this->assertSame(99, (int) $row['forum_id']);
    }
}

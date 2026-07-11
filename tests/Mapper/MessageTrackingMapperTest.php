<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\MessageTrackingMapper;
use Phorum\Model\MessageTracking;

class MessageTrackingMapperTest extends MapperTestCase
{
    private function makeMapper(): MessageTrackingMapper
    {
        return new class extends MessageTrackingMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    // -------------------------------------------------------------------------
    // findByMessage
    // -------------------------------------------------------------------------

    public function testFindByMessageReturnsSortedEntries(): void
    {
        $this->insert('phorum_messages_edittrack', ['message_id' => 1, 'user_id' => 10, 'time' => 100, 'diff_body' => 'v1', 'diff_subject' => '']);
        $this->insert('phorum_messages_edittrack', ['message_id' => 1, 'user_id' => 10, 'time' => 200, 'diff_body' => 'v2', 'diff_subject' => '']);

        $mapper  = $this->makeMapper();
        $results = $mapper->findByMessage(1);
        $this->assertCount(2, $results);
        $this->assertSame('v1', $results[0]->diff_body);
        $this->assertSame('v2', $results[1]->diff_body);
    }

    public function testFindByMessageReturnsEmptyForMissing(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->findByMessage(999));
    }

    // -------------------------------------------------------------------------
    // record
    // -------------------------------------------------------------------------

    public function testRecordSavesEntry(): void
    {
        $mapper = $this->makeMapper();
        $mapper->record(5, 10, 'old body', 'old subject');

        $results = $mapper->findByMessage(5);
        $this->assertCount(1, $results);
        $this->assertSame('old body', $results[0]->diff_body);
        $this->assertSame('old subject', $results[0]->diff_subject);
        $this->assertSame(10, $results[0]->user_id);
    }

    // -------------------------------------------------------------------------
    // deleteForMessage
    // -------------------------------------------------------------------------

    public function testDeleteForMessage(): void
    {
        $this->insert('phorum_messages_edittrack', ['message_id' => 7, 'user_id' => 1, 'time' => 100, 'diff_body' => 'x', 'diff_subject' => '']);
        $this->insert('phorum_messages_edittrack', ['message_id' => 7, 'user_id' => 1, 'time' => 200, 'diff_body' => 'y', 'diff_subject' => '']);
        $this->insert('phorum_messages_edittrack', ['message_id' => 8, 'user_id' => 1, 'time' => 300, 'diff_body' => 'z', 'diff_subject' => '']);

        $mapper = $this->makeMapper();
        $mapper->deleteForMessage(7);

        $this->assertSame([], $mapper->findByMessage(7));
        // message 8 unaffected
        $this->assertCount(1, $mapper->findByMessage(8));
    }
}

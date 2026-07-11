<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\ModLogMapper;

class ModLogMapperTest extends MapperTestCase
{
    private function makeMapper(): ModLogMapper
    {
        return new class extends ModLogMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    public function testRecordInsertsEntry(): void
    {
        $mapper = $this->makeMapper();
        $mapper->record(5, 'approve', 'message', 42, 1, 'Some Subject');

        $rows = $mapper->find(filter: []);
        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows[0]->user_id);
        $this->assertSame('approve', $rows[0]->action);
        $this->assertSame('message', $rows[0]->object_type);
        $this->assertSame(42, $rows[0]->object_id);
        $this->assertSame(1, $rows[0]->forum_id);
        $this->assertSame('Some Subject', $rows[0]->details);
        $this->assertGreaterThan(0, $rows[0]->time);
    }

    public function testFindRecentOrdersNewestFirst(): void
    {
        $mapper = $this->makeMapper();
        $this->insert('phorum_mod_log', ['user_id' => 1, 'forum_id' => 1, 'action' => 'delete', 'object_type' => 'message', 'object_id' => 1, 'details' => '', 'time' => 100]);
        $this->insert('phorum_mod_log', ['user_id' => 1, 'forum_id' => 1, 'action' => 'approve', 'object_type' => 'message', 'object_id' => 2, 'details' => '', 'time' => 200]);

        $results = $mapper->findRecent();
        $this->assertCount(2, $results);
        $this->assertSame(200, $results[0]->time);
        $this->assertSame(100, $results[1]->time);
    }

    public function testFindRecentRespectsLimit(): void
    {
        $mapper = $this->makeMapper();
        for ($i = 0; $i < 5; $i++) {
            $this->insert('phorum_mod_log', ['user_id' => 1, 'forum_id' => 1, 'action' => 'delete', 'object_type' => 'message', 'object_id' => $i, 'details' => '', 'time' => $i]);
        }

        $results = $mapper->findRecent(2);
        $this->assertCount(2, $results);
    }

    public function testFindRecentReturnsNullWhenEmpty(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findRecent());
    }
}

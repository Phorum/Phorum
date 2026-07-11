<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\ReportMapper;

class ReportMapperTest extends MapperTestCase
{
    private function makeMapper(): ReportMapper
    {
        return new class extends ReportMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    public function testCreateInsertsOpenReport(): void
    {
        $mapper = $this->makeMapper();
        $mapper->create(10, 1, 5, 'Spam content');

        $rows = $mapper->find(filter: []);
        $this->assertCount(1, $rows);
        $this->assertSame(10, $rows[0]->message_id);
        $this->assertSame(1, $rows[0]->forum_id);
        $this->assertSame(5, $rows[0]->reporter_user_id);
        $this->assertSame('Spam content', $rows[0]->reason);
        $this->assertSame(ReportMapper::STATUS_OPEN, $rows[0]->status);
        $this->assertGreaterThan(0, $rows[0]->created);
    }

    public function testFindOpenInForumsReturnsOnlyOpenReports(): void
    {
        $this->insert('phorum_reports', ['message_id' => 1, 'forum_id' => 1, 'reporter_user_id' => 1, 'reason' => '', 'status' => ReportMapper::STATUS_OPEN, 'created' => 100]);
        $this->insert('phorum_reports', ['message_id' => 2, 'forum_id' => 1, 'reporter_user_id' => 1, 'reason' => '', 'status' => ReportMapper::STATUS_RESOLVED, 'created' => 200]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findOpenInForums([1]);
        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]->message_id);
    }

    public function testFindOpenInForumsScopesToGivenForums(): void
    {
        $this->insert('phorum_reports', ['message_id' => 1, 'forum_id' => 1, 'reporter_user_id' => 1, 'reason' => '', 'status' => ReportMapper::STATUS_OPEN, 'created' => 100]);
        $this->insert('phorum_reports', ['message_id' => 2, 'forum_id' => 2, 'reporter_user_id' => 1, 'reason' => '', 'status' => ReportMapper::STATUS_OPEN, 'created' => 100]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findOpenInForums([1]);
        $this->assertCount(1, $results);
        $this->assertSame(1, $results[0]->forum_id);
    }

    public function testFindOpenInForumsReturnsNullForEmptyForumList(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findOpenInForums([]));
    }

    public function testFindOpenInForumsReturnsNullWhenNoneOpen(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findOpenInForums([1]));
    }

    public function testResolveMarksStatusAndResolver(): void
    {
        $id = $this->insert('phorum_reports', ['message_id' => 1, 'forum_id' => 1, 'reporter_user_id' => 1, 'reason' => '', 'status' => ReportMapper::STATUS_OPEN, 'created' => 100]);

        $mapper = $this->makeMapper();
        $mapper->resolve($id, 9, ReportMapper::STATUS_RESOLVED);

        $updated = $mapper->load($id);
        $this->assertSame(ReportMapper::STATUS_RESOLVED, $updated->status);
        $this->assertSame(9, $updated->resolved_user_id);
        $this->assertGreaterThan(0, $updated->resolved_time);
    }

    public function testResolveDoesNothingForMissingReport(): void
    {
        $mapper = $this->makeMapper();
        $mapper->resolve(999, 9, ReportMapper::STATUS_DISMISSED);
        $this->assertNull($mapper->load(999));
    }
}

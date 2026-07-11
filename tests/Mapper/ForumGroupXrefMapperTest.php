<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\ForumGroupXrefMapper;

class ForumGroupXrefMapperTest extends MapperTestCase
{
    private function makeMapper(): ForumGroupXrefMapper
    {
        return new class extends ForumGroupXrefMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    public function testSetPermissionInsertsNewGrant(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setPermission(1, 10, 3);

        $row = $mapper->findByForumAndGroup(1, 10);
        $this->assertNotNull($row);
        $this->assertSame(3, $row->permission);
    }

    public function testSetPermissionUpdatesExistingGrant(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setPermission(1, 10, 3);
        $mapper->setPermission(1, 10, 65);

        $rows = $mapper->find(filter: ['forum_id' => 1, 'group_id' => 10]);
        $this->assertCount(1, $rows);
        $this->assertSame(65, $rows[0]->permission);
    }

    public function testFindByGroupReturnsAllGrants(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setPermission(1, 10, 3);
        $mapper->setPermission(2, 10, 3);
        $mapper->setPermission(1, 20, 3);

        $grants = $mapper->findByGroup(10);
        $this->assertCount(2, $grants);
    }

    public function testRemovePermissionDeletesGrant(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setPermission(1, 10, 3);
        $mapper->removePermission(1, 10);

        $this->assertNull($mapper->findByForumAndGroup(1, 10));
    }

    public function testRemovePermissionDoesNothingWhenNoGrant(): void
    {
        $mapper = $this->makeMapper();
        $mapper->removePermission(1, 10);
        $this->assertNull($mapper->findByForumAndGroup(1, 10));
    }
}

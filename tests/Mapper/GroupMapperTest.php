<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\GroupMapper;
use Phorum\Model\Group;

class GroupMapperTest extends MapperTestCase
{
    private function makeMapper(): GroupMapper
    {
        return new class extends GroupMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    public function testSaveInsertAndLoadById(): void
    {
        $mapper = $this->makeMapper();
        $group       = new Group();
        $group->name = 'Moderators';
        $group->open = 0;
        $mapper->save($group);

        $this->assertGreaterThan(0, $group->group_id);
        $loaded = $mapper->load($group->group_id);
        $this->assertInstanceOf(Group::class, $loaded);
        $this->assertSame('Moderators', $loaded->name);
    }

    public function testSaveUpdate(): void
    {
        $id = $this->insert('phorum_groups', ['name' => 'Old Name', 'open' => 0]);
        $mapper = $this->makeMapper();
        $group  = $mapper->load($id);
        $group->name = 'New Name';
        $mapper->save($group);

        $this->assertSame('New Name', $mapper->load($id)->name);
    }

    public function testDelete(): void
    {
        $id = $this->insert('phorum_groups', ['name' => 'Temp', 'open' => 0]);
        $mapper = $this->makeMapper();
        $mapper->delete($id);
        $this->assertNull($mapper->load($id));
    }

    public function testLoadMissingReturnsNull(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->load(999));
    }

    public function testFindReturnsAllGroups(): void
    {
        $this->insert('phorum_groups', ['name' => 'A', 'open' => 0]);
        $this->insert('phorum_groups', ['name' => 'B', 'open' => 1]);

        $mapper = $this->makeMapper();
        $this->assertCount(2, $mapper->find(filter: []));
    }
}

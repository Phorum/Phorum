<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\UserGroupXrefMapper;

class UserGroupXrefMapperTest extends MapperTestCase
{
    private function makeMapper(): UserGroupXrefMapper
    {
        return new class extends UserGroupXrefMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    public function testSetMembershipInsertsNewRow(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setMembership(5, 1, UserGroupXrefMapper::STATUS_APPROVED);

        $row = $mapper->findByUserAndGroup(5, 1);
        $this->assertNotNull($row);
        $this->assertSame(UserGroupXrefMapper::STATUS_APPROVED, $row->status);
    }

    public function testSetMembershipUpdatesExistingRow(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setMembership(5, 1, UserGroupXrefMapper::STATUS_UNAPPROVED);
        $mapper->setMembership(5, 1, UserGroupXrefMapper::STATUS_MODERATOR);

        $rows = $mapper->find(filter: ['user_id' => 5, 'group_id' => 1]);
        $this->assertCount(1, $rows);
        $this->assertSame(UserGroupXrefMapper::STATUS_MODERATOR, $rows[0]->status);
    }

    public function testFindByGroupReturnsAllMembers(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setMembership(1, 10, UserGroupXrefMapper::STATUS_APPROVED);
        $mapper->setMembership(2, 10, UserGroupXrefMapper::STATUS_APPROVED);
        $mapper->setMembership(3, 20, UserGroupXrefMapper::STATUS_APPROVED);

        $members = $mapper->findByGroup(10);
        $this->assertCount(2, $members);
    }

    public function testFindByUserReturnsAllGroupsForUser(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setMembership(1, 10, UserGroupXrefMapper::STATUS_APPROVED);
        $mapper->setMembership(1, 20, UserGroupXrefMapper::STATUS_APPROVED);

        $memberships = $mapper->findByUser(1);
        $this->assertCount(2, $memberships);
    }

    public function testRemoveMembershipDeletesRow(): void
    {
        $mapper = $this->makeMapper();
        $mapper->setMembership(5, 1, UserGroupXrefMapper::STATUS_APPROVED);
        $mapper->removeMembership(5, 1);

        $this->assertNull($mapper->findByUserAndGroup(5, 1));
    }

    public function testRemoveMembershipDoesNothingWhenNotAMember(): void
    {
        $mapper = $this->makeMapper();
        $mapper->removeMembership(5, 1);
        $this->assertNull($mapper->findByUserAndGroup(5, 1));
    }

    public function testFindByUserAndGroupReturnsNullWhenNoMatch(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findByUserAndGroup(1, 1));
    }
}

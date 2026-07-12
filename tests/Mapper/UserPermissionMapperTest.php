<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use Phorum\Mapper\UserPermissionMapper;

class UserPermissionMapperTest extends MapperTestCase
{
    private function makeMapper(): UserPermissionMapper
    {
        $mapper = new UserPermissionMapper();
        $ref    = new \ReflectionProperty(UserPermissionMapper::class, 'crud');
        $ref->setValue($mapper, self::$crud);
        return $mapper;
    }

    // -------------------------------------------------------------------------
    // getDirectPermission
    // -------------------------------------------------------------------------

    public function testGetDirectPermissionReturnsValue(): void
    {
        $this->insert('phorum_user_permissions', ['user_id' => 1, 'forum_id' => 10, 'permission' => 7]);

        $mapper = $this->makeMapper();
        $this->assertSame(7, $mapper->getDirectPermission(1, 10));
    }

    public function testGetDirectPermissionReturnsNullForMissing(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->getDirectPermission(9, 9));
    }

    public function testGetDirectPermissionScopedToForumId(): void
    {
        $this->insert('phorum_user_permissions', ['user_id' => 2, 'forum_id' => 20, 'permission' => 15]);
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->getDirectPermission(2, 99));
        $this->assertSame(15, $mapper->getDirectPermission(2, 20));
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\UserMapper;
use Phorum\Model\User;

class UserMapperTest extends MapperTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HookDispatcher::reset();
        require_once dirname(__DIR__, 2) . '/src/Hook/functions.php';
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    private function makeMapper(): UserMapper
    {
        return new class extends UserMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    private function seedUser(array $override = []): int
    {
        return $this->insert('phorum_users', array_merge([
            'username'     => 'user' . rand(1, 999999),
            'email'        => 'u' . rand(1, 999999) . '@example.com',
            'password'     => 'hash',
            'active'       => 1,
            'settings_data' => '{}',
        ], $override));
    }

    // -------------------------------------------------------------------------
    // save / load
    // -------------------------------------------------------------------------

    public function testSaveInsertAndLoad(): void
    {
        $mapper = $this->makeMapper();
        $u = new User();
        $u->username = 'alice';
        $u->email    = 'alice@example.com';
        $u->active   = 1;
        $mapper->save($u);

        $this->assertGreaterThan(0, $u->user_id);
        $loaded = $mapper->load($u->user_id);
        $this->assertSame('alice', $loaded->username);
    }

    public function testSaveAndLoadRoundTripsRegIp(): void
    {
        $mapper = $this->makeMapper();
        $u = new User();
        $u->username = 'alice';
        $u->email    = 'alice@example.com';
        $u->active   = 1;
        $u->reg_ip   = '198.51.100.23';
        $mapper->save($u);

        $loaded = $mapper->load($u->user_id);
        $this->assertSame('198.51.100.23', $loaded->reg_ip);
    }

    public function testSaveUpdate(): void
    {
        $id = $this->seedUser(['username' => 'bob']);
        $mapper = $this->makeMapper();
        $u = $mapper->load($id);
        $u->username = 'robert';
        $mapper->save($u);
        $this->assertSame('robert', $mapper->load($id)->username);
    }

    // -------------------------------------------------------------------------
    // findByUsername / findByEmail
    // -------------------------------------------------------------------------

    public function testFindByUsernameReturnsUser(): void
    {
        $this->seedUser(['username' => 'carol', 'email' => 'carol@example.com']);
        $mapper = $this->makeMapper();
        $result = $mapper->findByUsername('carol');
        $this->assertNotNull($result);
        $this->assertSame('carol', $result->username);
    }

    public function testFindByUsernameReturnsNullForMissing(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findByUsername('ghost'));
    }

    public function testFindByEmailReturnsUser(): void
    {
        $this->seedUser(['username' => 'dave', 'email' => 'dave@example.com']);
        $mapper = $this->makeMapper();
        $result = $mapper->findByEmail('dave@example.com');
        $this->assertNotNull($result);
        $this->assertSame('dave', $result->username);
    }

    // -------------------------------------------------------------------------
    // findBySessionLt / findBySessionSt / findByPasswordTemp
    // -------------------------------------------------------------------------

    public function testFindBySessionLt(): void
    {
        $this->seedUser(['sessid_lt' => 'lttoken123']);
        $mapper = $this->makeMapper();
        $result = $mapper->findBySessionLt('lttoken123');
        $this->assertNotNull($result);
        $this->assertSame('lttoken123', $result->sessid_lt);
    }

    public function testFindBySessionSt(): void
    {
        $this->seedUser(['sessid_st' => 'sttoken456']);
        $mapper = $this->makeMapper();
        $result = $mapper->findBySessionSt('sttoken456');
        $this->assertNotNull($result);
        $this->assertSame('sttoken456', $result->sessid_st);
    }

    public function testFindByPasswordTemp(): void
    {
        $this->seedUser(['password_temp' => 'resettoken789']);
        $mapper = $this->makeMapper();
        $result = $mapper->findByPasswordTemp('resettoken789');
        $this->assertNotNull($result);
        $this->assertSame('resettoken789', $result->password_temp);
    }

    // -------------------------------------------------------------------------
    // incrementNewPmCount
    // -------------------------------------------------------------------------

    public function testIncrementNewPmCount(): void
    {
        $id = $this->seedUser(['pm_new_count' => 3]);
        $mapper = $this->makeMapper();
        $mapper->incrementNewPmCount($id);

        $row = self::$pdo->query("SELECT pm_new_count FROM phorum_users WHERE user_id = {$id}")->fetch();
        $this->assertSame(4, (int) $row['pm_new_count']);
    }

    // -------------------------------------------------------------------------
    // findModeratorsForForum
    // -------------------------------------------------------------------------

    public function testFindModeratorsIncludesAdmins(): void
    {
        $uid = $this->seedUser(['admin' => 1, 'active' => 1, 'moderation_email' => 1, 'email' => 'mod@example.com']);
        $mapper = $this->makeMapper();
        $results = $mapper->findModeratorsForForum(1);
        $uids = array_map('intval', array_column($results, 'user_id'));
        $this->assertContains($uid, $uids);
    }

    public function testFindModeratorsExcludesInactiveAdmins(): void
    {
        $this->seedUser(['admin' => 1, 'active' => 0, 'moderation_email' => 1, 'email' => 'inactive@example.com']);
        $mapper = $this->makeMapper();
        $results = $mapper->findModeratorsForForum(1);
        $this->assertSame([], $results);
    }

    public function testFindModeratorsIncludesDirectPermissionUsers(): void
    {
        $uid = $this->seedUser(['admin' => 0, 'active' => 1, 'moderation_email' => 1, 'email' => 'perm@example.com']);
        $this->insert('phorum_user_permissions', ['user_id' => $uid, 'forum_id' => 10, 'permission' => 64]);
        $mapper = $this->makeMapper();
        $results = $mapper->findModeratorsForForum(10);
        $uids = array_map('intval', array_column($results, 'user_id'));
        $this->assertContains($uid, $uids);
    }

    // -------------------------------------------------------------------------
    // incrementDeletedCount
    // -------------------------------------------------------------------------

    public function testIncrementDeletedCountDefaultsToOne(): void
    {
        $uid = $this->seedUser(['deleted_count' => 5]);
        $mapper = $this->makeMapper();
        $mapper->incrementDeletedCount($uid);
        $this->assertSame(6, $mapper->load($uid)->deleted_count);
    }

    public function testIncrementDeletedCountByExplicitAmount(): void
    {
        $uid = $this->seedUser(['deleted_count' => 2]);
        $mapper = $this->makeMapper();
        $mapper->incrementDeletedCount($uid, 3);
        $this->assertSame(5, $mapper->load($uid)->deleted_count);
    }

    // -------------------------------------------------------------------------
    // findPendingModeration
    // -------------------------------------------------------------------------

    public function testFindPendingModerationIncludesPendingModAndPendingBoth(): void
    {
        $modId  = $this->seedUser(['username' => 'aaa_pending_mod', 'active' => UserMapper::PENDING_MOD]);
        $bothId = $this->seedUser(['username' => 'bbb_pending_both', 'active' => UserMapper::PENDING_BOTH]);
        $this->seedUser(['username' => 'ccc_active', 'active' => UserMapper::ACTIVE]);
        $this->seedUser(['username' => 'ddd_pending_email', 'active' => UserMapper::PENDING_EMAIL]);
        $this->seedUser(['username' => 'eee_inactive', 'active' => UserMapper::INACTIVE]);

        $mapper = $this->makeMapper();
        $results = $mapper->findPendingModeration();

        $ids = array_map(fn($u) => $u->user_id, $results);
        $this->assertContains($modId, $ids);
        $this->assertContains($bothId, $ids);
        $this->assertCount(2, $results);
    }

    public function testFindPendingModerationOrderedByUsername(): void
    {
        $this->seedUser(['username' => 'zeta', 'active' => UserMapper::PENDING_MOD]);
        $this->seedUser(['username' => 'alpha', 'active' => UserMapper::PENDING_BOTH]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findPendingModeration();

        $this->assertSame('alpha', $results[0]->username);
        $this->assertSame('zeta', $results[1]->username);
    }

    public function testFindPendingModerationReturnsNullWhenNoneWaiting(): void
    {
        $this->seedUser(['active' => UserMapper::ACTIVE]);
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findPendingModeration());
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mod\Oauth\OauthIdentity;
use Phorum\Mod\Oauth\OauthIdentityMapper;

class OauthIdentityMapperTest extends MapperTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $base = dirname(__DIR__, 2) . '/mods/oauth';
        require_once $base . '/OauthIdentity.php';
        require_once $base . '/OauthIdentityMapper.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::$pdo->exec('DELETE FROM phorum_oauth_identities');
        self::$pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'phorum_oauth_identities'");
    }

    private function makeMapper(): OauthIdentityMapper
    {
        return new class extends OauthIdentityMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    private function seedIdentity(array $override = []): int
    {
        return $this->insert('phorum_oauth_identities', array_merge([
            'user_id'          => 1,
            'provider'         => 'google',
            'provider_user_id' => 'g-123',
            'email'            => 'alice@example.com',
            'date_added'       => 1000,
        ], $override));
    }

    // -------------------------------------------------------------------------
    // Basic CRUD
    // -------------------------------------------------------------------------

    public function testSaveInsertAndLoad(): void
    {
        $mapper = $this->makeMapper();
        $i = new OauthIdentity();
        $i->user_id          = 5;
        $i->provider         = 'github';
        $i->provider_user_id = 'gh-42';
        $i->email            = 'bob@example.com';
        $i->date_added       = 1234;
        $mapper->save($i);

        $this->assertGreaterThan(0, $i->oauth_identity_id);
        $loaded = $mapper->load($i->oauth_identity_id);
        $this->assertSame('github', $loaded->provider);
        $this->assertSame('gh-42', $loaded->provider_user_id);
        $this->assertSame(5, $loaded->user_id);
    }

    // -------------------------------------------------------------------------
    // findByProviderAndId
    // -------------------------------------------------------------------------

    public function testFindByProviderAndIdReturnsMatchingIdentity(): void
    {
        $this->seedIdentity(['provider' => 'google', 'provider_user_id' => 'g-123', 'user_id' => 7]);

        $mapper = $this->makeMapper();
        $found  = $mapper->findByProviderAndId('google', 'g-123');

        $this->assertNotNull($found);
        $this->assertSame(7, $found->user_id);
    }

    public function testFindByProviderAndIdReturnsNullWhenNoMatch(): void
    {
        $this->seedIdentity(['provider' => 'google', 'provider_user_id' => 'g-123']);

        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findByProviderAndId('github', 'g-123'));
        $this->assertNull($mapper->findByProviderAndId('google', 'g-999'));
    }

    // -------------------------------------------------------------------------
    // findByUserId
    // -------------------------------------------------------------------------

    public function testFindByUserIdReturnsAllLinkedIdentities(): void
    {
        $this->seedIdentity(['user_id' => 3, 'provider' => 'google', 'provider_user_id' => 'g-1']);
        $this->seedIdentity(['user_id' => 3, 'provider' => 'github', 'provider_user_id' => 'gh-1']);
        $this->seedIdentity(['user_id' => 4, 'provider' => 'google', 'provider_user_id' => 'g-2']);

        $mapper = $this->makeMapper();
        $this->assertCount(2, $mapper->findByUserId(3));
        $this->assertCount(1, $mapper->findByUserId(4));
        $this->assertSame([], $mapper->findByUserId(999));
    }
}

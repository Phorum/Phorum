<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\BanMapper;
use Phorum\Model\Ban;

class BanMapperTest extends MapperTestCase
{
    private function makeMapper(): BanMapper
    {
        return new class extends BanMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public function testSaveInsertAndLoadById(): void
    {
        $mapper = $this->makeMapper();
        $ban    = new Ban();
        $ban->type   = 1;
        $ban->string = 'evil.com';
        $mapper->save($ban);

        $this->assertGreaterThan(0, $ban->id);
        $loaded = $mapper->load($ban->id);
        $this->assertInstanceOf(Ban::class, $loaded);
        $this->assertSame('evil.com', $loaded->string);
    }

    public function testSaveUpdate(): void
    {
        $id = $this->insert('phorum_banlists', ['forum_id' => 0, 'type' => 1, 'pcre' => 0, 'string' => 'old', 'comments' => '']);
        $mapper = $this->makeMapper();
        $ban    = $mapper->load($id);
        $ban->string = 'new';
        $mapper->save($ban);

        $this->assertSame('new', $mapper->load($id)->string);
    }

    public function testDelete(): void
    {
        $id = $this->insert('phorum_banlists', ['forum_id' => 0, 'type' => 1, 'pcre' => 0, 'string' => 'evil.com', 'comments' => '']);
        $mapper = $this->makeMapper();
        $mapper->delete($id);

        $this->assertNull($mapper->load($id));
    }

    public function testLoadMissingReturnsNull(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->load(999));
    }

    public function testFindReturnsAllEntries(): void
    {
        $this->insert('phorum_banlists', ['forum_id' => 0, 'type' => 1, 'pcre' => 0, 'string' => 'a', 'comments' => '']);
        $this->insert('phorum_banlists', ['forum_id' => 0, 'type' => 2, 'pcre' => 0, 'string' => 'b', 'comments' => '']);

        $mapper = $this->makeMapper();
        $rows   = $mapper->find(filter: []);
        $this->assertCount(2, $rows);
    }

    // -------------------------------------------------------------------------
    // getBans
    // -------------------------------------------------------------------------

    public function testGetBansReturnsGlobalBans(): void
    {
        $this->insert('phorum_banlists', ['forum_id' => 0, 'type' => 1, 'pcre' => 0, 'string' => 'evil.com', 'comments' => '']);

        $mapper = $this->makeMapper();
        $bans   = $mapper->getBans(1, 5);
        $this->assertCount(1, $bans);
        $this->assertSame('evil.com', $bans[0]['string']);
    }

    public function testGetBansIncludesForumSpecificBans(): void
    {
        $this->insert('phorum_banlists', ['forum_id' => 10, 'type' => 2, 'pcre' => 0, 'string' => 'spammer', 'comments' => '']);
        $this->insert('phorum_banlists', ['forum_id' => 20, 'type' => 2, 'pcre' => 0, 'string' => 'other',   'comments' => '']);

        $mapper = $this->makeMapper();
        $bans   = $mapper->getBans(2, 10);
        $this->assertCount(1, $bans);
        $this->assertSame('spammer', $bans[0]['string']);
    }

    public function testGetBansExcludesEmptyStrings(): void
    {
        $this->insert('phorum_banlists', ['forum_id' => 0, 'type' => 1, 'pcre' => 0, 'string' => '', 'comments' => '']);

        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->getBans(1, 0));
    }

    public function testGetBansReturnsBothGlobalAndForumBans(): void
    {
        $this->insert('phorum_banlists', ['forum_id' => 0,  'type' => 3, 'pcre' => 0, 'string' => 'global',  'comments' => '']);
        $this->insert('phorum_banlists', ['forum_id' => 15, 'type' => 3, 'pcre' => 0, 'string' => 'local',   'comments' => '']);
        $this->insert('phorum_banlists', ['forum_id' => 99, 'type' => 3, 'pcre' => 0, 'string' => 'unrelated', 'comments' => '']);

        $mapper = $this->makeMapper();
        $bans   = $mapper->getBans(3, 15);
        $this->assertCount(2, $bans);
        $strings = array_column($bans, 'string');
        $this->assertContains('global', $strings);
        $this->assertContains('local',  $strings);
    }

    public function testGetBansReturnsEmptyWhenNoMatch(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->getBans(1, 1));
    }
}

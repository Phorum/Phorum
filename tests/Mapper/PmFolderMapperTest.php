<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\PmFolderMapper;
use Phorum\Model\PmFolder;

class PmFolderMapperTest extends MapperTestCase
{
    private function makeMapper(): PmFolderMapper
    {
        return new class extends PmFolderMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public function testSaveInsertAndLoad(): void
    {
        $mapper = $this->makeMapper();
        $f = new PmFolder();
        $f->user_id    = 1;
        $f->foldername = 'Work';
        $mapper->save($f);

        $this->assertGreaterThan(0, $f->pm_folder_id);
        $loaded = $mapper->load($f->pm_folder_id);
        $this->assertSame('Work', $loaded->foldername);
    }

    public function testDelete(): void
    {
        $this->insert('phorum_pm_folders', ['user_id' => 1, 'foldername' => 'Trash']);
        $id = (int) self::$pdo->lastInsertId();
        $mapper = $this->makeMapper();
        $mapper->delete($id);
        $this->assertNull($mapper->load($id));
    }

    // -------------------------------------------------------------------------
    // findByUser
    // -------------------------------------------------------------------------

    public function testFindByUserReturnsOnlyUserFolders(): void
    {
        $this->insert('phorum_pm_folders', ['user_id' => 1, 'foldername' => 'Inbox']);
        $this->insert('phorum_pm_folders', ['user_id' => 1, 'foldername' => 'Work']);
        $this->insert('phorum_pm_folders', ['user_id' => 2, 'foldername' => 'Other']);

        $mapper  = $this->makeMapper();
        $results = $mapper->findByUser(1);
        $this->assertCount(2, $results);
        // ordered by foldername ASC
        $this->assertSame('Inbox', $results[0]->foldername);
        $this->assertSame('Work',  $results[1]->foldername);
    }

    public function testFindByUserReturnsEmptyWhenNone(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->findByUser(99));
    }
}

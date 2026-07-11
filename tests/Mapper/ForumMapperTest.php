<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\ForumMapper;
use Phorum\Model\Forum;

class ForumMapperTest extends MapperTestCase
{
    private function makeMapper(): ForumMapper
    {
        return new class extends ForumMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    private function seedForum(array $override = []): int
    {
        return $this->insert('phorum_forums', array_merge([
            'name'        => 'Test Forum',
            'active'      => 1,
            'folder_flag' => 0,
            'parent_id'   => 0,
            'vroot'       => 0,
            'display_order' => 0,
        ], $override));
    }

    // -------------------------------------------------------------------------
    // CRUD via parent
    // -------------------------------------------------------------------------

    public function testSaveInsertAndLoadById(): void
    {
        $mapper = $this->makeMapper();
        $f      = new Forum();
        $f->name   = 'General';
        $f->active = 1;
        $mapper->save($f);

        $this->assertGreaterThan(0, $f->forum_id);
        $loaded = $mapper->load($f->forum_id);
        $this->assertInstanceOf(Forum::class, $loaded);
        $this->assertSame('General', $loaded->name);
    }

    public function testSaveUpdate(): void
    {
        $id = $this->seedForum(['name' => 'Old Name']);
        $mapper = $this->makeMapper();
        $f = $mapper->load($id);
        $f->name = 'New Name';
        $mapper->save($f);

        $this->assertSame('New Name', $mapper->load($id)->name);
    }

    public function testDelete(): void
    {
        $id = $this->seedForum();
        $mapper = $this->makeMapper();
        $mapper->delete($id);
        $this->assertNull($mapper->load($id));
    }

    public function testLoadMissingReturnsNull(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->load(9999));
    }

    // -------------------------------------------------------------------------
    // findVisible
    // -------------------------------------------------------------------------

    public function testFindVisibleReturnsActiveForumsAtRoot(): void
    {
        $this->seedForum(['name' => 'Active',   'active' => 1, 'parent_id' => 0]);
        $this->seedForum(['name' => 'Inactive', 'active' => 0, 'parent_id' => 0]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findVisible(0);
        $this->assertCount(1, $results);
        $this->assertSame('Active', $results[0]->name);
    }

    public function testFindVisibleFiltersParentId(): void
    {
        $this->seedForum(['name' => 'Root',  'active' => 1, 'parent_id' => 0]);
        $this->seedForum(['name' => 'Child', 'active' => 1, 'parent_id' => 5]);

        $mapper = $this->makeMapper();
        $this->assertCount(1, $mapper->findVisible(0));
        $this->assertCount(1, $mapper->findVisible(5));
    }

    public function testFindVisibleReturnsNullWhenEmpty(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->findVisible(0));
    }

    // -------------------------------------------------------------------------
    // findForumsInVroot
    // -------------------------------------------------------------------------

    public function testFindForumsInVrootExcludesFolders(): void
    {
        $this->seedForum(['name' => 'Forum',  'active' => 1, 'vroot' => 1, 'folder_flag' => 0]);
        $this->seedForum(['name' => 'Folder', 'active' => 1, 'vroot' => 1, 'folder_flag' => 1]);

        $mapper  = $this->makeMapper();
        $results = $mapper->findForumsInVroot(1);
        $this->assertCount(1, $results);
        $this->assertSame('Forum', $results[0]->name);
    }

    // -------------------------------------------------------------------------
    // recalcStats
    // -------------------------------------------------------------------------

    public function testRecalcStatsUpdatesCountsFromMessages(): void
    {
        $fid = $this->seedForum(['message_count' => 0, 'thread_count' => 0]);
        // seed two approved thread starters and one reply
        $t1 = $this->insert('phorum_messages', ['forum_id' => $fid, 'parent_id' => 0, 'thread' => 1, 'status' => 2, 'modifystamp' => 100]);
        $this->insert('phorum_messages', ['forum_id' => $fid, 'parent_id' => 0, 'thread' => 2, 'status' => 2, 'modifystamp' => 200]);
        $this->insert('phorum_messages', ['forum_id' => $fid, 'parent_id' => $t1, 'thread' => $t1, 'status' => 2, 'modifystamp' => 150]);

        $mapper = $this->makeMapper();
        $mapper->recalcStats($fid);

        $forum = $mapper->load($fid);
        $this->assertSame(3, $forum->message_count);
        $this->assertSame(2, $forum->thread_count);
        $this->assertSame(200, $forum->last_post_time);
    }

    public function testRecalcStatsIgnoresDeletedMessages(): void
    {
        $fid = $this->seedForum();
        $this->insert('phorum_messages', ['forum_id' => $fid, 'parent_id' => 0, 'thread' => 1, 'status' => -1, 'modifystamp' => 50]);

        $mapper = $this->makeMapper();
        $mapper->recalcStats($fid);

        $forum = $mapper->load($fid);
        $this->assertSame(0, $forum->message_count);
    }

    // -------------------------------------------------------------------------
    // loadMulti
    // -------------------------------------------------------------------------

    public function testLoadMulti(): void
    {
        $id1 = $this->seedForum(['name' => 'A']);
        $id2 = $this->seedForum(['name' => 'B']);

        $mapper  = $this->makeMapper();
        $results = $mapper->loadMulti([$id1, $id2]);
        $this->assertCount(2, $results);
    }

    public function testLoadMultiEmptyReturnsNull(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->loadMulti([]));
    }

    // -------------------------------------------------------------------------
    // find with invalid column throws
    // -------------------------------------------------------------------------

    public function testFindWithUnknownColumnThrows(): void
    {
        $mapper = $this->makeMapper();
        $this->expectException(\InvalidArgumentException::class);
        $mapper->find(['nonexistent_col' => 1]);
    }
}

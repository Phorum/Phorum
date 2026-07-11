<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Mapper\ForumMapper;
use Phorum\Model\Forum;
use Phorum\Service\ForumService;
use PHPUnit\Framework\TestCase;

class ForumServiceTest extends TestCase
{
    private function makeForum(int $id, int $parentId, bool $folder = false, string $name = ''): Forum
    {
        $f              = new Forum();
        $f->forum_id    = $id;
        $f->parent_id   = $parentId;
        $f->folder_flag = $folder ? 1 : 0;
        $f->name        = $name ?: "Forum{$id}";
        $f->active      = 1;
        return $f;
    }

    private function makeService(?array $forums): ForumService
    {
        $mapper = $this->createMock(ForumMapper::class);
        $mapper->method('find')->willReturn($forums);
        return new ForumService($mapper);
    }

    public function testEmptyResultReturnsEmptyArray(): void
    {
        $svc = $this->makeService(null);
        $this->assertSame([], $svc->getTree());
    }

    public function testFlatForumsAtRoot(): void
    {
        $svc  = $this->makeService([
            $this->makeForum(1, 0),
            $this->makeForum(2, 0),
        ]);
        $tree = $svc->getTree();
        $this->assertCount(2, $tree);
        $this->assertSame(1, $tree[0]->forum_id);
        $this->assertSame(2, $tree[1]->forum_id);
    }

    public function testFolderReceivesChildren(): void
    {
        $folder = $this->makeForum(1, 0, folder: true);
        $child1 = $this->makeForum(2, 1);
        $child2 = $this->makeForum(3, 1);

        $svc  = $this->makeService([$folder, $child1, $child2]);
        $tree = $svc->getTree();

        $this->assertCount(1, $tree);
        $this->assertSame(1, $tree[0]->forum_id);
        $this->assertCount(2, $tree[0]->children);
        $this->assertSame(2, $tree[0]->children[0]->forum_id);
        $this->assertSame(3, $tree[0]->children[1]->forum_id);
    }

    public function testRegularForumChildrenArrayRemainsEmpty(): void
    {
        $svc  = $this->makeService([$this->makeForum(1, 0, folder: false)]);
        $tree = $svc->getTree();
        $this->assertSame([], $tree[0]->children);
    }

    public function testNestedFolderBuildsDeepTree(): void
    {
        $root   = $this->makeForum(1, 0, folder: true);
        $sub    = $this->makeForum(2, 1, folder: true);
        $leaf   = $this->makeForum(3, 2);

        $svc  = $this->makeService([$root, $sub, $leaf]);
        $tree = $svc->getTree();

        $this->assertCount(1, $tree);
        $this->assertCount(1, $tree[0]->children);
        $this->assertSame(2, $tree[0]->children[0]->forum_id);
        $this->assertCount(1, $tree[0]->children[0]->children);
        $this->assertSame(3, $tree[0]->children[0]->children[0]->forum_id);
    }

    public function testMixedRootAndFolderChildren(): void
    {
        $folder = $this->makeForum(1, 0, folder: true);
        $child  = $this->makeForum(2, 1);
        $rootForum = $this->makeForum(3, 0);

        $svc  = $this->makeService([$folder, $child, $rootForum]);
        $tree = $svc->getTree();

        $this->assertCount(2, $tree);
    }
}

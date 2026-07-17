<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\ForumMapper;
use Phorum\Model\Forum;
use Phorum\Model\User;

class ForumService
{
    public function __construct(private readonly ForumMapper $forums) {}

    /**
     * Flattened, permission-filtered forum IDs (folders excluded) that $user
     * can read — used by the site-wide RSS/Atom feed to scope its recent-
     * messages query to only what the current viewer is allowed to see.
     *
     * @return int[]
     */
    public function getReadableForumIds(?User $user, PermissionService $perms): array
    {
        $ids = [];
        $this->collectReadableIds($this->getTree(), $user, $perms, $ids);
        return $ids;
    }

    /** @param Forum[] $forums */
    private function collectReadableIds(array $forums, ?User $user, PermissionService $perms, array &$ids): void
    {
        foreach ($forums as $forum) {
            if ($forum->folder_flag) {
                $this->collectReadableIds($forum->children, $user, $perms, $ids);
                continue;
            }
            if ($perms->canRead($forum, $user)) {
                $ids[] = $forum->forum_id;
            }
        }
    }

    /**
     * Return a forum tree as a flat list of Forum objects, each with its
     * children populated. Folders get their sub-forums in $forum->children;
     * regular forums have an empty children array. Pass a folder's forum_id
     * as $parentId to get just that folder's subtree instead of the full
     * tree from the root.
     *
     * @return Forum[]
     */
    public function getTree(int $parentId = 0): array
    {
        $all = $this->forums->find(
            filter: ['active' => 1],
            order:  'display_order ASC, name ASC'
        );

        if ($all === null) {
            return [];
        }

        // Index all forums by forum_id and group children by parent_id
        $byId     = [];
        $byParent = [];
        foreach ($all as $forum) {
            $byId[$forum->forum_id]            = $forum;
            $byParent[$forum->parent_id][]     = $forum;
        }

        return $this->buildLevel($byParent, $parentId);
    }

    /** @return Forum[] */
    private function buildLevel(array $byParent, int $parentId): array
    {
        $nodes = [];
        foreach ($byParent[$parentId] ?? [] as $forum) {
            if ($forum->folder_flag) {
                $forum->children = $this->buildLevel($byParent, $forum->forum_id);
            }
            $nodes[] = $forum;
        }
        return $nodes;
    }
}

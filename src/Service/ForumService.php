<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\ForumMapper;
use Phorum\Model\Forum;

class ForumService
{
    public function __construct(private readonly ForumMapper $forums) {}

    /**
     * Return the full forum tree as a flat list of top-level Forum objects,
     * each with its children populated. Folders get their sub-forums in
     * $forum->children; regular forums have an empty children array.
     *
     * @return Forum[]
     */
    public function getTree(): array
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

        return $this->buildLevel($byParent, 0);
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

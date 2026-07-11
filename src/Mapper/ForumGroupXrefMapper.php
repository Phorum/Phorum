<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\ForumGroupXref;

class ForumGroupXrefMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = ForumGroupXref::class;
    public const PRIMARY_KEY  = 'forum_group_xref_id';
    public const TABLE_BASE   = 'forum_group_xref';

    public const MAPPING = [
        'forum_group_xref_id' => ['read_only' => true],
        'forum_id'             => [],
        'group_id'             => [],
        'permission'           => [],
    ];

    /** All forum grants for a group. */
    public function findByGroup(int $groupId): ?array
    {
        return $this->find(filter: ['group_id' => $groupId]);
    }

    public function findByForumAndGroup(int $forumId, int $groupId): ?ForumGroupXref
    {
        $rows = $this->find(filter: ['forum_id' => $forumId, 'group_id' => $groupId], limit: 1);
        return $rows[0] ?? null;
    }

    /** Grant (or update) a group's permission bitmask on a forum. */
    public function setPermission(int $forumId, int $groupId, int $permission): void
    {
        $row = $this->findByForumAndGroup($forumId, $groupId);
        if ($row === null) {
            $row           = new ForumGroupXref();
            $row->forum_id = $forumId;
            $row->group_id = $groupId;
        }
        $row->permission = $permission;
        $this->save($row);
    }

    public function removePermission(int $forumId, int $groupId): void
    {
        $row = $this->findByForumAndGroup($forumId, $groupId);
        if ($row !== null) {
            $this->delete($row->forum_group_xref_id);
        }
    }
}

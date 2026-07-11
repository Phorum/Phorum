<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use DealNews\DB\CRUD;

/**
 * Queries the user_permissions, user_group_xref, and forum_group_xref tables
 * for per-user per-forum permission lookups.
 *
 * Not a full data-mapper (no model class) — only lookup queries needed.
 */
class UserPermissionMapper
{
    private ?CRUD $crud = null;

    /**
     * Return the explicit per-user permission for a forum, or null if no
     * individual override exists.
     */
    public function getDirectPermission(int $userId, int $forumId): ?int
    {
        $rows = $this->crud()->runFetch(
            'SELECT permission FROM ' . $this->table('user_permissions')
            . ' WHERE user_id = :user_id AND forum_id = :forum_id',
            [':user_id' => $userId, ':forum_id' => $forumId]
        );
        return empty($rows) ? null : (int) $rows[0]['permission'];
    }

    /**
     * Return the combined (BIT_OR) group permission for a user in a forum.
     * Only groups where the user has an active or moderator status (>= 1)
     * are included. Returns 0 if the user has no group access to this forum.
     */
    public function getGroupPermission(int $userId, int $forumId): int
    {
        $rows = $this->crud()->runFetch(
            'SELECT BIT_OR(fgx.permission) AS perm'
            . ' FROM '    . $this->table('user_group_xref')  . ' ugx'
            . ' JOIN '    . $this->table('forum_group_xref') . ' fgx ON fgx.group_id = ugx.group_id'
            . ' WHERE ugx.user_id  = :user_id'
            . '   AND fgx.forum_id = :forum_id'
            . '   AND ugx.status  >= 1',
            [':user_id' => $userId, ':forum_id' => $forumId]
        );
        return (int) ($rows[0]['perm'] ?? 0);
    }

    private function table(string $base): string
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        return $prefix . '_' . $base;
    }

    private function crud(): CRUD
    {
        if ($this->crud === null) {
            $db         = defined('PHORUM_DB') ? PHORUM_DB : 'phorum';
            $this->crud = CRUD::factory($db);
        }
        return $this->crud;
    }
}

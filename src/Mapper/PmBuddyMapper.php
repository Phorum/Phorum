<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\PmBuddy;

class PmBuddyMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = PmBuddy::class;
    public const PRIMARY_KEY  = 'pm_buddy_id';
    public const TABLE_BASE   = 'pm_buddies';

    public const MAPPING = [
        'pm_buddy_id'   => ['read_only' => true],
        'user_id'       => [],
        'buddy_user_id' => [],
    ];

    /**
     * Add a buddy relationship. Silently ignores duplicates.
     */
    public function add(int $userId, int $buddyUserId): void
    {
        $this->crud()->run(
            'INSERT IGNORE INTO ' . $this->table() . ' (user_id, buddy_user_id) VALUES (:uid, :bid)',
            [':uid' => $userId, ':bid' => $buddyUserId]
        );
    }

    /**
     * Remove a buddy relationship.
     */
    public function remove(int $userId, int $buddyUserId): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table() . ' WHERE user_id = :uid AND buddy_user_id = :bid',
            [':uid' => $userId, ':bid' => $buddyUserId]
        );
    }

    /**
     * Check whether $userId has $buddyUserId in their buddy list.
     */
    public function isBuddy(int $userId, int $buddyUserId): bool
    {
        $rows = $this->crud()->runFetch(
            'SELECT 1 FROM ' . $this->table() . ' WHERE user_id = :uid AND buddy_user_id = :bid LIMIT 1',
            [':uid' => $userId, ':bid' => $buddyUserId]
        );
        return !empty($rows);
    }

    /**
     * Return all buddies for a user, joined with basic user data.
     * Each row contains: pm_buddy_id, buddy_user_id, username, display_name,
     * date_last_active, mutual (1|0).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listBuddies(int $userId): array
    {
        $usersTable = $this->usersTable();
        $sql = 'SELECT b.pm_buddy_id, b.buddy_user_id,'
             . '       u.username, u.display_name, u.date_last_active,'
             . '       EXISTS('
             . '           SELECT 1 FROM ' . $this->table() . ' rb'
             . '           WHERE rb.user_id = b.buddy_user_id AND rb.buddy_user_id = b.user_id'
             . '       ) AS mutual'
             . ' FROM ' . $this->table() . ' b'
             . ' JOIN ' . $usersTable . ' u ON u.user_id = b.buddy_user_id'
             . ' WHERE b.user_id = :uid'
             . ' ORDER BY u.username ASC';

        return $this->crud()->runFetch($sql, [':uid' => $userId]) ?: [];
    }

    private function usersTable(): string
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        return $prefix . '_users';
    }
}

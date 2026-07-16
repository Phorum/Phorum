<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use DealNews\DB\CRUD;

/**
 * Manages the user_newflags table — matches Phorum 6's phorum_user_newflags
 * exactly.
 *
 * Read/unread semantics (mirrors phorum_db_newflag_get_flags()):
 *   UNREAD: message_id > min_id  AND  message_id NOT IN user_newflags
 *   READ:   message_id <= min_id  OR  message_id IN user_newflags
 *
 * min_id is not stored — like Phorum 6, it's derived on the fly as
 * MIN(message_id) over the user's current flags for that forum (see
 * getMinFlagId()). Pruning the oldest flags (deleteOldest()) naturally
 * raises this derived value; there is nothing else to update.
 */
class NewflagMapper
{
    public const MAX_FLAGS = 1000;

    private ?CRUD $crud = null;

    protected function crud(): CRUD
    {
        if ($this->crud === null) {
            $db         = defined('PHORUM_DB') ? PHORUM_DB : 'phorum';
            $this->crud = CRUD::factory($db);
        }
        return $this->crud;
    }

    private function prefix(): string
    {
        return defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
    }

    private function table(): string
    {
        return $this->prefix() . '_user_newflags';
    }

    /**
     * Return all flagged-read message IDs for a user in a forum.
     * Returns [message_id => true] lookup map.
     *
     * @return array<int,bool>
     */
    public function getFlags(int $userId, int $forumId): array
    {
        $rows = $this->crud()->runFetch(
            'SELECT message_id FROM ' . $this->table()
            . ' WHERE user_id = :uid AND forum_id = :fid',
            [':uid' => $userId, ':fid' => $forumId]
        );
        $flags = [];
        foreach ($rows ?: [] as $row) {
            $flags[(int) $row['message_id']] = true;
        }
        return $flags;
    }

    public function countFlags(int $userId, int $forumId): int
    {
        $rows = $this->crud()->runFetch(
            'SELECT COUNT(*) AS cnt FROM ' . $this->table()
            . ' WHERE user_id = :uid AND forum_id = :fid',
            [':uid' => $userId, ':fid' => $forumId]
        );
        return empty($rows) ? 0 : (int) $rows[0]['cnt'];
    }

    /**
     * Mark message IDs as read via INSERT IGNORE.
     * IDs are int-cast before SQL interpolation — never from raw user input.
     *
     * @param int[] $messageIds
     */
    public function addFlags(int $userId, int $forumId, array $messageIds): void
    {
        if (empty($messageIds)) {
            return;
        }
        $table  = $this->table();
        $values = implode(', ', array_map(
            fn($id) => "({$userId}, {$forumId}, " . (int) $id . ')',
            $messageIds
        ));
        $this->crud()->run(
            "INSERT IGNORE INTO {$table} (user_id, forum_id, message_id) VALUES {$values}",
            []
        );
    }

    /**
     * Delete the N oldest (lowest message_id) flags for a user/forum.
     */
    public function deleteOldest(int $userId, int $forumId, int $count): void
    {
        if ($count <= 0) {
            return;
        }
        $this->crud()->run(
            'DELETE FROM ' . $this->table()
            . ' WHERE user_id = :uid AND forum_id = :fid ORDER BY message_id ASC LIMIT ' . $count,
            [':uid' => $userId, ':fid' => $forumId]
        );
    }

    /**
     * Minimum message_id among all remaining flags for a user/forum.
     * Returns 0 if no flags remain.
     */
    public function getMinFlagId(int $userId, int $forumId): int
    {
        $rows = $this->crud()->runFetch(
            'SELECT MIN(message_id) AS min_id FROM ' . $this->table()
            . ' WHERE user_id = :uid AND forum_id = :fid',
            [':uid' => $userId, ':fid' => $forumId]
        );
        return empty($rows) ? 0 : (int) ($rows[0]['min_id'] ?? 0);
    }

    public function deleteAllFlags(int $userId, int $forumId): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table()
            . ' WHERE user_id = :uid AND forum_id = :fid',
            [':uid' => $userId, ':fid' => $forumId]
        );
    }

    /**
     * Re-key newflag rows for the given message ids from $oldForumId to
     * $newForumId. Used when a thread-merge moves messages into a different
     * forum — without this, already-read state stays keyed to the forum the
     * messages used to live in and those posts reappear as unread.
     *
     * @param int[] $messageIds
     */
    public function moveForumForMessages(int $oldForumId, int $newForumId, array $messageIds): void
    {
        if (empty($messageIds)) {
            return;
        }
        $ids = implode(', ', array_map('intval', $messageIds));
        $this->crud()->run(
            'UPDATE ' . $this->table()
            . " SET forum_id = :new WHERE forum_id = :old AND message_id IN ({$ids})",
            [':new' => $newForumId, ':old' => $oldForumId]
        );
    }

    /**
     * Count unread messages per forum in a single query.
     * Returns [forum_id => count] — only forums with unread messages appear.
     *
     * @param  int[] $forumIds
     * @return array<int,int>
     */
    public function countNewPerForum(int $userId, array $forumIds): array
    {
        if (empty($forumIds)) {
            return [];
        }
        $prefix = $this->prefix();
        $mTable = $prefix . '_messages';
        $fTable = $this->table();
        $in     = implode(',', array_map('intval', $forumIds));

        // "n" derives each forum's min_id on the fly (lowest flagged message_id
        // for this user), same as getMinFlagId() but for many forums at once.
        $sql = "SELECT m.forum_id, COUNT(*) AS new_count"
             . " FROM {$mTable} m"
             . " LEFT JOIN ("
             . "     SELECT forum_id, MIN(message_id) AS min_id"
             . "     FROM {$fTable}"
             . "     WHERE user_id = :uid1"
             . "     GROUP BY forum_id"
             . " ) n ON n.forum_id = m.forum_id"
             . " LEFT JOIN {$fTable} f ON f.user_id = :uid2 AND f.forum_id = m.forum_id"
             . "   AND f.message_id = m.message_id"
             . " WHERE m.forum_id IN ({$in})"
             . "   AND m.status = 2"
             . "   AND m.message_id > COALESCE(n.min_id, 0)"
             . "   AND f.message_id IS NULL"
             . " GROUP BY m.forum_id";

        $rows   = $this->crud()->runFetch($sql, [':uid1' => $userId, ':uid2' => $userId]);
        $result = [];
        foreach ($rows ?: [] as $row) {
            $result[(int) $row['forum_id']] = (int) $row['new_count'];
        }
        return $result;
    }

    /**
     * Count unread messages per thread within a forum (for the thread listing page).
     * Returns [thread_id => count] — only threads with unread messages appear.
     *
     * @return array<int,int>
     */
    public function countNewInThreads(int $userId, int $forumId, int $minId): array
    {
        $prefix = $this->prefix();
        $mTable = $prefix . '_messages';
        $fTable = $this->table();

        $sql = "SELECT m.thread, COUNT(*) AS new_count"
             . " FROM {$mTable} m"
             . " LEFT JOIN {$fTable} f ON f.user_id = :uid AND f.forum_id = :fid"
             . "   AND f.message_id = m.message_id"
             . " WHERE m.forum_id = :fid"
             . "   AND m.status = 2"
             . "   AND m.message_id > :mid"
             . "   AND f.message_id IS NULL"
             . " GROUP BY m.thread";

        $rows   = $this->crud()->runFetch($sql, [
            ':uid' => $userId,
            ':fid' => $forumId,
            ':mid' => $minId,
        ]);
        $result = [];
        foreach ($rows ?: [] as $row) {
            $result[(int) $row['thread']] = (int) $row['new_count'];
        }
        return $result;
    }

    /**
     * Maximum approved message_id in a forum.
     * Used when marking a forum as fully read.
     */
    public function getMaxMessageId(int $forumId): int
    {
        $rows = $this->crud()->runFetch(
            'SELECT MAX(message_id) AS max_id FROM ' . $this->prefix() . '_messages'
            . ' WHERE forum_id = :fid AND status = 2',
            [':fid' => $forumId]
        );
        return empty($rows) ? 0 : (int) ($rows[0]['max_id'] ?? 0);
    }
}

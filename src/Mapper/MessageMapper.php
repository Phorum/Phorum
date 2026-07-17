<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\Message;

class MessageMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = Message::class;
    public const PRIMARY_KEY  = 'message_id';
    public const TABLE_BASE   = 'messages';

    // Status constants matching the old Phorum schema
    public const STATUS_APPROVED   = 2;
    public const STATUS_UNAPPROVED = 0;
    public const STATUS_DELETED    = -1;

    // Sort constants
    public const SORT_DEFAULT    = 2;
    public const SORT_STICKY     = 1;
    public const SORT_ANNOUNCE   = 0;

    public const MAPPING = [
        'message_id'       => ['read_only' => true],
        'forum_id'         => [],
        'thread'           => [],
        'parent_id'        => [],
        'user_id'          => [],
        'author'           => [],
        'subject'          => [],
        'body'             => [],
        'email'            => [],
        'ip'               => [],
        'status'           => [],
        'msgid'            => [],
        'modifystamp'      => [],
        'thread_count'     => [],
        'moderator_post'   => [],
        'sort'             => [],
        'datestamp'        => [],
        'meta'             => [],
        'viewcount'        => [],
        'threadviewcount'  => [],
        'closed'           => [],
        'recent_message_id' => [],
        'recent_user_id'   => [],
        'recent_author'    => [],
        'moved'            => [],
        'hide_period'      => [],
    ];

    /**
     * Return all thread starters (parent_id = 0) in a forum, approved,
     * ordered by last activity descending — the thread list page.
     */
    public function findThreadsInForum(int $forumId, int $limit = 25, int $offset = 0): ?array
    {
        $sql    = 'SELECT * FROM ' . $this->table()
                . ' WHERE forum_id = :forum_id'
                . '   AND status = :status'
                . '   AND parent_id = 0'
                . ' ORDER BY sort DESC, modifystamp DESC'
                . " LIMIT {$limit} OFFSET {$offset}";
        $params = [
            ':forum_id' => $forumId,
            ':status'   => self::STATUS_APPROVED,
        ];
        $rows = $this->crud()->runFetch($sql, $params);
        return empty($rows) ? null : array_map(fn($r) => $this->setData($r), $rows);
    }

    /**
     * Return unapproved messages awaiting moderation across a set of forums,
     * oldest first so the queue is worked in a fair order.
     *
     * @param int[] $forumIds
     */
    public function findUnapprovedInForums(array $forumIds, int $limit = 100): ?array
    {
        if (empty($forumIds)) {
            return null;
        }

        $params = [':status' => self::STATUS_UNAPPROVED];
        $ids    = [];
        foreach (array_values($forumIds) as $i => $forumId) {
            $key         = ":fid{$i}";
            $params[$key] = $forumId;
            $ids[]        = $key;
        }

        $sql = 'SELECT * FROM ' . $this->table()
             . ' WHERE status = :status'
             . '   AND forum_id IN (' . implode(', ', $ids) . ')'
             . ' ORDER BY datestamp ASC'
             . " LIMIT {$limit}";

        $rows = $this->crud()->runFetch($sql, $params);
        return empty($rows) ? null : array_map(fn($r) => $this->setData($r), $rows);
    }

    /**
     * Return all approved messages in a thread (the thread starter plus all replies).
     */
    public function findByThread(int $threadId): ?array
    {
        $sql    = 'SELECT * FROM ' . $this->table()
                . ' WHERE thread = :thread'
                . '   AND status = :status'
                . ' ORDER BY datestamp ASC';
        $params = [
            ':thread' => $threadId,
            ':status' => self::STATUS_APPROVED,
        ];
        $rows = $this->crud()->runFetch($sql, $params);
        return empty($rows) ? null : array_map(fn($r) => $this->setData($r), $rows);
    }

    /** After inserting a root post, self-reference its thread field. */
    public function setThreadId(int $messageId): void
    {
        $sql = 'UPDATE ' . $this->table()
             . ' SET thread = :id WHERE message_id = :id';
        $this->crud()->run($sql, [':id' => $messageId]);
    }

    /**
     * Update the thread root's activity stats after any new post in the thread.
     * thread_count is the total number of messages in the thread (including the root).
     */
    public function updateThreadStats(
        int    $threadId,
        int    $now,
        int    $recentMsgId,
        int    $recentUserId,
        string $recentAuthor,
    ): void {
        $sql = 'UPDATE ' . $this->table()
             . ' SET thread_count      = thread_count + 1'
             . ',    modifystamp       = :now'
             . ',    recent_message_id = :recent_msg'
             . ',    recent_user_id    = :recent_user'
             . ',    recent_author     = :recent_author'
             . ' WHERE message_id = :thread_id';
        $this->crud()->run($sql, [
            ':now'           => $now,
            ':recent_msg'    => $recentMsgId,
            ':recent_user'   => $recentUserId,
            ':recent_author' => $recentAuthor,
            ':thread_id'     => $threadId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Moderation helpers
    // -------------------------------------------------------------------------

    /** Return all message IDs in a thread regardless of status. */
    public function findIdsByThread(int $threadId): array
    {
        $rows = $this->crud()->runFetch(
            'SELECT message_id FROM ' . $this->table() . ' WHERE thread = :thread',
            [':thread' => $threadId]
        );
        return empty($rows) ? [] : array_column($rows, 'message_id');
    }

    /** Set the approval status of a single message. */
    public function setStatus(int $messageId, int $status): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table() . ' SET status = :status WHERE message_id = :id',
            [':status' => $status, ':id' => $messageId]
        );
    }

    /** Set the status of every message in a thread. */
    public function setStatusForThread(int $threadId, int $status): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table() . ' SET status = :status WHERE thread = :thread',
            [':status' => $status, ':thread' => $threadId]
        );
    }

    /**
     * Re-parent the direct children of a deleted message so the thread
     * structure remains coherent.
     */
    public function reparentChildren(int $oldParentId, int $newParentId, int $forumId): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table()
            . ' SET parent_id = :new_parent'
            . ' WHERE forum_id = :forum_id AND parent_id = :old_parent',
            [':new_parent' => $newParentId, ':forum_id' => $forumId, ':old_parent' => $oldParentId]
        );
    }

    /** Set the closed flag on every message in a thread. */
    public function setClosedForThread(int $threadId, int $closed): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table() . ' SET closed = :closed WHERE thread = :thread',
            [':closed' => $closed, ':thread' => $threadId]
        );
    }

    /** Set the sort order on a thread root (SORT_STICKY or SORT_DEFAULT). */
    public function setSortForThread(int $threadId, int $sort): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table() . ' SET sort = :sort WHERE message_id = :thread',
            [':sort' => $sort, ':thread' => $threadId]
        );
    }

    /** Move every message in a thread to a different forum. */
    public function setForumForThread(int $threadId, int $forumId): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table() . ' SET forum_id = :forum_id WHERE thread = :thread',
            [':forum_id' => $forumId, ':thread' => $threadId]
        );
    }

    /**
     * Fold every message of $sourceThreadId into $targetThreadId, preserving
     * message ids (unlike Phorum 6's clone-and-delete merge, this keeps
     * permalinks, search rows, and newflags valid with no extra bookkeeping).
     * The old source root becomes a reply under the target's root.
     */
    public function mergeThread(int $sourceThreadId, int $targetThreadId, int $targetForumId): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table()
            . ' SET thread = :target, forum_id = :forum_id'
            . ' WHERE thread = :source',
            [':target' => $targetThreadId, ':forum_id' => $targetForumId, ':source' => $sourceThreadId]
        );
        $this->crud()->run(
            'UPDATE ' . $this->table()
            . ' SET parent_id = :target'
            . ' WHERE message_id = :source',
            [':target' => $targetThreadId, ':source' => $sourceThreadId]
        );
    }

    /**
     * Recalculate the thread root's aggregated stats from the current
     * approved-message set. Call after any delete/approve operation.
     */
    public function recalcThreadStats(int $threadId): void
    {
        $agg = $this->crud()->runFetch(
            'SELECT COUNT(*) AS cnt, COALESCE(MAX(datestamp), 0) AS max_ts'
            . ' FROM ' . $this->table()
            . ' WHERE thread = :thread AND status = :status',
            [':thread' => $threadId, ':status' => self::STATUS_APPROVED]
        );

        if (empty($agg) || (int) $agg[0]['cnt'] === 0) {
            return; // Entire thread was soft-deleted — nothing to update
        }

        $count = (int) $agg[0]['cnt'];
        $maxTs = (int) $agg[0]['max_ts'];

        // Find the message that produced max_ts (deterministic: highest message_id wins ties)
        $recent = $this->crud()->runFetch(
            'SELECT message_id, user_id, author FROM ' . $this->table()
            . ' WHERE thread = :thread AND status = :status AND datestamp = :ts'
            . ' ORDER BY message_id DESC LIMIT 1',
            [':thread' => $threadId, ':status' => self::STATUS_APPROVED, ':ts' => $maxTs]
        );

        if (empty($recent)) return;
        $r = $recent[0];

        $this->crud()->run(
            'UPDATE ' . $this->table()
            . ' SET thread_count      = :cnt,'
            . '     modifystamp       = :ts,'
            . '     recent_message_id = :rmid,'
            . '     recent_user_id    = :ruid,'
            . '     recent_author     = :ra'
            . ' WHERE message_id = :thread_id',
            [
                ':cnt'       => $count,
                ':ts'        => $maxTs,
                ':rmid'      => (int) $r['message_id'],
                ':ruid'      => (int) $r['user_id'],
                ':ra'        => $r['author'],
                ':thread_id' => $threadId,
            ]
        );
    }

    /**
     * Increment viewcount and threadviewcount on the thread root in one query.
     * Called each time a thread page is rendered.
     */
    public function incrementViewCounts(int $rootMessageId): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table()
            . ' SET viewcount = viewcount + 1, threadviewcount = threadviewcount + 1'
            . ' WHERE message_id = :id',
            [':id' => $rootMessageId]
        );
    }

    /**
     * Return the single most recent post by a user, any status — used for
     * flood control, where even a pending/deleted post should still count.
     */
    public function findLastByUser(int $userId): ?object
    {
        $sql    = 'SELECT * FROM ' . $this->table()
                . ' WHERE user_id = :user_id'
                . ' ORDER BY message_id DESC'
                . ' LIMIT 1';
        $rows = $this->crud()->runFetch($sql, [':user_id' => $userId]);
        return empty($rows) ? null : $this->setData($rows[0]);
    }

    /** Return the most recent approved posts by a specific user, newest first. */
    public function findByUser(int $userId, int $limit = 10): ?array
    {
        $sql    = 'SELECT * FROM ' . $this->table()
                . ' WHERE user_id = :user_id AND status = :status'
                . ' ORDER BY datestamp DESC'
                . " LIMIT {$limit}";
        $params = [':user_id' => $userId, ':status' => self::STATUS_APPROVED];
        $rows   = $this->crud()->runFetch($sql, $params);
        return empty($rows) ? null : array_map(fn($r) => $this->setData($r), $rows);
    }

    /** Return the most recent approved messages across all forums, for a front-page feed. */
    public function findRecent(int $limit = 20): ?array
    {
        $sql    = 'SELECT * FROM ' . $this->table()
                . ' WHERE status = :status'
                . ' ORDER BY datestamp DESC'
                . " LIMIT {$limit}";
        $params = [':status' => self::STATUS_APPROVED];
        $rows   = $this->crud()->runFetch($sql, $params);
        return empty($rows) ? null : array_map(fn($r) => $this->setData($r), $rows);
    }

    /**
     * Return the most recent approved messages, restricted to the given set of
     * forum IDs — used by the site-wide RSS/Atom feed, where the caller has
     * already resolved which forums the current viewer can read. Unlike
     * findRecent(), this never returns unfiltered cross-forum results.
     *
     * @param int[] $forumIds
     */
    public function findRecentInForums(array $forumIds, int $limit = 30): ?array
    {
        if (empty($forumIds)) {
            return null;
        }

        $params = [':status' => self::STATUS_APPROVED];
        $ids    = [];
        foreach (array_values($forumIds) as $i => $forumId) {
            $key          = ":fid{$i}";
            $params[$key] = $forumId;
            $ids[]        = $key;
        }

        $sql = 'SELECT * FROM ' . $this->table()
             . ' WHERE status = :status'
             . '   AND forum_id IN (' . implode(', ', $ids) . ')'
             . ' ORDER BY datestamp DESC'
             . " LIMIT {$limit}";

        $rows = $this->crud()->runFetch($sql, $params);
        return empty($rows) ? null : array_map(fn($r) => $this->setData($r), $rows);
    }
}

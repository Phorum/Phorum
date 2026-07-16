<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\Subscriber;

/**
 * The subscribers table uses a composite primary key (user_id, forum_id, thread).
 * Standard save/load/delete from AbstractPhorumMapper do not apply here.
 * Use the purpose-built methods below instead.
 */
class SubscriberMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = Subscriber::class;
    public const PRIMARY_KEY  = 'user_id'; // satisfies abstract — not used directly
    public const TABLE_BASE   = 'subscribers';

    public const MAPPING = [
        'user_id'  => [],
        'forum_id' => [],
        'thread'   => [],
        'sub_type' => [],
    ];

    // Subscription type constants matching old Phorum
    public const SUB_NONE     = -1;
    public const SUB_MESSAGE  =  0;  // email notifications
    public const SUB_DIGEST   =  1;  // digest (not actively used)
    public const SUB_BOOKMARK =  2;  // bookmark only, no email

    /** Insert or update a subscription row. */
    public function subscribe(int $userId, int $forumId, int $thread, int $subType): void
    {
        $params = [':uid' => $userId, ':fid' => $forumId, ':thread' => $thread, ':type' => $subType];
        try {
            $this->crud()->run(
                'INSERT INTO ' . $this->table()
                . ' (user_id, forum_id, thread, sub_type) VALUES (:uid, :fid, :thread, :type)',
                $params
            );
        } catch (\Exception $e) {
            if (!str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }
            $this->crud()->run(
                'UPDATE ' . $this->table()
                . ' SET sub_type = :type WHERE user_id = :uid AND forum_id = :fid AND thread = :thread',
                $params
            );
        }
    }

    /** Remove a subscription row. */
    public function unsubscribe(int $userId, int $forumId, int $thread): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table()
            . ' WHERE user_id = :uid AND forum_id = :fid AND thread = :thread',
            [':uid' => $userId, ':fid' => $forumId, ':thread' => $thread]
        );
    }

    /**
     * Remove every subscription for a thread (e.g. a thread being merged
     * away — matches Phorum 6's own behavior of dropping rather than
     * migrating subscriptions on merge).
     */
    public function deleteForThread(int $forumId, int $thread): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table() . ' WHERE forum_id = :fid AND thread = :thread',
            [':fid' => $forumId, ':thread' => $thread]
        );
    }

    /**
     * Return the sub_type for a user/forum/thread combination, or null if not subscribed.
     */
    public function getSubscription(int $userId, int $forumId, int $thread): ?int
    {
        $rows = $this->crud()->runFetch(
            'SELECT sub_type FROM ' . $this->table()
            . ' WHERE user_id = :uid AND forum_id = :fid AND thread = :thread',
            [':uid' => $userId, ':fid' => $forumId, ':thread' => $thread]
        );
        return empty($rows) ? null : (int) $rows[0]['sub_type'];
    }

    /**
     * Return all active users who should receive email for a post in the given
     * forum+thread. Combines thread-level and forum-level (thread=0) subscribers
     * with sub_type = SUB_MESSAGE, excluding the post author.
     *
     * Returns rows: [user_id, email, display_name, username]
     */
    public function listEmailSubscribers(int $forumId, int $thread, int $excludeUserId): array
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $sql    = 'SELECT DISTINCT u.user_id, u.email, u.display_name, u.username'
                . ' FROM '  . $this->table()                 . ' s'
                . ' JOIN '  . $prefix . '_users u ON u.user_id = s.user_id'
                . ' WHERE s.forum_id  = :fid'
                . '   AND (s.thread   = :thread OR s.thread = 0)'
                . '   AND s.sub_type  = :type'
                . '   AND u.active    = 1'
                . '   AND u.user_id  != :exclude';
        return $this->crud()->runFetch($sql, [
            ':fid'     => $forumId,
            ':thread'  => $thread,
            ':type'    => self::SUB_MESSAGE,
            ':exclude' => $excludeUserId,
        ]) ?: [];
    }
}

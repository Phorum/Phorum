<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\Forum;

class ForumMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = Forum::class;
    public const PRIMARY_KEY  = 'forum_id';
    public const TABLE_BASE   = 'forums';

    public const MAPPING = [
        'forum_id'                 => ['read_only' => true],
        'name'                     => [],
        'active'                   => [],
        'description'              => [],
        'template'                 => [],
        'folder_flag'              => [],
        'parent_id'                => [],
        'list_length_flat'         => [],
        'list_length_threaded'     => [],
        'moderation'               => [],
        'threaded_list'            => [],
        'threaded_read'            => [],
        'float_to_top'             => [],
        'check_duplicate'          => [],
        'allow_attachment_types'   => [],
        'max_attachment_size'      => [],
        'max_totalattachment_size' => [],
        'max_attachments'          => [],
        'pub_perms'                => [],
        'reg_perms'                => [],
        'display_ip_address'       => [],
        'allow_email_notify'       => [],
        'language'                 => [],
        'email_moderators'         => [],
        'message_count'            => [],
        'sticky_count'             => [],
        'thread_count'             => [],
        'last_post_time'           => [],
        'display_order'            => [],
        'read_length'              => [],
        'vroot'                    => [],
        'forum_path'               => [],
        'count_views'              => [],
        'count_views_per_thread'   => [],
        'display_fixed'            => [],
        'reverse_threading'        => [],
        'inherit_id'               => [],
        'cache_version'            => [],
    ];

    /** Return all active top-level forums and folders ordered for display. */
    public function findVisible(int $parentId = 0): ?array
    {
        return $this->find(
            filter: ['active' => 1, 'parent_id' => $parentId],
            order:  'display_order ASC, name ASC'
        );
    }

    /** Return active forums under a given vroot, folders excluded. */
    public function findForumsInVroot(int $vroot): ?array
    {
        return $this->find(
            filter: ['active' => 1, 'vroot' => $vroot, 'folder_flag' => 0],
            order:  'display_order ASC, name ASC'
        );
    }

    /**
     * Recompute message_count, thread_count, and last_post_time from the
     * current approved-message set. Called after any delete/approve/move.
     */
    public function recalcStats(int $forumId): void
    {
        $msgTable = (defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum') . '_messages';

        $rows = $this->crud()->runFetch(
            'SELECT COUNT(*) AS total,'
            . '     SUM(parent_id = 0) AS threads,'
            . '     COALESCE(MAX(modifystamp), 0) AS last_ts'
            . ' FROM ' . $msgTable
            . ' WHERE forum_id = :fid AND status = 2',
            [':fid' => $forumId]
        );

        if (empty($rows)) return;
        $s = $rows[0];

        $this->crud()->run(
            'UPDATE ' . $this->table()
            . ' SET message_count = :mc, thread_count = :tc, last_post_time = :lpt'
            . ' WHERE forum_id = :fid',
            [
                ':mc'  => (int) $s['total'],
                ':tc'  => (int) $s['threads'],
                ':lpt' => (int) $s['last_ts'],
                ':fid' => $forumId,
            ]
        );
    }

    /** Increment message/thread counts and update last_post_time after a new post. */
    public function updateStats(int $forumId, int $now, bool $newThread): void
    {
        $sql = 'UPDATE ' . $this->table()
             . ' SET message_count = message_count + 1'
             . ',    last_post_time = GREATEST(last_post_time, :now)'
             . ($newThread ? ', thread_count = thread_count + 1' : '')
             . ' WHERE forum_id = :forum_id';
        $this->crud()->run($sql, [':now' => $now, ':forum_id' => $forumId]);
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\ModLog;

class ModLogMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = ModLog::class;
    public const PRIMARY_KEY  = 'mod_log_id';
    public const TABLE_BASE   = 'mod_log';

    public const MAPPING = [
        'mod_log_id'  => ['read_only' => true],
        'user_id'     => [],
        'forum_id'    => [],
        'action'      => [],
        'object_type' => [],
        'object_id'   => [],
        'details'     => [],
        'time'        => [],
    ];

    /** Record a moderator action. */
    public function record(
        int    $userId,
        string $action,
        string $objectType,
        int    $objectId,
        int    $forumId,
        string $details = '',
    ): void {
        $entry              = new ModLog();
        $entry->user_id     = $userId;
        $entry->forum_id    = $forumId;
        $entry->action      = $action;
        $entry->object_type = $objectType;
        $entry->object_id   = $objectId;
        $entry->details     = $details;
        $entry->time        = time();
        $this->save($entry);
    }

    /** Return the most recent log entries, newest first. */
    public function findRecent(int $limit = 200): ?array
    {
        $sql  = 'SELECT * FROM ' . $this->table() . " ORDER BY time DESC LIMIT {$limit}";
        $rows = $this->crud()->runFetch($sql, []);
        return empty($rows) ? null : array_map(fn($r) => $this->setData($r), $rows);
    }
}

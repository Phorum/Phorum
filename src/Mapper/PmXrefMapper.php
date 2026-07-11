<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\PmXref;

class PmXrefMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = PmXref::class;
    public const PRIMARY_KEY  = 'pm_xref_id';
    public const TABLE_BASE   = 'pm_xref';

    public const MAPPING = [
        'pm_xref_id'     => ['read_only' => true],
        'user_id'        => [],
        'pm_message_id'  => [],
        'pm_folder_id'   => [],
        'special_folder' => [],
        'read_flag'      => [],
        'reply_flag'     => [],
    ];

    /**
     * List all xref rows for a built-in folder (inbox/outbox), joined with
     * message metadata. Returns raw rows containing both xref and message fields.
     */
    public function listBySpecialFolder(int $userId, string $specialFolder): array
    {
        $sql    = 'SELECT x.*, m.author, m.subject, m.datestamp'
                . ' FROM '    . $this->table() . ' x'
                . ' JOIN '    . $this->msgTable() . ' m ON m.pm_message_id = x.pm_message_id'
                . ' WHERE x.user_id        = :user_id'
                . '   AND x.special_folder = :sf'
                . ' ORDER BY m.datestamp DESC';
        $params = [':user_id' => $userId, ':sf' => $specialFolder];
        return $this->crud()->runFetch($sql, $params) ?: [];
    }

    /**
     * List all xref rows for a custom folder, joined with message metadata.
     */
    public function listByCustomFolder(int $userId, int $pmFolderId): array
    {
        $sql    = 'SELECT x.*, m.author, m.subject, m.datestamp'
                . ' FROM '    . $this->table() . ' x'
                . ' JOIN '    . $this->msgTable() . ' m ON m.pm_message_id = x.pm_message_id'
                . ' WHERE x.user_id     = :user_id'
                . '   AND x.pm_folder_id = :fid'
                . '   AND x.special_folder = \'\''
                . ' ORDER BY m.datestamp DESC';
        $params = [':user_id' => $userId, ':fid' => $pmFolderId];
        return $this->crud()->runFetch($sql, $params) ?: [];
    }

    /**
     * Load a single xref row and verify it belongs to the given user.
     */
    public function findForUser(int $pmXrefId, int $userId): ?PmXref
    {
        $xref = $this->load($pmXrefId);
        if ($xref === null || $xref->user_id !== $userId) {
            return null;
        }
        return $xref;
    }

    /** Mark a single xref row as read. */
    public function markRead(int $pmXrefId): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table() . ' SET read_flag = 1 WHERE pm_xref_id = :id',
            [':id' => $pmXrefId]
        );
    }

    /** Move an xref to a custom folder (clears special_folder). */
    public function moveToFolder(int $pmXrefId, int $pmFolderId): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table()
            . ' SET pm_folder_id = :fid, special_folder = \'\''
            . ' WHERE pm_xref_id = :id',
            [':fid' => $pmFolderId, ':id' => $pmXrefId]
        );
    }

    /** Move all messages from a custom folder to the user's inbox. */
    public function moveAllToInbox(int $userId, int $pmFolderId): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table()
            . ' SET pm_folder_id = 0, special_folder = \'inbox\''
            . ' WHERE user_id = :uid AND pm_folder_id = :fid',
            [':uid' => $userId, ':fid' => $pmFolderId]
        );
    }

    private function msgTable(): string
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        return $prefix . '_pm_messages';
    }
}

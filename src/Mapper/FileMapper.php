<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\File;

class FileMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = File::class;
    public const PRIMARY_KEY  = 'file_id';
    public const TABLE_BASE   = 'files';

    public const MAPPING = [
        'file_id'      => ['read_only' => true],
        'user_id'      => [],
        'filename'     => [],
        'filesize'     => [],
        'file_data'    => [],
        'add_datetime' => [],
        'message_id'   => [],
        'link'         => [],
        'mime_type'    => [],
        'meta'         => [],
    ];

    /**
     * Return all files attached to a message, ordered by file_id ascending.
     *
     * @return File[]
     */
    public function findByMessage(int $messageId): array
    {
        $rows = $this->crud()->runFetch(
            'SELECT * FROM ' . $this->table()
            . ' WHERE message_id = :mid AND link = :link ORDER BY file_id ASC',
            [':mid' => $messageId, ':link' => File::LINK_MESSAGE]
        );
        return $rows ? array_map(fn($r) => $this->setData($r), $rows) : [];
    }

    /**
     * Return all files for a set of message IDs in one query.
     *
     * @param  int[]        $messageIds
     * @return array<int, File[]>  keyed by message_id
     */
    public function findByMessages(array $messageIds): array
    {
        if (empty($messageIds)) {
            return [];
        }

        $params = [':link' => File::LINK_MESSAGE];
        foreach ($messageIds as $i => $id) {
            $params[":mid{$i}"] = $id;
        }
        $placeholders = implode(', ', array_keys(array_diff_key($params, [':link' => null])));

        $rows = $this->crud()->runFetch(
            'SELECT * FROM ' . $this->table()
            . ' WHERE message_id IN (' . $placeholders . ')'
            . ' AND link = :link ORDER BY file_id ASC',
            $params
        );

        $result = [];
        foreach ($rows as $row) {
            $file = $this->setData($row);
            $result[$file->message_id][] = $file;
        }
        return $result;
    }

    /**
     * Update the link type and message association for a file.
     */
    public function setLink(int $fileId, int $messageId, string $link): void
    {
        $this->crud()->update(
            $this->table(),
            ['message_id' => $messageId, 'link' => $link],
            ['file_id'    => $fileId]
        );
    }

    /**
     * Delete all LINK_MESSAGE files for a given message (used on message delete).
     */
    public function deleteByMessage(int $messageId): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table()
            . ' WHERE message_id = :mid AND link = :link',
            [':mid' => $messageId, ':link' => File::LINK_MESSAGE]
        );
    }

    /**
     * Return the avatar File for a user, or null if none has been uploaded.
     */
    public function findAvatarForUser(int $userId): ?File
    {
        $rows = $this->crud()->runFetch(
            'SELECT * FROM ' . $this->table()
            . ' WHERE user_id = :uid AND link = :link AND message_id = 0 LIMIT 1',
            [':uid' => $userId, ':link' => File::LINK_USER]
        );
        return $rows ? $this->setData($rows[0]) : null;
    }

    /**
     * Find LINK_EDITOR files older than $cutoffTime (orphaned during composition).
     *
     * @return File[]
     */
    public function findStaleEditorFiles(int $cutoffTime): array
    {
        $rows = $this->crud()->runFetch(
            'SELECT * FROM ' . $this->table()
            . ' WHERE link = :link AND add_datetime < :cut',
            [':link' => File::LINK_EDITOR, ':cut' => $cutoffTime]
        );
        return $rows ? array_map(fn($r) => $this->setData($r), $rows) : [];
    }
}

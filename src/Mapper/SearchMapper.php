<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\SearchEntry;

class SearchMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = SearchEntry::class;
    public const PRIMARY_KEY  = 'message_id';
    public const TABLE_BASE   = 'search';

    public const MAPPING = [
        'message_id'  => ['read_only' => true],
        'forum_id'    => [],
        'search_text' => [],
    ];

    /**
     * Insert or update the search index row for a message.
     * search_text mirrors the old Phorum format: "author | subject | body"
     */
    public function indexMessage(
        int    $messageId,
        int    $forumId,
        string $author,
        string $subject,
        string $body,
    ): void {
        $text = $author . ' | ' . $subject . ' | ' . $body;
        try {
            $this->crud()->run(
                'INSERT INTO ' . $this->table()
                . ' (message_id, forum_id, search_text) VALUES (:mid, :fid, :text)',
                [':mid' => $messageId, ':fid' => $forumId, ':text' => $text]
            );
        } catch (\Exception $e) {
            // SQLSTATE 23xxx = constraint violation (23000 MySQL, 23505 PostgreSQL)
            if (!str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }
            $this->crud()->run(
                'UPDATE ' . $this->table()
                . ' SET forum_id = :fid, search_text = :text WHERE message_id = :mid',
                [':mid' => $messageId, ':fid' => $forumId, ':text' => $text]
            );
        }
    }

    /** Remove a single message from the search index. */
    public function removeMessage(int $messageId): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table() . ' WHERE message_id = :id',
            [':id' => $messageId]
        );
    }

    /** Remove all messages belonging to a thread from the search index. */
    public function removeThread(int $threadId): void
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $this->crud()->run(
            'DELETE FROM ' . $this->table()
            . ' WHERE message_id IN ('
            . '   SELECT message_id FROM ' . $prefix . '_messages WHERE thread = :thread'
            . ')',
            [':thread' => $threadId]
        );
    }

    /** Update the forum_id on all index rows for a moved thread. */
    public function updateForum(int $threadId, int $forumId): void
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $this->crud()->run(
            'UPDATE ' . $this->table()
            . ' SET forum_id = :fid'
            . ' WHERE message_id IN ('
            . '   SELECT message_id FROM ' . $prefix . '_messages WHERE thread = :thread'
            . ')',
            [':fid' => $forumId, ':thread' => $threadId]
        );
    }
}

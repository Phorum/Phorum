<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\MessageTracking;

class MessageTrackingMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = MessageTracking::class;
    public const PRIMARY_KEY  = 'track_id';
    public const TABLE_BASE   = 'messages_edittrack';

    public const MAPPING = [
        'track_id'    => ['read_only' => true],
        'message_id'  => [],
        'user_id'     => [],
        'time'        => [],
        'diff_body'   => [],
        'diff_subject'=> [],
    ];

    /**
     * Return all tracking rows for a message, oldest edit first.
     *
     * @return MessageTracking[]
     */
    public function findByMessage(int $messageId): array
    {
        $rows = $this->crud()->runFetch(
            'SELECT * FROM ' . $this->table()
            . ' WHERE message_id = :mid ORDER BY track_id ASC',
            [':mid' => $messageId]
        );

        return array_map(fn($r) => $this->setData($r), $rows ?: []);
    }

    /**
     * Record the state of a message before an edit.
     */
    public function record(int $messageId, int $userId, string $body, string $subject): void
    {
        $t              = new MessageTracking();
        $t->message_id  = $messageId;
        $t->user_id     = $userId;
        $t->time        = time();
        $t->diff_body   = $body;
        $t->diff_subject = $subject;
        $this->save($t);
    }

    /**
     * Delete all tracking rows for a message (called when the message is deleted).
     */
    public function deleteForMessage(int $messageId): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table() . ' WHERE message_id = :mid',
            [':mid' => $messageId]
        );
    }
}

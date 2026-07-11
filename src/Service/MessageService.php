<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\MessageTrackingMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Model\MessageMeta;
use Phorum\Model\User;

class MessageService
{
    public function __construct(
        private readonly MessageMapper $messages,
        private readonly ForumMapper   $forums,
        private readonly ?UserMapper   $users = null,
    ) {}

    /**
     * Post a new thread or reply. Pass parentId = 0 for a new thread, or the
     * message_id of the post being replied to.
     *
     * @throws \InvalidArgumentException if parentId refers to a nonexistent message
     */
    public function post(
        Forum  $forum,
        User   $user,
        string $subject,
        string $body,
        int    $parentId = 0,
    ): Message {
        $now = time();

        $msg              = new Message();
        $msg->forum_id    = $forum->forum_id;
        $msg->user_id     = $user->user_id;
        $msg->author      = $user->display_name !== '' ? $user->display_name : $user->username;
        $msg->email       = $user->email;
        $msg->subject     = $subject;
        $msg->body        = $body;
        $msg->datestamp   = $now;
        $msg->modifystamp = $now;
        $msg->ip          = $_SERVER['REMOTE_ADDR'] ?? '';
        $msg->status      = $forum->moderation > 0
                            ? MessageMapper::STATUS_UNAPPROVED
                            : MessageMapper::STATUS_APPROVED;
        $msg->sort        = MessageMapper::SORT_DEFAULT;
        $msg->msgid       = md5(uniqid((string) $forum->forum_id, more_entropy: true));
        $msg->meta        = MessageMeta::fromArray(['format' => 'markdown'])->encode();

        if ($parentId === 0) {
            $msg->thread    = 0;
            $msg->parent_id = 0;
        } else {
            $parent = $this->messages->load($parentId);
            if ($parent === null) {
                throw new \InvalidArgumentException("Parent message {$parentId} not found.");
            }
            $msg->thread    = $parent->thread;
            $msg->parent_id = $parentId;
        }

        $msg = phorum_api_hook('check_post', $msg);
        $msg = phorum_api_hook('before_post', $msg);

        $msg = $this->messages->save($msg);

        if ($msg->thread === 0) {
            // Root post: set thread = message_id (self-reference)
            $this->messages->setThreadId($msg->message_id);
            $msg->thread = $msg->message_id;
        }

        if ($msg->status === MessageMapper::STATUS_APPROVED) {
            $this->messages->updateThreadStats(
                threadId:    $msg->thread,
                now:         $now,
                recentMsgId: $msg->message_id,
                recentUserId: $user->user_id,
                recentAuthor: $msg->author,
            );

            $this->forums->updateStats(
                forumId:   $forum->forum_id,
                now:       $now,
                newThread: $parentId === 0,
            );

            $this->users?->incrementPostCount($user->user_id);
        }

        phorum_api_hook('after_post', $msg);

        return $msg;
    }

    /**
     * Edit the subject and body of an existing message.
     * Records edit_date and increments edit_count in the meta JSON.
     * Does not touch modifystamp — that tracks last-reply time, not edits.
     *
     * When $tracker and $editorUserId are supplied, the pre-edit content is
     * saved to message_tracking before the message is updated.
     */
    public function edit(
        Message $msg,
        string $subject,
        string $body,
        int $editorUserId = 0,
        ?MessageTrackingMapper $tracker = null,
    ): Message {
        $orig = clone $msg;

        if ($tracker !== null) {
            $tracker->record($msg->message_id, $editorUserId, $msg->body, $msg->subject);
        }

        $editCount    = MessageMeta::decode($msg->meta)->editCount() + 1;
        $msg->subject = $subject;
        $msg->body    = $body;
        $msg->meta    = MessageMeta::decode($msg->meta)
            ->with('edit_date', time())
            ->with('edit_count', $editCount)
            ->encode();

        $msg = phorum_api_hook('before_edit', $msg, $orig);

        $msg = $this->messages->save($msg);
        phorum_api_hook('after_edit', $msg, $orig);

        return $msg;
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Mapper\UserMapper;

class ModerationService
{
    public function __construct(
        private readonly MessageMapper     $messages,
        private readonly ForumMapper       $forums,
        private readonly ?UserMapper       $users       = null,
        private readonly ?SubscriberMapper $subscribers = null,
    ) {}

    /**
     * Soft-delete a single message.
     * If the message is a thread root, the entire thread is deleted instead.
     * Children of a deleted non-root message are re-parented to its parent.
     */
    public function deleteMessage(int $messageId): void
    {
        $msg = $this->messages->load($messageId);
        if ($msg === null) return;

        if ($msg->parent_id === 0) {
            // Root post — delete the whole thread
            $this->deleteThread($messageId);
            return;
        }

        phorum_api_hook('before_delete', $msg);
        $this->messages->reparentChildren($messageId, $msg->parent_id, $msg->forum_id);
        $this->messages->setStatus($messageId, MessageMapper::STATUS_DELETED);
        $this->messages->recalcThreadStats($msg->thread);
        $this->forums->recalcStats($msg->forum_id);
        phorum_api_hook('delete', [$msg->message_id]);
    }

    /** Soft-delete an entire thread (all messages where thread = $threadId). */
    public function deleteThread(int $threadId): void
    {
        $root = $this->messages->load($threadId);
        if ($root === null) return;

        $forumId    = $root->forum_id;
        $messageIds = $this->messages->findIdsByThread($threadId);

        phorum_api_hook('before_delete', $root);
        $this->messages->setStatusForThread($threadId, MessageMapper::STATUS_DELETED);
        $this->forums->recalcStats($forumId);
        phorum_api_hook('delete', $messageIds);
    }

    /** Approve a single unapproved message. */
    public function approveMessage(int $messageId): void
    {
        $msg = $this->messages->load($messageId);
        if ($msg === null) return;

        $this->messages->setStatus($messageId, MessageMapper::STATUS_APPROVED);
        $this->messages->recalcThreadStats($msg->thread);
        $this->forums->recalcStats($msg->forum_id);
        $this->users?->incrementPostCount($msg->user_id);
        $msg->status = MessageMapper::STATUS_APPROVED;
        phorum_api_hook('after_approve', $msg);
    }

    /** Close a thread so no new replies can be posted. */
    public function closeThread(int $threadId): void
    {
        $this->messages->setClosedForThread($threadId, 1);
        phorum_api_hook('close_thread', $threadId);
    }

    /** Reopen a closed thread. */
    public function openThread(int $threadId): void
    {
        $this->messages->setClosedForThread($threadId, 0);
        phorum_api_hook('reopen_thread', $threadId);
    }

    /** Move an entire thread to another forum and recalculate stats for both forums. */
    public function moveThread(int $threadId, int $toForumId): void
    {
        $root = $this->messages->load($threadId);
        if ($root === null || $root->parent_id !== 0) return;

        $fromForumId = $root->forum_id;
        if ($fromForumId === $toForumId) return;

        $this->messages->setForumForThread($threadId, $toForumId);
        $this->forums->recalcStats($fromForumId);
        $this->forums->recalcStats($toForumId);
        phorum_api_hook('move_thread', $threadId, $fromForumId, $toForumId);
    }

    /**
     * Fold $sourceThreadId into $targetThreadId (both must be real thread
     * roots, and they must differ). Source-thread subscriptions are dropped
     * rather than migrated, matching Phorum 6's own merge behavior. Returns
     * false (no-op) if either id doesn't identify a valid, distinct thread.
     */
    public function mergeThread(int $sourceThreadId, int $targetThreadId): bool
    {
        if ($sourceThreadId === $targetThreadId) return false;

        $source = $this->messages->load($sourceThreadId);
        if ($source === null || $source->parent_id !== 0) return false;

        $target = $this->messages->load($targetThreadId);
        if ($target === null || $target->parent_id !== 0) return false;

        $sourceForumId = $source->forum_id;
        $targetForumId = $target->forum_id;

        $this->messages->mergeThread($sourceThreadId, $targetThreadId, $targetForumId);
        // The merged-in messages keep whatever `closed` value they had in the
        // source thread; reconcile them to the surviving (target) thread's
        // state so editability/reply-eligibility matches the thread they now live in.
        $this->messages->setClosedForThread($targetThreadId, $target->closed);
        $this->subscribers?->deleteForThread($sourceForumId, $sourceThreadId);
        $this->messages->recalcThreadStats($targetThreadId);
        $this->forums->recalcStats($sourceForumId);
        if ($sourceForumId !== $targetForumId) {
            $this->forums->recalcStats($targetForumId);
        }
        phorum_api_hook('after_merge', [$sourceThreadId, $targetThreadId]);

        return true;
    }

    /** Sticky or un-sticky a thread. */
    public function stickyThread(int $threadId, bool $sticky): void
    {
        $sort = $sticky ? MessageMapper::SORT_STICKY : MessageMapper::SORT_DEFAULT;
        $this->messages->setSortForThread($threadId, $sort);
        phorum_api_hook('make_sticky', $threadId);
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\NewflagMapper;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\Message;

class ModerationService
{
    public function __construct(
        private readonly MessageMapper     $messages,
        private readonly ForumMapper       $forums,
        private readonly ?UserMapper       $users       = null,
        private readonly ?SubscriberMapper $subscribers = null,
        private readonly ?NewflagMapper    $newflags    = null,
        private readonly ?FileService      $files       = null,
    ) {}

    /**
     * Soft-delete a single message, along with its attachments.
     * If the message is a thread root, the entire thread is deleted instead.
     * Children of a deleted non-root message are re-parented to its parent
     * and keep their own attachments.
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
        $this->users?->incrementDeletedCount($msg->user_id);
        $this->files?->deleteForMessage($messageId);
        phorum_api_hook('delete', [$msg->message_id]);
    }

    /** Soft-delete an entire thread (all messages where thread = $threadId) and all of its attachments. */
    public function deleteThread(int $threadId): void
    {
        $root = $this->loadThreadRoot($threadId);
        if ($root === null) return;

        $forumId    = $root->forum_id;
        $messageIds = $this->messages->findIdsByThread($threadId);

        phorum_api_hook('before_delete', $root);
        $this->messages->setStatusForThread($threadId, MessageMapper::STATUS_DELETED);
        $this->forums->recalcStats($forumId);
        $this->incrementDeletedCountForAuthors($messageIds);
        $this->files?->deleteForMessages($messageIds);
        phorum_api_hook('delete', $messageIds);
    }

    /**
     * Track a moderator-deleted thread against each distinct author in it —
     * a user with 3 messages in the deleted thread gets deleted_count += 3,
     * matching how `posts` already counts per-message, not per-action.
     */
    private function incrementDeletedCountForAuthors(array $messageIds): void
    {
        if ($this->users === null || empty($messageIds)) return;

        $messages = $this->messages->loadMulti($messageIds) ?? [];
        $counts   = [];
        foreach ($messages as $msg) {
            $counts[$msg->user_id] = ($counts[$msg->user_id] ?? 0) + 1;
        }
        foreach ($counts as $userId => $count) {
            $this->users->incrementDeletedCount($userId, $count);
        }
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
        $root = $this->loadThreadRoot($threadId);
        if ($root === null) return;

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

        $source = $this->loadThreadRoot($sourceThreadId);
        if ($source === null) return false;

        $target = $this->loadThreadRoot($targetThreadId);
        if ($target === null) return false;

        $sourceForumId    = $source->forum_id;
        $targetForumId    = $target->forum_id;
        $sourceMessageIds = $this->messages->findIdsByThread($sourceThreadId);

        $this->messages->mergeThread($sourceThreadId, $targetThreadId, $targetForumId);
        // The merged-in messages keep whatever `closed` value they had in the
        // source thread; reconcile them to the surviving (target) thread's
        // state so editability/reply-eligibility matches the thread they now live in.
        $this->messages->setClosedForThread($targetThreadId, $target->closed);
        if ($sourceForumId !== $targetForumId) {
            // Newflags are keyed by (user, forum, message) — without this,
            // already-read state stays under the old forum_id and these
            // posts reappear as unread now that they live in a new forum.
            $this->newflags?->moveForumForMessages($sourceForumId, $targetForumId, $sourceMessageIds);
        }
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

    /** Load a message and confirm it's a real thread root (parent_id === 0), or null otherwise. */
    private function loadThreadRoot(int $id): ?Message
    {
        $msg = $this->messages->load($id);
        return ($msg !== null && $msg->parent_id === 0) ? $msg : null;
    }
}

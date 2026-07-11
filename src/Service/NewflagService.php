<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\NewflagMapper;

class NewflagService
{
    public function __construct(private readonly NewflagMapper $mapper) {}

    /**
     * Mark a set of messages as read for a user in a forum.
     *
     * Enforces the per-forum 1000-flag limit: when exceeded, the oldest flags
     * are deleted and min_id is bumped so those messages remain "read".
     *
     * @param int[] $messageIds
     */
    public function markRead(int $userId, int $forumId, array $messageIds): void
    {
        if (empty($messageIds) || $userId === 0) {
            return;
        }

        $minId  = $this->mapper->getMinId($userId, $forumId);
        $toFlag = array_values(array_filter($messageIds, fn($id) => (int) $id > $minId));

        if (empty($toFlag)) {
            return;
        }

        $current = $this->mapper->countFlags($userId, $forumId);
        $total   = $current + count($toFlag);

        if ($total > NewflagMapper::MAX_FLAGS) {
            $toDelete = $total - NewflagMapper::MAX_FLAGS;
            $this->mapper->deleteOldest($userId, $forumId, $toDelete);
            // Slide min_id up to cover the deleted flags so they stay "read"
            $newMin = $this->mapper->getMinFlagId($userId, $forumId);
            if ($newMin > 0) {
                $this->mapper->setMinId($userId, $forumId, $newMin);
            }
        }

        $this->mapper->addFlags($userId, $forumId, $toFlag);
    }

    /**
     * Mark all messages in a forum as read.
     * Clears per-message flags and sets min_id to the current max message_id.
     */
    public function markForumRead(int $userId, int $forumId): void
    {
        if ($userId === 0) {
            return;
        }
        $maxId = $this->mapper->getMaxMessageId($forumId);
        $this->mapper->deleteAllFlags($userId, $forumId);
        if ($maxId > 0) {
            $this->mapper->setMinId($userId, $forumId, $maxId);
        }
    }

    /**
     * Return the subset of $messageIds that are unread for the user.
     * Result is a flat array suitable for Twig's `in` operator.
     *
     * @param  int[] $messageIds  Approved message IDs to check
     * @return int[]
     */
    public function getNewMessageIds(int $userId, int $forumId, array $messageIds): array
    {
        if ($userId === 0 || empty($messageIds)) {
            return [];
        }
        $minId = $this->mapper->getMinId($userId, $forumId);
        $flags = $this->mapper->getFlags($userId, $forumId);

        return array_values(array_filter(
            $messageIds,
            fn($id) => (int) $id > $minId && !isset($flags[(int) $id])
        ));
    }

    /**
     * Count unread messages per forum.
     * Returns [forum_id => count] for forums that have unread messages.
     *
     * @param  int[] $forumIds
     * @return array<int,int>
     */
    public function getNewCountsForForums(int $userId, array $forumIds): array
    {
        if ($userId === 0 || empty($forumIds)) {
            return [];
        }
        return $this->mapper->countNewPerForum($userId, $forumIds);
    }

    /**
     * Count unread messages per thread in a forum.
     * Returns [thread_id => count] for threads that have unread messages.
     *
     * @return array<int,int>
     */
    public function getNewCountsForThreads(int $userId, int $forumId): array
    {
        if ($userId === 0) {
            return [];
        }
        $minId = $this->mapper->getMinId($userId, $forumId);
        return $this->mapper->countNewInThreads($userId, $forumId, $minId);
    }
}

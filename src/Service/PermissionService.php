<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\UserPermissionMapper;
use Phorum\Model\Forum;
use Phorum\Model\User;

class PermissionService
{
    // Permission bits — must match the Phorum 6.x schema values
    public const ALLOW_READ               = 1;
    public const ALLOW_REPLY              = 2;
    public const ALLOW_EDIT               = 4;
    public const ALLOW_NEW_TOPIC          = 8;
    public const ALLOW_VIEW_ATTACHMENTS   = 16;
    public const ALLOW_ATTACH             = 32;
    public const ALLOW_MODERATE_MESSAGES  = 64;
    public const ALLOW_MODERATE_USERS     = 128;

    public function __construct(
        private readonly UserPermissionMapper $perms,
        private readonly ?ForumMapper         $forums = null,
    ) {}

    public function canRead(Forum $forum, ?User $user): bool
    {
        return $this->check($forum, $user, self::ALLOW_READ);
    }

    /** True if the user can start a new thread in this forum. */
    public function canNewThread(Forum $forum, ?User $user): bool
    {
        return $this->check($forum, $user, self::ALLOW_NEW_TOPIC);
    }

    /** True if the user can reply to messages in this forum. */
    public function canReply(Forum $forum, ?User $user): bool
    {
        return $this->check($forum, $user, self::ALLOW_REPLY);
    }

    /** True if the user can edit their own posts in this forum. */
    public function canEdit(Forum $forum, ?User $user): bool
    {
        return $this->check($forum, $user, self::ALLOW_EDIT);
    }

    /** True if the user can post anything (new thread OR reply). */
    public function canPost(Forum $forum, ?User $user): bool
    {
        $perm = $this->resolve($forum, $user);
        return ($perm & (self::ALLOW_REPLY | self::ALLOW_NEW_TOPIC)) !== 0;
    }

    public function canModerate(Forum $forum, ?User $user): bool
    {
        return $this->check($forum, $user, self::ALLOW_MODERATE_MESSAGES);
    }

    /** True if the user can view/download this forum's message attachments. */
    public function canViewAttachments(Forum $forum, ?User $user): bool
    {
        return $this->check($forum, $user, self::ALLOW_VIEW_ATTACHMENTS);
    }

    /** True if the user can moderate users' accounts in the context of this forum. */
    public function canModerateUsers(Forum $forum, ?User $user): bool
    {
        return $this->check($forum, $user, self::ALLOW_MODERATE_USERS);
    }

    /**
     * True if the user has ALLOW_MODERATE_USERS on at least one active
     * forum, or is a site admin. User-moderation isn't tied to a specific
     * forum's content (e.g. the pending-registration queue is site-wide),
     * so this is the gate for accessing that queue at all.
     */
    public function canModerateUsersAnywhere(?User $user): bool
    {
        return $this->anyForumGrants($user, self::ALLOW_MODERATE_USERS);
    }

    /**
     * True if the user has ALLOW_MODERATE_MESSAGES on at least one active
     * forum, or is a site admin — the gate for showing message-moderation
     * entry points (Review Queue, Reported Content) on pages that aren't
     * scoped to one specific forum (e.g. the site-wide forum index).
     */
    public function canModerateMessagesAnywhere(?User $user): bool
    {
        return $this->anyForumGrants($user, self::ALLOW_MODERATE_MESSAGES);
    }

    private function anyForumGrants(?User $user, int $flag): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->admin) {
            return true;
        }

        $forums = ($this->forums ?? new ForumMapper())->find(filter: ['active' => 1]) ?? [];
        foreach ($forums as $forum) {
            if ($this->check($forum, $user, $flag)) {
                return true;
            }
        }
        return false;
    }

    /** Check an arbitrary permission bit (or OR-combined bits). */
    public function check(Forum $forum, ?User $user, int $flag): bool
    {
        return ($this->resolve($forum, $user) & $flag) === $flag;
    }

    /**
     * Resolve the effective permission integer for a user in a forum.
     *
     * Priority order (highest to lowest):
     *   1. Admin → unrestricted (PHP_INT_MAX)
     *   2. Inactive user → none (0)
     *   3. Direct per-user entry in user_permissions → use as-is
     *   4. Group memberships in user_group_xref + forum_group_xref → OR-combined
     *   5. Forum defaults: pub_perms (anonymous) or reg_perms (registered)
     */
    private function resolve(Forum $forum, ?User $user): int
    {
        if ($user !== null) {
            if ($user->admin) {
                return PHP_INT_MAX;
            }
            // Only a fully ACTIVE (1) account gets permissions — pending
            // (negative) and explicitly inactive (0) states get none. Must
            // be a strict comparison: PHP treats any non-zero int, including
            // negative pending states, as truthy.
            if ($user->active !== 1) {
                return 0;
            }

            $direct = $this->perms->getDirectPermission($user->user_id, $forum->forum_id);
            if ($direct !== null) {
                return $direct;
            }

            $group = $this->perms->getGroupPermission($user->user_id, $forum->forum_id);
            if ($group > 0) {
                return $group;
            }

            return $forum->reg_perms;
        }

        return $forum->pub_perms;
    }
}

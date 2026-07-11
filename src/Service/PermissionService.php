<?php
declare(strict_types=1);

namespace Phorum\Service;

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
    public const ALLOW_ATTACH             = 32;
    public const ALLOW_MODERATE_MESSAGES  = 64;
    public const ALLOW_MODERATE_USERS     = 128;

    public function __construct(private readonly UserPermissionMapper $perms) {}

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
            if (!$user->active) {
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

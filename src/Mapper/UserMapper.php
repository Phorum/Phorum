<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\User;
use Phorum\Model\UserSettings;

class UserMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = User::class;
    public const PRIMARY_KEY  = 'user_id';
    public const TABLE_BASE   = 'users';

    // `active` states — matches the Phorum 6.x schema values exactly, so an
    // in-place upgrade from a real Phorum 6 database is interpreted correctly
    // without any data migration.
    public const ACTIVE        = 1;
    public const INACTIVE      = 0;
    public const PENDING_MOD   = -1; // needs moderator approval only
    public const PENDING_EMAIL = -2; // needs email confirmation only
    public const PENDING_BOTH  = -3; // needs both email confirmation and moderator approval

    public const MAPPING = [
        'user_id'           => ['read_only' => true],
        'username'          => [],
        'real_name'         => [],
        'display_name'      => [],
        'password'          => [],
        'password_temp'     => [],
        'sessid_lt'         => [],
        'sessid_st'         => [],
        'sessid_st_timeout' => [],
        'email'             => [],
        'email_temp'        => [],
        'hide_email'        => [],
        'active'            => [],
        'signature'         => [],
        'threaded_list'     => [],
        'posts'             => [],
        'admin'             => [],
        'threaded_read'     => [],
        'date_added'        => [],
        'date_last_active'  => [],
        'last_active_forum' => [],
        'hide_activity'     => [],
        'show_signature'    => [],
        'email_notify'      => [],
        'pm_email_notify'   => [],
        'pm_new_count'      => [],
        'tz_offset'         => [],
        'is_dst'            => [],
        'user_language'     => [],
        'user_template'     => [],
        'moderation_email'  => [],
        'settings_data'         => [],
        'moderator_data'        => [],
        'force_password_change' => [],
        'shadow_banned'     => [],
        'deleted_count'     => [],
        'reg_ip'            => [],
    ];

    /**
     * Normalize settings_data to JSON before persisting so PHP-serialized
     * rows from Phorum 6 upgrades are migrated on the next write.
     */
    public function save(object $object): object
    {
        /** @var User $object */
        $object->settings_data = UserSettings::decode($object->settings_data)->encode();
        $object = phorum_api_hook('user_save', $object);
        return parent::save($object);
    }

    public function findByUsername(string $username): ?User
    {
        $rows = $this->find(['username' => $username], limit: 1);
        return $rows[0] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        $rows = $this->find(['email' => $email], limit: 1);
        return $rows[0] ?? null;
    }

    public function findBySessionLt(string $token): ?User
    {
        $rows = $this->find(['sessid_lt' => $token], limit: 1);
        return $rows[0] ?? null;
    }

    public function findBySessionSt(string $token): ?User
    {
        $rows = $this->find(['sessid_st' => $token], limit: 1);
        return $rows[0] ?? null;
    }

    public function findByPasswordTemp(string $token): ?User
    {
        $rows = $this->find(['password_temp' => $token], limit: 1);
        return $rows[0] ?? null;
    }

    public function incrementPostCount(int $userId): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table() . ' SET posts = posts + 1 WHERE user_id = :id',
            [':id' => $userId]
        );
    }

    /** Track a moderator-deleted message against a user — the "bad" side of the karma check. */
    public function incrementDeletedCount(int $userId, int $by = 1): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table() . ' SET deleted_count = deleted_count + :by WHERE user_id = :id',
            [':by' => $by, ':id' => $userId]
        );
    }

    public function incrementNewPmCount(int $userId): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table() . ' SET pm_new_count = pm_new_count + 1 WHERE user_id = :id',
            [':id' => $userId]
        );
    }

    public function decrementNewPmCount(int $userId): void
    {
        $this->crud()->run(
            'UPDATE ' . $this->table()
            . ' SET pm_new_count = GREATEST(0, pm_new_count - 1) WHERE user_id = :id',
            [':id' => $userId]
        );
    }

    /**
     * Return accounts awaiting moderator approval (PENDING_MOD or
     * PENDING_BOTH — not PENDING_EMAIL, since a moderator can't act on those
     * until the user confirms their email first), oldest-name-first for a
     * stable queue order. Site-wide, not forum-scoped — matches legacy
     * Phorum 6's phorum_db_user_get_unapproved().
     */
    public function findPendingModeration(): ?array
    {
        $sql = 'SELECT * FROM ' . $this->table()
             . ' WHERE active IN (:pending_both, :pending_mod)'
             . ' ORDER BY username ASC';

        $rows = $this->crud()->runFetch($sql, [
            ':pending_both' => self::PENDING_BOTH,
            ':pending_mod'  => self::PENDING_MOD,
        ]);
        return empty($rows) ? null : array_map(fn($r) => $this->setData($r), $rows);
    }

    /**
     * Return users for a set of user_ids in one query, keyed by user_id.
     *
     * @param  int[]          $userIds
     * @return array<int,User>
     */
    public function findByIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        $params = [];
        foreach ($userIds as $i => $id) {
            $params[":id{$i}"] = $id;
        }
        $placeholders = implode(', ', array_keys($params));
        $rows         = $this->crud()->runFetch(
            'SELECT * FROM ' . $this->table() . ' WHERE user_id IN (' . $placeholders . ')',
            $params
        );
        $result = [];
        foreach ($rows ?: [] as $row) {
            $user = $this->setData($row);
            $result[$user->user_id] = $user;
        }
        return $result;
    }

    /**
     * Return active users who can moderate the given forum and have
     * moderation_email = 1. Includes site admins plus users with the
     * ALLOW_MODERATE_MESSAGES bit (64) via direct permission or group membership.
     *
     * Returns rows: [user_id, email, display_name, username]
     */
    public function findModeratorsForForum(int $forumId): array
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $sql    = 'SELECT DISTINCT u.user_id, u.email, u.display_name, u.username'
                . ' FROM '   . $this->table() . ' u'
                . ' WHERE u.active           = 1'
                . '   AND u.moderation_email = 1'
                . '   AND ('
                . '       u.admin = 1'
                . '    OR EXISTS ('
                . '           SELECT 1 FROM ' . $prefix . '_user_permissions up'
                . '           WHERE up.user_id  = u.user_id'
                . '             AND up.forum_id = :fid1'
                . '             AND (up.permission & 64) = 64'
                . '       )'
                . '    OR EXISTS ('
                . '           SELECT 1 FROM ' . $prefix . '_user_group_xref ugx'
                . '           JOIN '           . $prefix . '_forum_group_xref fgx'
                . '                ON fgx.group_id = ugx.group_id'
                . '           WHERE ugx.user_id  = u.user_id'
                . '             AND fgx.forum_id = :fid2'
                . '             AND (fgx.permission & 64) = 64'
                . '       )'
                . '   )';
        return $this->crud()->runFetch($sql, [':fid1' => $forumId, ':fid2' => $forumId]) ?: [];
    }
}

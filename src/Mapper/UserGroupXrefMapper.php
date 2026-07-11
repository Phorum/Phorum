<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\UserGroupXref;

class UserGroupXrefMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = UserGroupXref::class;
    public const PRIMARY_KEY  = 'user_group_xref_id';
    public const TABLE_BASE   = 'user_group_xref';

    public const STATUS_SUSPENDED  = -1;
    public const STATUS_UNAPPROVED = 0;
    public const STATUS_APPROVED   = 1;
    public const STATUS_MODERATOR  = 2;

    public const MAPPING = [
        'user_group_xref_id' => ['read_only' => true],
        'user_id'             => [],
        'group_id'            => [],
        'status'              => [],
    ];

    /** All membership rows for a group, any status. */
    public function findByGroup(int $groupId): ?array
    {
        return $this->find(filter: ['group_id' => $groupId]);
    }

    /** All membership rows for a user, any status. */
    public function findByUser(int $userId): ?array
    {
        return $this->find(filter: ['user_id' => $userId]);
    }

    public function findByUserAndGroup(int $userId, int $groupId): ?UserGroupXref
    {
        $rows = $this->find(filter: ['user_id' => $userId, 'group_id' => $groupId], limit: 1);
        return $rows[0] ?? null;
    }

    /** Add a user to a group, or update their status if already a member. */
    public function setMembership(int $userId, int $groupId, int $status): void
    {
        $row = $this->findByUserAndGroup($userId, $groupId);
        if ($row === null) {
            $row           = new UserGroupXref();
            $row->user_id  = $userId;
            $row->group_id = $groupId;
        }
        $row->status = $status;
        $this->save($row);
    }

    public function removeMembership(int $userId, int $groupId): void
    {
        $row = $this->findByUserAndGroup($userId, $groupId);
        if ($row !== null) {
            $this->delete($row->user_group_xref_id);
        }
    }
}

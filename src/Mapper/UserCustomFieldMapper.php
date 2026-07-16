<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use DealNews\DB\CRUD;
use Phorum\Model\UserCustomField;

/**
 * Manages the user_custom_fields table, which has a composite primary key
 * (user_id, type) and does not fit AbstractPhorumMapper.
 */
class UserCustomFieldMapper
{
    private ?CRUD $crud = null;

    protected function crud(): CRUD
    {
        if ($this->crud === null) {
            $db         = defined('PHORUM_DB') ? PHORUM_DB : 'phorum';
            $this->crud = CRUD::factory($db);
        }
        return $this->crud;
    }

    protected function table(): string
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        return $prefix . '_user_custom_fields';
    }

    /**
     * Return all UserCustomField rows for a given user, keyed by the config id (the `type` column).
     *
     * @return UserCustomField[] keyed by config id
     */
    public function loadForUser(int $userId): array
    {
        $rows = $this->crud()->runFetch(
            'SELECT * FROM ' . $this->table() . ' WHERE user_id = :uid',
            [':uid' => $userId]
        );

        $result = [];
        foreach ($rows ?: [] as $row) {
            $f          = new UserCustomField();
            $f->user_id = (int) $row['user_id'];
            $f->type    = (int) $row['type'];
            $f->data    = (string) $row['data'];
            $result[$f->type] = $f;
        }
        return $result;
    }

    /**
     * Upsert a single field value.
     * The PK is composite (user_id, type), so this tries an INSERT first and
     * falls back to an UPDATE on a constraint violation — portable across
     * MySQL/SQLite/Postgres, unlike MySQL-only `ON DUPLICATE KEY UPDATE`.
     */
    public function saveValue(int $userId, int $configId, string $value): void
    {
        $t = $this->table();
        try {
            $this->crud()->run(
                "INSERT INTO {$t} (user_id, `type`, data) VALUES (:uid, :cfg, :data)",
                [':uid' => $userId, ':cfg' => $configId, ':data' => $value]
            );
        } catch (\Exception $e) {
            if (!str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }
            $sth = $this->crud()->run(
                "UPDATE {$t} SET data = :data WHERE user_id = :uid AND `type` = :cfg",
                [':uid' => $userId, ':cfg' => $configId, ':data' => $value]
            );
            if ($sth->rowCount() === 0) {
                // Not actually a duplicate-key conflict (e.g. a genuine FK
                // violation on a nonexistent user/config) — the row we tried
                // to update doesn't exist either.
                throw $e;
            }
        }
    }

    /**
     * Delete all field values for a user.
     */
    public function deleteForUser(int $userId): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table() . ' WHERE user_id = :uid',
            [':uid' => $userId]
        );
    }

    /**
     * Delete all values for a specific config entry (used when hard-deleting a field config).
     */
    public function deleteForConfig(int $configId): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table() . ' WHERE `type` = :cfg',
            [':cfg' => $configId]
        );
    }
}

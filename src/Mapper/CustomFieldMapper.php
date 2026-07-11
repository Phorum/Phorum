<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use DealNews\DB\CRUD;
use Phorum\Model\CustomField;

/**
 * Manages the custom_fields table, which has a composite primary key
 * (relation_id, field_type, type) and does not fit AbstractPhorumMapper.
 */
class CustomFieldMapper
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
        return $prefix . '_custom_fields';
    }

    /**
     * Return all CustomField rows for a given relation and entity type,
     * keyed by the config id (the `type` column).
     *
     * @return CustomField[] keyed by config id
     */
    public function loadForRelation(int $relationId, int $fieldType): array
    {
        $rows = $this->crud()->runFetch(
            'SELECT * FROM ' . $this->table()
            . ' WHERE relation_id = :rid AND field_type = :ft',
            [':rid' => $relationId, ':ft' => $fieldType]
        );

        $result = [];
        foreach ($rows ?: [] as $row) {
            $f              = new CustomField();
            $f->relation_id = (int) $row['relation_id'];
            $f->field_type  = (int) $row['field_type'];
            $f->type        = (int) $row['type'];
            $f->data        = (string) $row['data'];
            $result[$f->type] = $f;
        }
        return $result;
    }

    /**
     * Return all CustomField rows for multiple relations of the same entity type.
     * Result is nested: [relation_id => [config_id => CustomField]]
     *
     * @param  int[] $relationIds
     * @return array<int, array<int, CustomField>>
     */
    public function loadForRelations(array $relationIds, int $fieldType): array
    {
        if (empty($relationIds)) {
            return [];
        }

        $params = [':ft' => $fieldType];
        foreach ($relationIds as $i => $id) {
            $params[":id{$i}"] = (int) $id;
        }
        $placeholders = implode(', ', array_filter(
            array_keys($params),
            fn($k) => $k !== ':ft'
        ));

        $rows = $this->crud()->runFetch(
            'SELECT * FROM ' . $this->table()
            . ' WHERE field_type = :ft AND relation_id IN (' . $placeholders . ')',
            $params
        );

        $result = [];
        foreach ($rows ?: [] as $row) {
            $f              = new CustomField();
            $f->relation_id = (int) $row['relation_id'];
            $f->field_type  = (int) $row['field_type'];
            $f->type        = (int) $row['type'];
            $f->data        = (string) $row['data'];
            $result[$f->relation_id][$f->type] = $f;
        }
        return $result;
    }

    /**
     * Upsert a single field value.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE because the PK is composite.
     */
    public function saveValue(int $relationId, int $fieldType, int $configId, string $value): void
    {
        $t   = $this->table();
        $sql = "INSERT INTO {$t} (relation_id, field_type, `type`, data)"
             . ' VALUES (:rid, :ft, :cfg, :data)'
             . ' ON DUPLICATE KEY UPDATE data = VALUES(data)';
        $this->crud()->run($sql, [
            ':rid'  => $relationId,
            ':ft'   => $fieldType,
            ':cfg'  => $configId,
            ':data' => $value,
        ]);
    }

    /**
     * Delete all field values for a relation + entity type.
     * Call before hard-deleting a config to clean up orphans.
     */
    public function deleteForRelation(int $relationId, int $fieldType): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table()
            . ' WHERE relation_id = :rid AND field_type = :ft',
            [':rid' => $relationId, ':ft' => $fieldType]
        );
    }

    /**
     * Delete all values for a specific config entry (used when hard-deleting a field config).
     */
    public function deleteForConfig(int $configId, int $fieldType): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table()
            . ' WHERE `type` = :cfg AND field_type = :ft',
            [':cfg' => $configId, ':ft' => $fieldType]
        );
    }
}

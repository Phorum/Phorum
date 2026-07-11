<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\CustomFieldConfig;

class CustomFieldConfigMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = CustomFieldConfig::class;
    public const PRIMARY_KEY  = 'id';
    public const TABLE_BASE   = 'custom_fields_config';

    public const MAPPING = [
        'id'            => ['read_only' => true],
        'field_type'    => [],
        'name'          => [],
        'length'        => [],
        'html_disabled' => [],
        'show_in_admin' => [],
        'deleted'       => [],
    ];

    /**
     * Return all field configs of a given type.
     * By default only returns non-deleted fields.
     */
    public function findByFieldType(int $fieldType, bool $includeDeleted = false): array
    {
        $sql = 'SELECT * FROM ' . $this->table()
             . ' WHERE field_type = :ft'
             . ($includeDeleted ? '' : ' AND deleted = 0')
             . ' ORDER BY id ASC';
        $rows = $this->crud()->runFetch($sql, [':ft' => $fieldType]);
        return empty($rows) ? [] : array_map(fn($r) => $this->setData($r), $rows);
    }

    /**
     * Look up a single field config by name + type (including deleted).
     */
    public function findByName(string $name, int $fieldType): ?CustomFieldConfig
    {
        $rows = $this->find(['name' => $name, 'field_type' => $fieldType], limit: 1);
        return $rows[0] ?? null;
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use DealNews\DataMapper\AbstractMapper;
use DealNews\DB\CRUD;

abstract class AbstractPhorumMapper extends AbstractMapper
{
    /** Base table name without prefix, e.g. 'messages'. Set in each subclass. */
    protected const TABLE_BASE = '';

    private ?CRUD $crud = null;

    // -------------------------------------------------------------------------
    // Storage implementation
    // -------------------------------------------------------------------------

    public function load(mixed $id): ?object
    {
        $rows = $this->crud()->read($this->table(), [static::PRIMARY_KEY => $id]);
        return empty($rows) ? null : $this->setData($rows[0]);
    }

    public function loadMulti(array $ids): ?array
    {
        if (empty($ids)) {
            return null;
        }

        $params = [];
        foreach ($ids as $i => $id) {
            $params[":id{$i}"] = $id;
        }
        $placeholders = implode(', ', array_keys($params));
        $sql          = sprintf(
            'SELECT * FROM %s WHERE %s IN (%s)',
            $this->table(),
            static::PRIMARY_KEY,
            $placeholders
        );

        $rows = $this->crud()->runFetch($sql, $params);
        return empty($rows) ? null : array_map(fn($r) => $this->setData($r), $rows);
    }

    /**
     * Find rows matching simple equality filters.
     * For complex queries add specific methods to the concrete mapper.
     */
    public function find(
        array   $filter,
        ?int    $limit  = null,
        ?int    $start  = null,
        string  $order  = ''
    ): ?array {
        $where  = [];
        $params = [];

        foreach ($filter as $column => $value) {
            if (!array_key_exists($column, static::MAPPING)) {
                throw new \InvalidArgumentException("Unknown filter column: {$column}");
            }
            $where[]              = "{$column} = :{$column}";
            $params[":{$column}"] = $value;
        }

        $sql = 'SELECT * FROM ' . $this->table();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        if ($order !== '') {
            $sql .= ' ORDER BY ' . $order;
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($start !== null) {
            $sql .= ' OFFSET ' . $start;
        }

        $rows = $this->crud()->runFetch($sql, $params);
        return empty($rows) ? null : array_map(fn($r) => $this->setData($r), $rows);
    }

    public function save(object $object): object
    {
        $pk   = static::PRIMARY_KEY;
        $data = $this->getData($object);

        if (empty($object->$pk)) {
            $this->crud()->create($this->table(), $data);
            $object->$pk = (int) $this->lastInsertId();
        } else {
            $this->crud()->update($this->table(), $data, [$pk => $object->$pk]);
        }

        return $object;
    }

    public function delete(mixed $id): bool
    {
        return (bool) $this->crud()->delete($this->table(), [static::PRIMARY_KEY => $id]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function table(): string
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        return $prefix . '_' . static::TABLE_BASE;
    }

    protected function crud(): CRUD
    {
        if ($this->crud === null) {
            $db         = defined('PHORUM_DB') ? PHORUM_DB : 'phorum';
            $this->crud = CRUD::factory($db);
        }
        return $this->crud;
    }

    private function lastInsertId(): string
    {
        return (string) $this->crud()->pdo->lastInsertId();
    }
}

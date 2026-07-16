<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\Setting;

class SettingMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = Setting::class;
    public const PRIMARY_KEY  = 'name';
    public const TABLE_BASE   = 'settings';

    public const MAPPING = [
        'name' => [],
        'type' => [],
        'data' => [],
    ];

    /** Return all settings as a decoded key => value map. */
    public function getAll(): array
    {
        $rows = $this->find(filter: []) ?? [];
        $out  = [];
        foreach ($rows as $setting) {
            $out[$setting->name] = $setting->getValue();
        }
        return $out;
    }

    /** Return the decoded value for a single setting, or null if not found. */
    public function getSetting(string $name): mixed
    {
        $rows = $this->find(filter: ['name' => $name], limit: 1);
        return isset($rows[0]) ? $rows[0]->getValue() : null;
    }

    /** Persist a single setting (insert or update). */
    public function saveSetting(string $name, mixed $value): void
    {
        if (is_array($value) || is_object($value)) {
            $type = 'S';
            $data = serialize($value);
        } else {
            $type = 'V';
            $data = (string) $value;
        }

        try {
            $this->crud()->run(
                'INSERT INTO ' . $this->table() . ' (name, type, data) VALUES (:name, :type, :data)',
                [':name' => $name, ':type' => $type, ':data' => $data]
            );
        } catch (\Exception $e) {
            if (!str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }
            $this->crud()->run(
                'UPDATE ' . $this->table() . ' SET type = :type, data = :data WHERE name = :name',
                [':name' => $name, ':type' => $type, ':data' => $data]
            );
        }
    }

    /** Persist multiple settings at once. */
    public function saveAll(array $settings): void
    {
        foreach ($settings as $name => $value) {
            $this->saveSetting($name, $value);
        }
    }

    /**
     * The raw Setting row for a key (undecoded), or null if absent.
     * Used for optimistic-concurrency compare-and-swap on array-valued
     * settings — see compareAndSwap().
     */
    public function getSettingRow(string $name): ?Setting
    {
        $rows = $this->find(filter: ['name' => $name], limit: 1);
        return $rows[0] ?? null;
    }

    /**
     * Write $value under $name only if the row's current raw data still
     * equals $expectedRawData ($expectedRawData === null means "the row
     * must not exist yet"). Returns false if another writer already changed
     * it first — callers should reload (getSettingRow()), re-apply their
     * change, and retry.
     */
    public function compareAndSwap(string $name, ?string $expectedRawData, mixed $value): bool
    {
        if (is_array($value) || is_object($value)) {
            $type = 'S';
            $data = serialize($value);
        } else {
            $type = 'V';
            $data = (string) $value;
        }

        if ($expectedRawData === null) {
            try {
                $this->crud()->run(
                    'INSERT INTO ' . $this->table() . ' (name, type, data) VALUES (:name, :type, :data)',
                    [':name' => $name, ':type' => $type, ':data' => $data]
                );
                return true;
            } catch (\Exception $e) {
                if (!str_starts_with((string) $e->getCode(), '23')) {
                    throw $e;
                }
                return false; // a row appeared concurrently
            }
        }

        $sth = $this->crud()->run(
            'UPDATE ' . $this->table() . ' SET type = :type, data = :data WHERE name = :name AND data = :expected',
            [':name' => $name, ':type' => $type, ':data' => $data, ':expected' => $expectedRawData]
        );
        return $sth->rowCount() > 0;
    }
}

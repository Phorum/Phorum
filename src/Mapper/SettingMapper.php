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
}

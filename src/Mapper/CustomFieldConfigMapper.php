<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\CustomFieldConfig;

/**
 * Manages custom (user profile) field definitions.
 *
 * Matches Phorum 6, where these definitions are not a database table but a
 * serialized array stored under the 'PROFILE_FIELDS' key in the settings
 * table (mirroring phorum_api_custom_profile_field_configure() et al.).
 * 'num_fields' tracks the highest id ever assigned, so ids are never reused
 * after a field is purged.
 */
class CustomFieldConfigMapper
{
    private const SETTING_KEY = 'PROFILE_FIELDS';

    public function __construct(
        private readonly SettingMapper $settings = new SettingMapper(),
    ) {}

    public function load(int $id): ?CustomFieldConfig
    {
        $fields = $this->loadAll();
        return isset($fields[$id]) ? $this->hydrate($id, $fields[$id]) : null;
    }

    /**
     * Return all field configs.
     * By default only returns non-deleted fields.
     *
     * @return CustomFieldConfig[]
     */
    public function findAll(bool $includeDeleted = false): array
    {
        $result = [];
        foreach ($this->loadAll() as $id => $row) {
            if ($id === 'num_fields') {
                continue;
            }
            if (!$includeDeleted && !empty($row['deleted'])) {
                continue;
            }
            $result[] = $this->hydrate((int) $id, $row);
        }

        usort($result, fn($a, $b) => strcmp($a->name, $b->name));
        return $result;
    }

    /**
     * Look up a single field config by name (including deleted).
     */
    public function findByName(string $name): ?CustomFieldConfig
    {
        foreach ($this->findAll(includeDeleted: true) as $config) {
            if ($config->name === $name) {
                return $config;
            }
        }
        return null;
    }

    public function save(CustomFieldConfig $config): CustomFieldConfig
    {
        $fields = $this->loadAll();

        if ($config->id === 0) {
            $high = (int) ($fields['num_fields'] ?? 0);
            foreach ($fields as $id => $row) {
                if ($id !== 'num_fields' && (int) $id > $high) {
                    $high = (int) $id;
                }
            }
            $config->id            = $high + 1;
            $fields['num_fields']  = $config->id;
        }

        $fields[$config->id] = $this->dehydrate($config);
        $this->saveAll($fields);

        return $config;
    }

    public function delete(int $id): bool
    {
        $fields = $this->loadAll();
        if (!isset($fields[$id])) {
            return false;
        }
        unset($fields[$id]);
        $this->saveAll($fields);
        return true;
    }

    private function loadAll(): array
    {
        return $this->settings->getSetting(self::SETTING_KEY) ?? [];
    }

    private function saveAll(array $fields): void
    {
        $this->settings->saveSetting(self::SETTING_KEY, $fields);
    }

    private function hydrate(int $id, array $row): CustomFieldConfig
    {
        $c                = new CustomFieldConfig();
        $c->id            = $id;
        $c->name          = (string) ($row['name'] ?? '');
        $c->length        = (int) ($row['length'] ?? 255);
        $c->html_disabled = !empty($row['html_disabled']) ? 1 : 0;
        $c->show_in_admin = !empty($row['show_in_admin']) ? 1 : 0;
        $c->deleted       = !empty($row['deleted']) ? 1 : 0;
        return $c;
    }

    private function dehydrate(CustomFieldConfig $c): array
    {
        return [
            'name'          => $c->name,
            'length'        => $c->length,
            'html_disabled' => (bool) $c->html_disabled,
            'show_in_admin' => (bool) $c->show_in_admin,
            'deleted'       => (bool) $c->deleted,
        ];
    }
}

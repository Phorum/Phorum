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
    private const SETTING_KEY      = 'PROFILE_FIELDS';
    private const MAX_CAS_ATTEMPTS = 5;

    /** Per-instance cache of the decoded PROFILE_FIELDS blob; kept in sync by mutate(). */
    private ?array $cache = null;

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
        $this->mutate(function (array $fields) use ($config) {
            if ($config->id === 0) {
                $high = (int) ($fields['num_fields'] ?? 0);
                foreach ($fields as $id => $row) {
                    if ($id !== 'num_fields' && (int) $id > $high) {
                        $high = (int) $id;
                    }
                }
                $config->id           = $high + 1;
                $fields['num_fields'] = $config->id;
            }

            $fields[$config->id] = $this->dehydrate($config);
            return $fields;
        });

        return $config;
    }

    public function delete(int $id): bool
    {
        $deleted = false;
        $this->mutate(function (array $fields) use ($id, &$deleted) {
            if (isset($fields[$id])) {
                unset($fields[$id]);
                $deleted = true;
            }
            return $fields;
        });
        return $deleted;
    }

    private function loadAll(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->settings->getSetting(self::SETTING_KEY) ?? [];
        }
        return $this->cache;
    }

    /**
     * Apply $mutate to the current field set and write it back with
     * optimistic concurrency: if another admin saved a different field
     * in between our read and write, the compare-and-swap fails and we
     * reload, re-run $mutate against the fresh state, and retry — instead
     * of silently clobbering their change.
     */
    private function mutate(callable $mutate): void
    {
        for ($attempt = 0; $attempt < self::MAX_CAS_ATTEMPTS; $attempt++) {
            $row    = $this->settings->getSettingRow(self::SETTING_KEY);
            $fields = $row?->getValue() ?? [];

            $updated = $mutate($fields);

            if ($this->settings->compareAndSwap(self::SETTING_KEY, $row?->data, $updated)) {
                $this->cache = $updated;
                return;
            }
        }

        throw new \RuntimeException('Could not save custom field config: too many concurrent updates.');
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

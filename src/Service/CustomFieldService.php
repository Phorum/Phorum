<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\CustomFieldConfigMapper;
use Phorum\Mapper\UserCustomFieldMapper;
use Phorum\Model\CustomFieldConfig;

class CustomFieldService
{
    public function __construct(
        private readonly CustomFieldConfigMapper $configs,
        private readonly UserCustomFieldMapper    $values,
    ) {}

    /**
     * Return active user field configs merged with the user's current values.
     *
     * Each entry: ['config' => CustomFieldConfig, 'value' => string]
     *
     * @return array<int, array{config: CustomFieldConfig, value: string}>
     */
    public function getUserFields(int $userId): array
    {
        $configs = $this->configs->findAll();
        if (empty($configs)) {
            return [];
        }

        $stored = $this->values->loadForUser($userId);

        $result = [];
        foreach ($configs as $config) {
            $raw   = $stored[$config->id]->data ?? '';
            $result[] = [
                'config' => $config,
                'value'  => $config->html_disabled ? htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') : $raw,
            ];
        }
        return $result;
    }

    /**
     * Return active user field configs that have show_in_admin = 1,
     * merged with the user's current values.
     *
     * @return array<int, array{config: CustomFieldConfig, value: string}>
     */
    public function getAdminUserFields(int $userId): array
    {
        return array_values(array_filter(
            $this->getUserFields($userId),
            fn($entry) => $entry['config']->show_in_admin === 1
        ));
    }

    /**
     * Validate and optionally save submitted custom field values for a user.
     * Pass $dryRun = true to validate without writing to the database.
     *
     * @param array<string, string> $data  field_name => submitted value
     * @return string[]  validation errors (empty on success)
     */
    public function saveUserFields(int $userId, array $data, bool $dryRun = false): array
    {
        $configs = $this->configs->findAll();
        $errors  = [];

        foreach ($configs as $config) {
            if (!array_key_exists($config->name, $data)) {
                continue;
            }

            $value = (string) $data[$config->name];

            if (mb_strlen($value) > $config->length) {
                $errors[] = sprintf(
                    '"%s" must be %d characters or fewer.',
                    $config->label(),
                    $config->length
                );
                continue;
            }

            if (!$dryRun) {
                $this->values->saveValue($userId, $config->id, $value);
            }
        }

        return $errors;
    }
}

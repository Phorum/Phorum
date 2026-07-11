<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Mapper\CustomFieldConfigMapper;
use Phorum\Mapper\CustomFieldMapper;
use Phorum\Model\CustomFieldConfig;
use Phorum\Model\Forum;
use Phorum\Model\Message;

class CustomFieldService
{
    public function __construct(
        private readonly CustomFieldConfigMapper $configs,
        private readonly CustomFieldMapper       $values,
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
        $configs = $this->configs->findByFieldType(CustomFieldConfig::FIELD_TYPE_USER);
        if (empty($configs)) {
            return [];
        }

        $stored = $this->values->loadForRelation($userId, CustomFieldConfig::FIELD_TYPE_USER);

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
        $configs = $this->configs->findByFieldType(CustomFieldConfig::FIELD_TYPE_USER);
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
                $this->values->saveValue(
                    $userId,
                    CustomFieldConfig::FIELD_TYPE_USER,
                    $config->id,
                    $value
                );
            }
        }

        return $errors;
    }

    /**
     * Return all active user configs (for building forms).
     *
     * @return CustomFieldConfig[]
     */
    public function getActiveUserConfigs(): array
    {
        return $this->configs->findByFieldType(CustomFieldConfig::FIELD_TYPE_USER);
    }

    /**
     * Populate $forum->custom_fields on each Forum in the array.
     * Keys are field names; values are display-ready strings (HTML-escaped where configured).
     *
     * @param Forum[] $forums
     */
    public function hydrateForums(array $forums): void
    {
        if (empty($forums)) {
            return;
        }

        $configs = $this->configs->findByFieldType(CustomFieldConfig::FIELD_TYPE_FORUM);
        if (empty($configs)) {
            return;
        }

        $configIndex = [];
        foreach ($configs as $config) {
            $configIndex[$config->id] = $config;
        }

        $ids    = array_map(fn(Forum $f) => $f->forum_id, $forums);
        $stored = $this->values->loadForRelations($ids, CustomFieldConfig::FIELD_TYPE_FORUM);

        foreach ($forums as $forum) {
            $forum->custom_fields = [];
            foreach ($stored[$forum->forum_id] ?? [] as $configId => $cf) {
                if (!isset($configIndex[$configId])) {
                    continue;
                }
                $config = $configIndex[$configId];
                $forum->custom_fields[$config->name] = $config->html_disabled
                    ? htmlspecialchars($cf->data, ENT_QUOTES, 'UTF-8')
                    : $cf->data;
            }
        }
    }

    /**
     * Populate $message->custom_fields on each Message in the array.
     * Keys are field names; values are display-ready strings (HTML-escaped where configured).
     *
     * @param Message[] $messages
     */
    public function hydrateMessages(array $messages): void
    {
        if (empty($messages)) {
            return;
        }

        $configs = $this->configs->findByFieldType(CustomFieldConfig::FIELD_TYPE_MESSAGE);
        if (empty($configs)) {
            return;
        }

        $configIndex = [];
        foreach ($configs as $config) {
            $configIndex[$config->id] = $config;
        }

        $ids    = array_map(fn(Message $m) => $m->message_id, $messages);
        $stored = $this->values->loadForRelations($ids, CustomFieldConfig::FIELD_TYPE_MESSAGE);

        foreach ($messages as $message) {
            $message->custom_fields = [];
            foreach ($stored[$message->message_id] ?? [] as $configId => $cf) {
                if (!isset($configIndex[$configId])) {
                    continue;
                }
                $config = $configIndex[$configId];
                $message->custom_fields[$config->name] = $config->html_disabled
                    ? htmlspecialchars($cf->data, ENT_QUOTES, 'UTF-8')
                    : $cf->data;
            }
        }
    }
}

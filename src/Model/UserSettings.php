<?php
declare(strict_types=1);

namespace Phorum\Model;

/**
 * Immutable value object for the users.settings_data column.
 *
 * Old Phorum 6 stored PHP-serialized arrays here. New code writes JSON.
 * decode() accepts both formats so upgrades work without a bulk migration.
 */
class UserSettings
{
    private function __construct(private readonly array $data = [])
    {
    }

    /**
     * Decode a raw settings_data string (JSON or PHP-serialized).
     * Returns an empty instance on null, empty, or unparseable input.
     */
    public static function decode(?string $raw): self
    {
        if ($raw === null || $raw === '') {
            return new self();
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return new self($decoded);
        }

        // Fall back to PHP-serialized format from Phorum 6.
        // allowed_classes: false prevents object-injection attacks.
        $unserialized = @unserialize($raw, ['allowed_classes' => false]);
        if (is_array($unserialized)) {
            return new self($unserialized);
        }

        return new self();
    }

    /**
     * Build an instance from an array directly.
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Return a value by key, or null if absent.
     */
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Return a new instance with the given key set.
     */
    public function with(string $key, mixed $value): self
    {
        $data        = $this->data;
        $data[$key]  = $value;
        return new self($data);
    }

    /**
     * Return a new instance with the given key removed.
     */
    public function without(string $key): self
    {
        $data = $this->data;
        unset($data[$key]);
        return new self($data);
    }

    /**
     * Return the raw data array.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Encode to JSON for storage.
     */
    public function encode(): string
    {
        if (empty($this->data)) {
            return '';
        }
        return (string) json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Model;

/**
 * Value object for the file `meta` column — mirrors MessageMeta's shape.
 *
 * Unlike message meta, there's no legacy Phorum 6 format to support (this is
 * a brand-new column), so decode() only handles JSON.
 *
 * Known fields:
 *   width, height - image dimensions in pixels, set at upload time by
 *                   FileService::store() via fromImageData()
 */
class FileMeta
{
    private function __construct(private array $data) {}

    /**
     * Decode a raw meta string from the database.
     * Returns an empty FileMeta when $raw is null/empty or unparseable.
     */
    public static function decode(?string $raw): self
    {
        if ($raw === null || $raw === '') {
            return new self([]);
        }

        $decoded = json_decode($raw, true);
        return new self(is_array($decoded) ? $decoded : []);
    }

    /**
     * Create a FileMeta from a plain array.
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Build a FileMeta from raw image bytes via getimagesizefromstring() —
     * a pure function over the byte string, so it's testable independent of
     * the upload pipeline. Returns null when $rawData isn't a decodable image.
     */
    public static function fromImageData(string $rawData): ?self
    {
        $info = @getimagesizefromstring($rawData);
        if ($info === false) {
            return null;
        }

        return new self(['width' => $info[0], 'height' => $info[1]]);
    }

    // -------------------------------------------------------------------------
    // Typed accessors for known fields
    // -------------------------------------------------------------------------

    public function width(): ?int
    {
        return isset($this->data['width']) ? (int) $this->data['width'] : null;
    }

    public function height(): ?int
    {
        return isset($this->data['height']) ? (int) $this->data['height'] : null;
    }

    /**
     * Return an arbitrary meta field value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    // -------------------------------------------------------------------------
    // Mutation and encoding (immutable — returns new instance)
    // -------------------------------------------------------------------------

    /**
     * Return a new FileMeta with the given field set.
     */
    public function with(string $key, mixed $value): self
    {
        return new self(array_merge($this->data, [$key => $value]));
    }

    /**
     * Encode to a JSON string for database storage.
     */
    public function encode(): string
    {
        return json_encode($this->data) ?: '{}';
    }
}

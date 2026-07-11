<?php
declare(strict_types=1);

namespace Phorum\Model;

/**
 * Value object for the message `meta` column.
 *
 * Old Phorum 6 stored meta as a PHP-serialized array.
 * New Phorum stores it as a JSON object.
 * Both formats are supported on read; writes always produce JSON.
 *
 * Known fields:
 *   format    - message body format: 'bbcode' (default), 'markdown', 'html', 'text'
 *   edit_date - Unix timestamp of the last edit (absent if never edited)
 *   edit_count - number of times the message has been edited
 *
 * Old Phorum-only fields that are large and computed (message_ids,
 * message_ids_moderator) are intentionally omitted from encode() output
 * because the new codebase derives them from direct DB queries.
 */
class MessageMeta
{
    // Fields that are large computed arrays in old Phorum and not used by new code.
    private const OMIT_ON_ENCODE = ['message_ids', 'message_ids_moderator', 'recent_post'];

    private function __construct(private array $data) {}

    /**
     * Decode a raw meta string from the database.
     * Accepts JSON (new format) or PHP-serialized arrays (old Phorum 6).
     * Returns an empty MessageMeta when $raw is null/empty or unparseable.
     */
    public static function decode(?string $raw): self
    {
        if ($raw === null || $raw === '') {
            return new self([]);
        }

        // JSON: new format  {"format":"markdown"}
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return new self($decoded);
        }

        // PHP serialize: old Phorum 6 format  a:1:{s:6:"format";s:6:"bbcode";}
        // allowed_classes: false prevents object injection — only scalars and arrays.
        $decoded = @unserialize($raw, ['allowed_classes' => false]);
        if (is_array($decoded)) {
            return new self($decoded);
        }

        return new self([]);
    }

    /**
     * Create a MessageMeta from a plain array (for new messages).
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    // -------------------------------------------------------------------------
    // Typed accessors for known fields
    // -------------------------------------------------------------------------

    /**
     * Message body format.
     * Defaults to 'bbcode' so old Phorum messages with no meta render correctly.
     */
    public function format(): string
    {
        return isset($this->data['format']) ? (string) $this->data['format'] : 'bbcode';
    }

    /**
     * Unix timestamp of the last edit, or null if the message has never been edited.
     */
    public function editDate(): ?int
    {
        return isset($this->data['edit_date']) ? (int) $this->data['edit_date'] : null;
    }

    /**
     * Number of times the message has been edited.
     */
    public function editCount(): int
    {
        return (int) ($this->data['edit_count'] ?? 0);
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
     * Return a new MessageMeta with the given field set.
     */
    public function with(string $key, mixed $value): self
    {
        return new self(array_merge($this->data, [$key => $value]));
    }

    /**
     * Encode to a JSON string for database storage.
     * Large computed arrays from old Phorum (message_ids, etc.) are omitted.
     */
    public function encode(): string
    {
        $out = array_diff_key($this->data, array_flip(self::OMIT_ON_ENCODE));
        return json_encode($out) ?: '{}';
    }
}

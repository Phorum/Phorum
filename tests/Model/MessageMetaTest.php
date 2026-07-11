<?php
declare(strict_types=1);

namespace Phorum\Tests\Model;

use Phorum\Model\MessageMeta;
use PHPUnit\Framework\TestCase;

class MessageMetaTest extends TestCase
{
    // -------------------------------------------------------------------------
    // decode() — null / empty
    // -------------------------------------------------------------------------

    public function testDecodeNullReturnsDefaultFormat(): void
    {
        $meta = MessageMeta::decode(null);
        $this->assertSame('bbcode', $meta->format());
    }

    public function testDecodeEmptyStringReturnsDefaultFormat(): void
    {
        $meta = MessageMeta::decode('');
        $this->assertSame('bbcode', $meta->format());
    }

    // -------------------------------------------------------------------------
    // decode() — JSON (new format)
    // -------------------------------------------------------------------------

    public function testDecodeJsonFormat(): void
    {
        $meta = MessageMeta::decode('{"format":"markdown"}');
        $this->assertSame('markdown', $meta->format());
    }

    public function testDecodeJsonEditDate(): void
    {
        $meta = MessageMeta::decode('{"format":"markdown","edit_date":1700000000,"edit_count":2}');
        $this->assertSame(1700000000, $meta->editDate());
        $this->assertSame(2, $meta->editCount());
    }

    public function testDecodeJsonNoEditDateReturnsNull(): void
    {
        $meta = MessageMeta::decode('{"format":"markdown"}');
        $this->assertNull($meta->editDate());
        $this->assertSame(0, $meta->editCount());
    }

    public function testDecodeJsonArbitraryField(): void
    {
        $meta = MessageMeta::decode('{"format":"bbcode","custom_key":"hello"}');
        $this->assertSame('hello', $meta->get('custom_key'));
    }

    // -------------------------------------------------------------------------
    // decode() — PHP serialize (old Phorum 6 format)
    // -------------------------------------------------------------------------

    public function testDecodePhpSerializeBbcode(): void
    {
        $raw  = serialize(['format' => 'bbcode']);
        $meta = MessageMeta::decode($raw);
        $this->assertSame('bbcode', $meta->format());
    }

    public function testDecodePhpSerializeMarkdown(): void
    {
        $raw  = serialize(['format' => 'markdown']);
        $meta = MessageMeta::decode($raw);
        $this->assertSame('markdown', $meta->format());
    }

    public function testDecodePhpSerializeWithMessageIds(): void
    {
        // Old Phorum stored large message_ids arrays; we read format correctly.
        $raw  = serialize(['format' => 'bbcode', 'message_ids' => [1, 2, 3, 4, 5]]);
        $meta = MessageMeta::decode($raw);
        $this->assertSame('bbcode', $meta->format());
    }

    public function testDecodePhpSerializeWithEditDate(): void
    {
        $raw  = serialize(['format' => 'bbcode', 'edit_date' => 1700000000, 'edit_count' => 1]);
        $meta = MessageMeta::decode($raw);
        $this->assertSame(1700000000, $meta->editDate());
        $this->assertSame(1, $meta->editCount());
    }

    public function testDecodePhpSerializeWithNoFormat(): void
    {
        // Phorum 6 messages could theoretically have no format key.
        $raw  = serialize(['message_ids' => [1, 2, 3]]);
        $meta = MessageMeta::decode($raw);
        $this->assertSame('bbcode', $meta->format());
    }

    // -------------------------------------------------------------------------
    // decode() — garbage input
    // -------------------------------------------------------------------------

    public function testDecodeGarbageStringReturnsDefaultFormat(): void
    {
        $meta = MessageMeta::decode('not valid json or serialize');
        $this->assertSame('bbcode', $meta->format());
    }

    // -------------------------------------------------------------------------
    // fromArray() and encode()
    // -------------------------------------------------------------------------

    public function testFromArrayAndEncode(): void
    {
        $meta = MessageMeta::fromArray(['format' => 'markdown']);
        $this->assertSame('markdown', $meta->format());
        $this->assertJsonStringEqualsJsonString('{"format":"markdown"}', $meta->encode());
    }

    public function testEncodeOmitsLegacyComputedFields(): void
    {
        $raw  = serialize(['format' => 'bbcode', 'message_ids' => [1, 2], 'message_ids_moderator' => [3]]);
        $meta = MessageMeta::decode($raw);
        $encoded = json_decode($meta->encode(), true);

        $this->assertArrayNotHasKey('message_ids', $encoded);
        $this->assertArrayNotHasKey('message_ids_moderator', $encoded);
        $this->assertSame('bbcode', $encoded['format']);
    }

    public function testEncodeOmitsRecentPostLegacyField(): void
    {
        $raw  = serialize(['format' => 'bbcode', 'recent_post' => 42]);
        $meta = MessageMeta::decode($raw);
        $encoded = json_decode($meta->encode(), true);

        $this->assertArrayNotHasKey('recent_post', $encoded);
    }

    public function testEncodeIncludesEditDateWhenPresent(): void
    {
        $meta    = MessageMeta::fromArray(['format' => 'markdown', 'edit_date' => 1700000000, 'edit_count' => 3]);
        $encoded = json_decode($meta->encode(), true);

        $this->assertSame(1700000000, $encoded['edit_date']);
        $this->assertSame(3, $encoded['edit_count']);
    }

    // -------------------------------------------------------------------------
    // with() — immutability
    // -------------------------------------------------------------------------

    public function testWithReturnsNewInstance(): void
    {
        $original = MessageMeta::fromArray(['format' => 'bbcode']);
        $updated  = $original->with('format', 'markdown');

        $this->assertNotSame($original, $updated);
        $this->assertSame('bbcode', $original->format());
        $this->assertSame('markdown', $updated->format());
    }

    public function testWithSetsArbitraryField(): void
    {
        $meta    = MessageMeta::fromArray(['format' => 'markdown']);
        $updated = $meta->with('edit_date', 9999);

        $this->assertSame(9999, $updated->editDate());
    }

    // -------------------------------------------------------------------------
    // Round-trip: encode → decode
    // -------------------------------------------------------------------------

    public function testRoundTripPreservesFormat(): void
    {
        $original = MessageMeta::fromArray(['format' => 'markdown', 'edit_date' => 1234567890, 'edit_count' => 5]);
        $decoded  = MessageMeta::decode($original->encode());

        $this->assertSame('markdown', $decoded->format());
        $this->assertSame(1234567890, $decoded->editDate());
        $this->assertSame(5, $decoded->editCount());
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Model;

use Phorum\Model\FileMeta;
use PHPUnit\Framework\TestCase;

class FileMetaTest extends TestCase
{
    /**
     * A minimal valid 1x1 transparent GIF, hand-encoded as a byte literal —
     * needs no GD/fileinfo dependency to construct, and is reliably decoded
     * by getimagesizefromstring() as a 1x1 image.
     */
    private const TINY_GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    // -------------------------------------------------------------------------
    // decode() — null / empty / garbage
    // -------------------------------------------------------------------------

    public function testDecodeNullReturnsEmptyMeta(): void
    {
        $meta = FileMeta::decode(null);
        $this->assertNull($meta->width());
        $this->assertNull($meta->height());
    }

    public function testDecodeEmptyStringReturnsEmptyMeta(): void
    {
        $meta = FileMeta::decode('');
        $this->assertNull($meta->width());
    }

    public function testDecodeGarbageStringReturnsEmptyMeta(): void
    {
        $meta = FileMeta::decode('not valid json');
        $this->assertNull($meta->width());
        $this->assertNull($meta->height());
    }

    public function testDecodeJsonWidthAndHeight(): void
    {
        $meta = FileMeta::decode('{"width":100,"height":200}');
        $this->assertSame(100, $meta->width());
        $this->assertSame(200, $meta->height());
    }

    public function testDecodeJsonArbitraryField(): void
    {
        $meta = FileMeta::decode('{"width":1,"custom_key":"hello"}');
        $this->assertSame('hello', $meta->get('custom_key'));
    }

    // -------------------------------------------------------------------------
    // fromArray() and encode()
    // -------------------------------------------------------------------------

    public function testFromArrayAndEncode(): void
    {
        $meta = FileMeta::fromArray(['width' => 10, 'height' => 20]);
        $this->assertSame(10, $meta->width());
        $this->assertJsonStringEqualsJsonString('{"width":10,"height":20}', $meta->encode());
    }

    // -------------------------------------------------------------------------
    // fromImageData()
    // -------------------------------------------------------------------------

    public function testFromImageDataReturnsDimensionsForValidImage(): void
    {
        $meta = FileMeta::fromImageData(self::TINY_GIF);
        $this->assertNotNull($meta);
        $this->assertSame(1, $meta->width());
        $this->assertSame(1, $meta->height());
    }

    public function testFromImageDataReturnsNullForNonImageData(): void
    {
        $this->assertNull(FileMeta::fromImageData('this is not an image'));
    }

    public function testFromImageDataReturnsNullForEmptyString(): void
    {
        $this->assertNull(FileMeta::fromImageData(''));
    }

    // -------------------------------------------------------------------------
    // with() — immutability
    // -------------------------------------------------------------------------

    public function testWithReturnsNewInstance(): void
    {
        $original = FileMeta::fromArray(['width' => 10, 'height' => 20]);
        $updated  = $original->with('width', 99);

        $this->assertNotSame($original, $updated);
        $this->assertSame(10, $original->width());
        $this->assertSame(99, $updated->width());
    }

    // -------------------------------------------------------------------------
    // Round-trip: encode → decode
    // -------------------------------------------------------------------------

    public function testRoundTripPreservesDimensions(): void
    {
        $original = FileMeta::fromImageData(self::TINY_GIF);
        $decoded  = FileMeta::decode($original->encode());

        $this->assertSame(1, $decoded->width());
        $this->assertSame(1, $decoded->height());
    }
}

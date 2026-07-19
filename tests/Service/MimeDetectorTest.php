<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Service\MimeDetector;
use PHPUnit\Framework\TestCase;

class MimeDetectorTest extends TestCase
{
    private const TINY_GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

    public function testDetectSniffsGifContent(): void
    {
        $this->assertSame('image/gif', MimeDetector::detect(self::TINY_GIF, 'photo.gif'));
    }

    /**
     * finfo_buffer content-sniffs successfully for almost any realistic byte
     * string, so detect() trusts that result over a mismatched extension —
     * this is the whole point of content-based detection rather than
     * trusting the client-supplied filename.
     */
    public function testDetectPrefersContentSniffingOverMisleadingExtension(): void
    {
        $this->assertSame('image/gif', MimeDetector::detect(self::TINY_GIF, 'not-really-a.pdf'));
    }

    public function testDetectIdentifiesPlainTextContent(): void
    {
        $this->assertSame('text/plain', MimeDetector::detect('hello world', 'data.bin'));
    }

    public function testMimeMapCoversCommonImageExtensions(): void
    {
        $this->assertSame('image/jpeg', MimeDetector::MIME_MAP['jpg']);
        $this->assertSame('image/png', MimeDetector::MIME_MAP['png']);
        $this->assertSame('image/svg+xml', MimeDetector::MIME_MAP['svg']);
    }
}

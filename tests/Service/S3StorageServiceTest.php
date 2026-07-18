<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3ClientInterface;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Phorum\Mapper\SettingMapper;
use Phorum\Mod\S3Storage\S3StorageService;
use PHPUnit\Framework\TestCase;

class S3StorageServiceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/mods/s3storage/S3StorageService.php';
    }

    private function makeSettings(array $overrides = []): SettingMapper
    {
        $values = array_merge([
            's3_bucket'     => 'my-bucket',
            's3_region'     => 'us-east-1',
            's3_access_key' => 'AKIA...',
            's3_secret_key' => 'sekrit',
            's3_key_prefix' => 'phorum',
        ], $overrides);

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturnCallback(fn($name) => $values[$name] ?? null);
        return $settings;
    }

    // -------------------------------------------------------------------------
    // keyForFile / mimeForFilename — pure helpers, no client involved
    // -------------------------------------------------------------------------

    public function testKeyForFileUsesConfiguredPrefix(): void
    {
        $svc = new S3StorageService($this->makeSettings(['s3_key_prefix' => 'phorum']));
        $this->assertSame('phorum/7', $svc->keyForFile(7));
    }

    public function testKeyForFileWithoutPrefixIsJustTheFileId(): void
    {
        $svc = new S3StorageService($this->makeSettings(['s3_key_prefix' => '']));
        $this->assertSame('7', $svc->keyForFile(7));
    }

    public function testMimeForFilenameKnownExtension(): void
    {
        $svc = new S3StorageService($this->makeSettings());
        $this->assertSame('image/jpeg', $svc->mimeForFilename('photo.JPG'));
    }

    public function testMimeForFilenameUnknownExtensionFallsBackToOctetStream(): void
    {
        $svc = new S3StorageService($this->makeSettings());
        $this->assertSame('application/octet-stream', $svc->mimeForFilename('mystery.xyz'));
    }

    // -------------------------------------------------------------------------
    // putObject
    // -------------------------------------------------------------------------

    public function testPutObjectReturnsTrueOnSuccess(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->method('execute')->willReturn(new Result([]));

        $svc = new S3StorageService($this->makeSettings(), $client);
        $this->assertTrue($svc->putObject('phorum/7', 'bytes', 'image/jpeg'));
    }

    public function testPutObjectReturnsFalseAndDoesNotThrowOnFailure(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->method('execute')->willThrowException(new \RuntimeException('network down'));

        $svc = new S3StorageService($this->makeSettings(), $client);
        $this->assertFalse($svc->putObject('phorum/7', 'bytes', 'image/jpeg'));
    }

    // -------------------------------------------------------------------------
    // getObject
    // -------------------------------------------------------------------------

    public function testGetObjectReturnsBytesOnSuccess(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->method('execute')->willReturn(new Result(['Body' => 'the-bytes']));

        $svc = new S3StorageService($this->makeSettings(), $client);
        $this->assertSame('the-bytes', $svc->getObject('phorum/7'));
    }

    public function testGetObjectReturnsNullAndDoesNotThrowOnFailure(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->method('execute')->willThrowException(new \RuntimeException('not found'));

        $svc = new S3StorageService($this->makeSettings(), $client);
        $this->assertNull($svc->getObject('phorum/7'));
    }

    // -------------------------------------------------------------------------
    // deleteObject
    // -------------------------------------------------------------------------

    public function testDeleteObjectCallsExecuteAndDoesNotThrowOnFailure(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->expects($this->once())->method('execute')->willThrowException(new \RuntimeException('boom'));

        $svc = new S3StorageService($this->makeSettings(), $client);
        $svc->deleteObject('phorum/7'); // must not throw
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // presignedGetUrl
    // -------------------------------------------------------------------------

    public function testPresignedGetUrlReturnsTheRequestUri(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->method('createPresignedRequest')
            ->willReturn(new PsrRequest('GET', 'https://my-bucket.s3.amazonaws.com/phorum/7?X-Amz-Signature=abc'));

        $svc = new S3StorageService($this->makeSettings(), $client);
        $url = $svc->presignedGetUrl('phorum/7', 'application/pdf', 'attachment', 'report.pdf');

        $this->assertSame('https://my-bucket.s3.amazonaws.com/phorum/7?X-Amz-Signature=abc', $url);
    }

    public function testPresignedGetUrlReturnsNullAndDoesNotThrowOnFailure(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->method('createPresignedRequest')->willThrowException(new \RuntimeException('boom'));

        $svc = new S3StorageService($this->makeSettings(), $client);
        $this->assertNull($svc->presignedGetUrl('phorum/7', 'application/pdf', 'attachment', 'report.pdf'));
    }

    public function testPresignedGetUrlSanitizesFilenameForDisposition(): void
    {
        $capturedArgs = null;
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturnCallback(function ($name, $args) use (&$capturedArgs) {
            $capturedArgs = $args;
            return $this->createMock(CommandInterface::class);
        });
        $client->method('createPresignedRequest')
            ->willReturn(new PsrRequest('GET', 'https://example.com/signed'));

        $svc = new S3StorageService($this->makeSettings(), $client);
        $svc->presignedGetUrl('phorum/7', 'application/pdf', 'attachment', "evil\r\nname\".pdf");

        $this->assertStringNotContainsString("\r", $capturedArgs['ResponseContentDisposition']);
        $this->assertStringNotContainsString("\n", $capturedArgs['ResponseContentDisposition']);
    }
}

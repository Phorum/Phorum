<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3ClientInterface;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Phorum\Mapper\SettingMapper;
use Phorum\Mod\S3Storage\S3StorageService;
use Phorum\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;

/**
 * Covers S3StorageService: key/mime helpers, and the AWS-backed operations
 * that must swallow every failure rather than letting it break the request
 * that triggered them.
 *
 * No real AWS calls are made — S3ClientInterface is mocked and injected, and
 * a SpyLogger is injected in place of the default ErrorLogLogger so the
 * failure-path notices are asserted on instead of reaching stderr.
 */
class S3StorageServiceTest extends TestCase
{
    /** Collects the service's log output for the duration of one test. */
    private SpyLogger $logger;

    /** Loads the mod class, which lives outside the composer autoload map. */
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/mods/s3storage/S3StorageService.php';
    }

    /** Gives each test a fresh spy logger. */
    protected function setUp(): void
    {
        $this->logger = new SpyLogger();
    }

    /** Builds the service under test with the spy logger injected. */
    private function makeService(SettingMapper $settings, ?S3ClientInterface $client = null): S3StorageService
    {
        return new S3StorageService($settings, $client, $this->logger);
    }

    /**
     * A SettingMapper stub returning the standard S3 settings, with any
     * supplied overrides applied on top.
     */
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
        $svc = $this->makeService($this->makeSettings(['s3_key_prefix' => 'phorum']));
        $this->assertSame('phorum/7', $svc->keyForFile(7));
    }

    public function testKeyForFileWithoutPrefixIsJustTheFileId(): void
    {
        $svc = $this->makeService($this->makeSettings(['s3_key_prefix' => '']));
        $this->assertSame('7', $svc->keyForFile(7));
    }

    public function testMimeForFilenameKnownExtension(): void
    {
        $svc = $this->makeService($this->makeSettings());
        $this->assertSame('image/jpeg', $svc->mimeForFilename('photo.JPG'));
    }

    public function testMimeForFilenameUnknownExtensionFallsBackToOctetStream(): void
    {
        $svc = $this->makeService($this->makeSettings());
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

        $svc = $this->makeService($this->makeSettings(), $client);
        $this->assertTrue($svc->putObject('phorum/7', 'bytes', 'image/jpeg'));
    }

    public function testPutObjectReturnsFalseAndDoesNotThrowOnFailure(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->method('execute')->willThrowException(new \RuntimeException('network down'));

        $svc = $this->makeService($this->makeSettings(), $client);
        $this->assertFalse($svc->putObject('phorum/7', 'bytes', 'image/jpeg'));

        $record = $this->logger->onlyRecord();
        $this->assertSame('error', $record['level']);
        $this->assertStringContainsString('putObject failed', $record['message']);
        $this->assertSame('phorum/7', $record['context']['key']);
        $this->assertSame('network down', $record['context']['error']);
    }

    // -------------------------------------------------------------------------
    // getObject
    // -------------------------------------------------------------------------

    public function testGetObjectReturnsBytesOnSuccess(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->method('execute')->willReturn(new Result(['Body' => 'the-bytes']));

        $svc = $this->makeService($this->makeSettings(), $client);
        $this->assertSame('the-bytes', $svc->getObject('phorum/7'));
    }

    public function testGetObjectReturnsNullAndDoesNotThrowOnFailure(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->method('execute')->willThrowException(new \RuntimeException('not found'));

        $svc = $this->makeService($this->makeSettings(), $client);
        $this->assertNull($svc->getObject('phorum/7'));

        $record = $this->logger->onlyRecord();
        $this->assertSame('error', $record['level']);
        $this->assertStringContainsString('getObject failed', $record['message']);
        $this->assertSame('phorum/7', $record['context']['key']);
        $this->assertSame('not found', $record['context']['error']);
    }

    // -------------------------------------------------------------------------
    // deleteObject
    // -------------------------------------------------------------------------

    public function testDeleteObjectCallsExecuteAndDoesNotThrowOnFailure(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->expects($this->once())->method('execute')->willThrowException(new \RuntimeException('boom'));

        $svc = $this->makeService($this->makeSettings(), $client);
        $svc->deleteObject('phorum/7'); // must not throw

        $record = $this->logger->onlyRecord();
        $this->assertSame('error', $record['level']);
        $this->assertStringContainsString('deleteObject failed', $record['message']);
        $this->assertSame('phorum/7', $record['context']['key']);
        $this->assertSame('boom', $record['context']['error']);
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

        $svc = $this->makeService($this->makeSettings(), $client);
        $url = $svc->presignedGetUrl('phorum/7', 'application/pdf', 'attachment', 'report.pdf');

        $this->assertSame('https://my-bucket.s3.amazonaws.com/phorum/7?X-Amz-Signature=abc', $url);
    }

    public function testPresignedGetUrlReturnsNullAndDoesNotThrowOnFailure(): void
    {
        $client = $this->createMock(S3ClientInterface::class);
        $client->method('getCommand')->willReturn($this->createMock(CommandInterface::class));
        $client->method('createPresignedRequest')->willThrowException(new \RuntimeException('boom'));

        $svc = $this->makeService($this->makeSettings(), $client);
        $this->assertNull($svc->presignedGetUrl('phorum/7', 'application/pdf', 'attachment', 'report.pdf'));

        $record = $this->logger->onlyRecord();
        $this->assertSame('error', $record['level']);
        $this->assertStringContainsString('presign failed', $record['message']);
        $this->assertSame('phorum/7', $record['context']['key']);
        $this->assertSame('boom', $record['context']['error']);
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

        $svc = $this->makeService($this->makeSettings(), $client);
        $svc->presignedGetUrl('phorum/7', 'application/pdf', 'attachment', "evil\r\nname\".pdf");

        $this->assertStringNotContainsString("\r", $capturedArgs['ResponseContentDisposition']);
        $this->assertStringNotContainsString("\n", $capturedArgs['ResponseContentDisposition']);
    }
}

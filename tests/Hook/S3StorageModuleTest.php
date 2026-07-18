<?php
declare(strict_types=1);

namespace Phorum\Tests\Hook;

use Phorum\Hook\HookDispatcher;
use Phorum\Mod\S3Storage\S3StorageHooks;
use Phorum\Mod\S3Storage\S3StorageService;
use Phorum\Model\File;
use PHPUnit\Framework\TestCase;

/**
 * Tests the full hook round-trip: the s3storage module registers on each
 * FileService hook, the dispatcher fires it, and S3StorageHooks delegates
 * to S3StorageService correctly — using the real S3StorageHooks::register()
 * wiring (not a hand-copied duplicate), following the pattern established
 * by WebhooksModuleTest/BbcodeModuleTest.
 */
class S3StorageModuleTest extends TestCase
{
    private static bool $moduleLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$moduleLoaded) {
            $base = dirname(__DIR__, 2) . '/mods/s3storage';
            require_once $base . '/S3StorageService.php';
            require_once $base . '/S3StorageHooks.php';
            self::$moduleLoaded = true;
        }
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    private function registerWith(S3StorageService $s3): HookDispatcher
    {
        HookDispatcher::reset();
        $hooks = HookDispatcher::getInstance();
        S3StorageHooks::register($s3, $hooks);
        return $hooks;
    }

    public function testAllHooksAreRegistered(): void
    {
        $hooks = $this->registerWith($this->createMock(S3StorageService::class));

        foreach (['file_store', 'file_retrieve', 'file_delete', 'file_serve_url'] as $hookName) {
            $this->assertTrue($hooks->hasHook($hookName), "expected {$hookName} to be registered");
        }
    }

    // -------------------------------------------------------------------------
    // file_store
    // -------------------------------------------------------------------------

    public function testFileStoreUploadsToS3AndClearsFileDataOnSuccess(): void
    {
        $s3 = $this->createMock(S3StorageService::class);
        $s3->method('keyForFile')->with(7)->willReturn('prefix/7');
        $s3->method('mimeForFilename')->with('photo.jpg')->willReturn('image/jpeg');
        $s3->expects($this->once())->method('putObject')
            ->with('prefix/7', 'raw-bytes', 'image/jpeg')
            ->willReturn(true);

        $hooks  = $this->registerWith($s3);
        $result = $hooks->dispatch('file_store', [
            'file_id' => 7, 'filename' => 'photo.jpg', 'file_data' => 'raw-bytes',
        ]);

        $this->assertSame('', $result['file_data']);
    }

    public function testFileStoreReturnsFalseWhenUploadFails(): void
    {
        $s3 = $this->createMock(S3StorageService::class);
        $s3->method('keyForFile')->willReturn('prefix/7');
        $s3->method('mimeForFilename')->willReturn('image/jpeg');
        $s3->method('putObject')->willReturn(false);

        $hooks  = $this->registerWith($s3);
        $result = $hooks->dispatch('file_store', [
            'file_id' => 7, 'filename' => 'photo.jpg', 'file_data' => 'raw-bytes',
        ]);

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // file_retrieve
    // -------------------------------------------------------------------------

    public function testFileRetrieveReturnsTwoElementArrayWithBytesOnSuccess(): void
    {
        $s3 = $this->createMock(S3StorageService::class);
        $s3->method('keyForFile')->with(7)->willReturn('prefix/7');
        $s3->method('getObject')->with('prefix/7')->willReturn('the-bytes');

        $hooks  = $this->registerWith($s3);
        $result = $hooks->dispatch('file_retrieve', [['file_id' => 7, 'file_data' => null], 0]);

        $this->assertIsArray($result);
        $this->assertSame('the-bytes', $result[0]['file_data']);
    }

    public function testFileRetrieveLeavesFileDataNullWhenS3FetchFails(): void
    {
        $s3 = $this->createMock(S3StorageService::class);
        $s3->method('keyForFile')->willReturn('prefix/7');
        $s3->method('getObject')->willReturn(null);

        $hooks  = $this->registerWith($s3);
        $result = $hooks->dispatch('file_retrieve', [['file_id' => 7, 'file_data' => null], 0]);

        $this->assertIsArray($result);
        $this->assertNull($result[0]['file_data']);
    }

    // -------------------------------------------------------------------------
    // file_delete
    // -------------------------------------------------------------------------

    public function testFileDeleteRemovesTheS3Object(): void
    {
        $s3 = $this->createMock(S3StorageService::class);
        $s3->method('keyForFile')->with(42)->willReturn('prefix/42');
        $s3->expects($this->once())->method('deleteObject')->with('prefix/42');

        $hooks = $this->registerWith($s3);
        $hooks->dispatch('file_delete', 42);
    }

    // -------------------------------------------------------------------------
    // file_serve_url
    // -------------------------------------------------------------------------

    public function testFileServeUrlReturnsPresignedUrl(): void
    {
        $file           = new File();
        $file->file_id  = 9;
        $file->filename = 'report.pdf';

        $s3 = $this->createMock(S3StorageService::class);
        $s3->method('keyForFile')->with(9)->willReturn('prefix/9');
        $s3->method('mimeForFilename')->with('report.pdf')->willReturn('application/pdf');
        $s3->expects($this->once())->method('presignedGetUrl')
            ->with('prefix/9', 'application/pdf', 'attachment', 'report.pdf')
            ->willReturn('https://bucket.s3.amazonaws.com/prefix/9?signed');

        $hooks  = $this->registerWith($s3);
        $result = $hooks->dispatch('file_serve_url', $file, 'attachment');

        $this->assertSame('https://bucket.s3.amazonaws.com/prefix/9?signed', $result);
    }

    public function testFileServeUrlFallsThroughToOriginalFileWhenPresignFails(): void
    {
        // HookDispatcher::dispatch() only replaces $data when a callback
        // returns non-null, so a null return here means the caller (in
        // production, FileController) gets the original $file object back —
        // which correctly fails its is_string($redirectUrl) check and falls
        // through to normal byte-serving.
        $file           = new File();
        $file->file_id  = 9;
        $file->filename = 'report.pdf';

        $s3 = $this->createMock(S3StorageService::class);
        $s3->method('keyForFile')->willReturn('prefix/9');
        $s3->method('mimeForFilename')->willReturn('application/pdf');
        $s3->method('presignedGetUrl')->willReturn(null);

        $hooks  = $this->registerWith($s3);
        $result = $hooks->dispatch('file_serve_url', $file, 'attachment');

        $this->assertFalse(is_string($result));
        $this->assertSame($file, $result);
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\FileMapper;
use Phorum\Model\File;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Service\FileService;
use PHPUnit\Framework\TestCase;

class FileServiceTest extends TestCase
{
    protected function setUp(): void
    {
        HookDispatcher::reset();
        require_once dirname(__DIR__, 2) . '/src/Hook/functions.php';
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    private function makeForum(array $override = []): Forum
    {
        $f = new Forum();
        $f->forum_id                = 1;
        $f->max_attachments         = $override['max_attachments']         ?? 0;
        $f->max_attachment_size     = $override['max_attachment_size']     ?? 0;
        $f->max_totalattachment_size = $override['max_totalattachment_size'] ?? 0;
        $f->allow_attachment_types  = $override['allow_attachment_types']  ?? '';
        return $f;
    }

    private function makeUpload(array $override = []): array
    {
        return array_merge([
            'name'     => 'photo.jpg',
            'size'     => 1024,
            'error'    => UPLOAD_ERR_OK,
            'tmp_name' => '/tmp/upload',
        ], $override);
    }

    // -------------------------------------------------------------------------
    // validateUpload — happy path
    // -------------------------------------------------------------------------

    public function testValidateUploadReturnsNullForValidFile(): void
    {
        $svc = new FileService($this->createMock(FileMapper::class));
        $result = $svc->validateUpload($this->makeUpload(), $this->makeForum(), 0, 0);
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // validateUpload — error codes
    // -------------------------------------------------------------------------

    public function testValidateUploadReturnsErrorForNonOkError(): void
    {
        $svc = new FileService($this->createMock(FileMapper::class));
        $result = $svc->validateUpload(
            $this->makeUpload(['error' => UPLOAD_ERR_INI_SIZE]),
            $this->makeForum(),
            0,
            0
        );
        $this->assertNotNull($result);
        $this->assertStringContainsString('error code', $result);
    }

    // -------------------------------------------------------------------------
    // validateUpload — max attachments
    // -------------------------------------------------------------------------

    public function testValidateUploadBlocksWhenMaxAttachmentsReached(): void
    {
        $svc = new FileService($this->createMock(FileMapper::class));
        $result = $svc->validateUpload(
            $this->makeUpload(),
            $this->makeForum(['max_attachments' => 2]),
            existingCount: 2,
            existingTotalBytes: 0
        );
        $this->assertNotNull($result);
        $this->assertStringContainsString('2', $result);
    }

    // -------------------------------------------------------------------------
    // validateUpload — file size limits
    // -------------------------------------------------------------------------

    public function testValidateUploadBlocksOversizedFile(): void
    {
        $svc = new FileService($this->createMock(FileMapper::class));
        $result = $svc->validateUpload(
            $this->makeUpload(['size' => 2 * 1024 * 1024]), // 2 MB
            $this->makeForum(['max_attachment_size' => 1024 * 1024]), // 1 MB limit
            0,
            0
        );
        $this->assertNotNull($result);
        $this->assertStringContainsString('MB', $result);
    }

    public function testValidateUploadBlocksWhenTotalSizeExceeded(): void
    {
        $svc = new FileService($this->createMock(FileMapper::class));
        $result = $svc->validateUpload(
            $this->makeUpload(['size' => 500]),
            $this->makeForum(['max_totalattachment_size' => 1000]),
            existingCount: 0,
            existingTotalBytes: 600
        );
        $this->assertNotNull($result);
    }

    // -------------------------------------------------------------------------
    // validateUpload — allowed types
    // -------------------------------------------------------------------------

    public function testValidateUploadBlocksDisallowedExtension(): void
    {
        $svc = new FileService($this->createMock(FileMapper::class));
        $result = $svc->validateUpload(
            $this->makeUpload(['name' => 'script.php']),
            $this->makeForum(['allow_attachment_types' => 'jpg;png;gif']),
            0,
            0
        );
        $this->assertNotNull($result);
        $this->assertStringContainsString('.php', $result);
    }

    public function testValidateUploadAllowsAllowedExtension(): void
    {
        $svc = new FileService($this->createMock(FileMapper::class));
        $result = $svc->validateUpload(
            $this->makeUpload(['name' => 'image.PNG']),
            $this->makeForum(['allow_attachment_types' => 'jpg;png;gif']),
            0,
            0
        );
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // retrieve — falls back to base64 decode
    // -------------------------------------------------------------------------

    public function testRetrieveDecodesBase64WhenNoHook(): void
    {
        $svc  = new FileService($this->createMock(FileMapper::class));
        $file = new File();
        $file->file_data = base64_encode('hello world');

        $result = $svc->retrieve($file);
        $this->assertSame('hello world', $result);
    }

    // -------------------------------------------------------------------------
    // delete — calls mapper delete and fires hook
    // -------------------------------------------------------------------------

    public function testDeleteCallsMapperDelete(): void
    {
        $file     = new File();
        $file->file_id = 42;

        $mapper = $this->createMock(FileMapper::class);
        $mapper->expects($this->once())->method('delete')->with(42);

        $svc = new FileService($mapper);
        $svc->delete($file);
    }

    // -------------------------------------------------------------------------
    // hydrateMessages
    // -------------------------------------------------------------------------

    public function testHydrateMessagesSetsAttachments(): void
    {
        $f = new File();
        $f->file_id    = 1;
        $f->message_id = 5;

        $mapper = $this->createMock(FileMapper::class);
        $mapper->method('findByMessages')->willReturn([5 => [$f]]);

        $msg = new Message();
        $msg->message_id = 5;

        $svc = new FileService($mapper);
        $svc->hydrateMessages([$msg]);

        $this->assertSame([$f], $msg->attachments);
    }

    public function testHydrateMessagesDoesNothingForEmptyInput(): void
    {
        $mapper = $this->createMock(FileMapper::class);
        $mapper->expects($this->never())->method('findByMessages');

        $svc = new FileService($mapper);
        $svc->hydrateMessages([]);
    }

    // -------------------------------------------------------------------------
    // getAttachments
    // -------------------------------------------------------------------------

    public function testGetAttachmentsDelegatesToMapper(): void
    {
        $f = new File();
        $mapper = $this->createMock(FileMapper::class);
        $mapper->method('findByMessage')->with(10)->willReturn([$f]);

        $svc = new FileService($mapper);
        $this->assertSame([$f], $svc->getAttachments(10));
    }

    // -------------------------------------------------------------------------
    // deleteForMessage — fires delete hook and removes each file
    // -------------------------------------------------------------------------

    public function testDeleteForMessageDeletesAllAttachments(): void
    {
        $f1 = new File(); $f1->file_id = 1;
        $f2 = new File(); $f2->file_id = 2;

        $mapper = $this->createMock(FileMapper::class);
        $mapper->method('findByMessage')->with(7)->willReturn([$f1, $f2]);
        $mapper->expects($this->exactly(2))->method('delete');

        $svc = new FileService($mapper);
        $svc->deleteForMessage(7);
    }
}

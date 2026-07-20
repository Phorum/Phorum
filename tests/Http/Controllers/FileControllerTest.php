<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Hook\HookDispatcher;
use Phorum\Http\Controllers\FileController;
use Phorum\Http\Request;
use Phorum\Mapper\FileMapper;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Model\File;
use Phorum\Service\FileService;
use Phorum\Service\PermissionService;
use Phorum\Tests\Http\ControllerTestCase;

class FileControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): FileController
    {
        return new FileController(
            config:      $this->makeConfig(),
            twig:        $this->makeTwig(),
            fileMapper:  $deps['fileMapper']  ?? $this->createMock(FileMapper::class),
            messages:    $deps['messages']    ?? $this->createMock(MessageMapper::class),
            forums:      $deps['forums']      ?? $this->createMock(ForumMapper::class),
            perms:       $deps['perms']       ?? $this->createMock(PermissionService::class),
            fileService: $deps['fileService'] ?? $this->createMock(FileService::class),
        );
    }

    private function makeAttachmentFile(int $id = 1, int $messageId = 1): File
    {
        $file             = new File();
        $file->file_id    = $id;
        $file->message_id = $messageId;
        $file->link       = File::LINK_MESSAGE;
        $file->filename   = 'report.pdf';
        return $file;
    }

    /** A controller wired to serve $bytes for file_id=1, with read permission granted. */
    private function makeControllerForServe(string $bytes, string $filename = 'video.mp4'): FileController
    {
        $file = $this->makeAttachmentFile();
        $file->filename = $filename;
        $msg   = $this->makeMessage(1, 1);
        $forum = $this->makeForum(1);

        $fileMapper = $this->createMock(FileMapper::class);
        $fileMapper->method('load')->with(1)->willReturn($file);
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->with(1)->willReturn($msg);
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->with(1)->willReturn($forum);
        $perms = $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn(true);
        $perms->method('canViewAttachments')->willReturn(true);

        $fileService = $this->createMock(FileService::class);
        $fileService->method('retrieve')->willReturn($bytes);

        return $this->makeController([
            'fileMapper' => $fileMapper, 'messages' => $messages,
            'forums' => $forums, 'perms' => $perms, 'fileService' => $fileService,
        ]);
    }

    // -------------------------------------------------------------------------
    // serve() — Range requests (video seeking)
    // -------------------------------------------------------------------------

    public function testServeReturns206ForValidRangeRequest(): void
    {
        $ctrl     = $this->makeControllerForServe('the quick brown fox');
        $response = $ctrl->serve(new Request(
            server: ['HTTP_RANGE' => 'bytes=4-8'],
            tokens: ['file_id' => '1'],
        ));

        $this->assertSame(206, $response->status);
        $this->assertSame('quick', $response->body);
        $this->assertSame('bytes 4-8/19', $response->headers['Content-Range']);
        $this->assertSame('5', $response->headers['Content-Length']);
        $this->assertSame('bytes', $response->headers['Accept-Ranges']);
    }

    public function testServeReturns206ForOpenEndedRange(): void
    {
        $ctrl     = $this->makeControllerForServe('the quick brown fox'); // 19 bytes
        $response = $ctrl->serve(new Request(
            server: ['HTTP_RANGE' => 'bytes=16-'],
            tokens: ['file_id' => '1'],
        ));

        $this->assertSame(206, $response->status);
        $this->assertSame('fox', $response->body);
        $this->assertSame('bytes 16-18/19', $response->headers['Content-Range']);
    }

    public function testServeReturns416ForOutOfBoundsRange(): void
    {
        $ctrl     = $this->makeControllerForServe('short');
        $response = $ctrl->serve(new Request(
            server: ['HTTP_RANGE' => 'bytes=99999-'],
            tokens: ['file_id' => '1'],
        ));

        $this->assertSame(416, $response->status);
        $this->assertSame('bytes */5', $response->headers['Content-Range']);
    }

    public function testServeReturns200WithAcceptRangesWhenNoRangeHeader(): void
    {
        $ctrl     = $this->makeControllerForServe('the quick brown fox');
        $response = $ctrl->serve(new Request(tokens: ['file_id' => '1']));

        $this->assertSame(200, $response->status);
        $this->assertSame('the quick brown fox', $response->body);
        $this->assertSame('bytes', $response->headers['Accept-Ranges']);
    }

    public function testServeIgnoresRangeHeaderForForceDownloadContent(): void
    {
        // Content that trips the HTML-signature security check must still be
        // forced to attachment/octet-stream — a crafted Range header must not
        // be able to bypass that by taking the 206 path instead.
        $ctrl     = $this->makeControllerForServe('<html><script>alert(1)</script></html>', 'evil.svg');
        $response = $ctrl->serve(new Request(
            server: ['HTTP_RANGE' => 'bytes=0-4'],
            tokens: ['file_id' => '1'],
        ));

        $this->assertSame(200, $response->status);
        $this->assertSame('application/octet-stream', $response->headers['Content-Type']);
        $this->assertStringStartsWith('attachment', $response->headers['Content-Disposition']);
    }

    // -------------------------------------------------------------------------
    // serve() — file_serve_url hook
    // -------------------------------------------------------------------------

    public function testServeRedirectsWhenFileServeUrlHookReturnsAUrl(): void
    {
        $file = $this->makeAttachmentFile();
        $msg  = $this->makeMessage(1, 1);
        $forum = $this->makeForum(1);

        $fileMapper = $this->createMock(FileMapper::class);
        $fileMapper->method('load')->with(1)->willReturn($file);
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->with(1)->willReturn($msg);
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->with(1)->willReturn($forum);
        $perms = $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn(true);
        $perms->method('canViewAttachments')->willReturn(true);

        $fileService = $this->createMock(FileService::class);
        $fileService->expects($this->never())->method('retrieve');

        HookDispatcher::getInstance()->register('file_serve_url', function (File $f, string $disposition) {
            return 'https://bucket.s3.amazonaws.com/prefix/' . $f->file_id . '?signed';
        });

        $ctrl = $this->makeController([
            'fileMapper' => $fileMapper, 'messages' => $messages,
            'forums' => $forums, 'perms' => $perms, 'fileService' => $fileService,
        ]);
        $response = $ctrl->serve(new Request(tokens: ['file_id' => '1']));

        $this->assertSame(302, $response->status);
        $this->assertSame('https://bucket.s3.amazonaws.com/prefix/1?signed', $response->headers['Location']);
    }

    public function testServeFallsThroughToByteServingWhenHookUnclaimed(): void
    {
        $file = $this->makeAttachmentFile();
        $msg  = $this->makeMessage(1, 1);
        $forum = $this->makeForum(1);

        $fileMapper = $this->createMock(FileMapper::class);
        $fileMapper->method('load')->with(1)->willReturn($file);
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->with(1)->willReturn($msg);
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->with(1)->willReturn($forum);
        $perms = $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn(true);
        $perms->method('canViewAttachments')->willReturn(true);

        $fileService = $this->createMock(FileService::class);
        $fileService->expects($this->once())->method('retrieve')->willReturn('the bytes');

        // No file_serve_url handler registered — HookDispatcher::dispatch()
        // returns the original $file object unchanged, which fails the
        // is_string() check in the controller.
        $ctrl = $this->makeController([
            'fileMapper' => $fileMapper, 'messages' => $messages,
            'forums' => $forums, 'perms' => $perms, 'fileService' => $fileService,
        ]);
        $response = $ctrl->serve(new Request(tokens: ['file_id' => '1']));

        $this->assertSame(200, $response->status);
        $this->assertSame('the bytes', $response->body);
    }

    public function testServeDoesNotFireHookWhenPermissionDenied(): void
    {
        $file  = $this->makeAttachmentFile();
        $msg   = $this->makeMessage(1, 1);
        $forum = $this->makeForum(1);

        $fileMapper = $this->createMock(FileMapper::class);
        $fileMapper->method('load')->with(1)->willReturn($file);
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->with(1)->willReturn($msg);
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->with(1)->willReturn($forum);
        $perms = $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn(false);

        $hookFired = false;
        HookDispatcher::getInstance()->register('file_serve_url', function () use (&$hookFired) {
            $hookFired = true;
            return 'https://example.com/should-not-be-used';
        });

        $ctrl = $this->makeController([
            'fileMapper' => $fileMapper, 'messages' => $messages,
            'forums' => $forums, 'perms' => $perms,
        ]);
        $response = $ctrl->serve(new Request(tokens: ['file_id' => '1']));

        $this->assertSame(403, $response->status);
        $this->assertFalse($hookFired);
    }

    public function testServeReturns403WhenCannotViewAttachmentsEvenIfCanRead(): void
    {
        $file  = $this->makeAttachmentFile();
        $msg   = $this->makeMessage(1, 1);
        $forum = $this->makeForum(1);

        $fileMapper = $this->createMock(FileMapper::class);
        $fileMapper->method('load')->with(1)->willReturn($file);
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->with(1)->willReturn($msg);
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->with(1)->willReturn($forum);
        $perms = $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn(true);
        $perms->method('canViewAttachments')->willReturn(false);

        $hookFired = false;
        HookDispatcher::getInstance()->register('file_serve_url', function () use (&$hookFired) {
            $hookFired = true;
            return 'https://example.com/should-not-be-used';
        });

        $fileService = $this->createMock(FileService::class);
        $fileService->expects($this->never())->method('retrieve');

        $ctrl = $this->makeController([
            'fileMapper' => $fileMapper, 'messages' => $messages,
            'forums' => $forums, 'perms' => $perms, 'fileService' => $fileService,
        ]);
        $response = $ctrl->serve(new Request(tokens: ['file_id' => '1']));

        $this->assertSame(403, $response->status);
        $this->assertFalse($hookFired);
    }

    // -------------------------------------------------------------------------
    // avatar() — file_serve_url hook
    // -------------------------------------------------------------------------

    public function testAvatarRedirectsWhenFileServeUrlHookReturnsAUrl(): void
    {
        $file           = new File();
        $file->file_id  = 5;
        $file->link     = File::LINK_USER;
        $file->filename = 'avatar.png';

        $fileMapper = $this->createMock(FileMapper::class);
        $fileMapper->method('findAvatarForUser')->with(9)->willReturn($file);

        $fileService = $this->createMock(FileService::class);
        $fileService->expects($this->never())->method('retrieve');

        HookDispatcher::getInstance()->register('file_serve_url', function (File $f, string $disposition) {
            return 'https://bucket.s3.amazonaws.com/prefix/' . $f->file_id . '?signed';
        });

        $ctrl     = $this->makeController(['fileMapper' => $fileMapper, 'fileService' => $fileService]);
        $response = $ctrl->avatar(new Request(tokens: ['user_id' => '9']));

        $this->assertSame(302, $response->status);
        $this->assertSame('https://bucket.s3.amazonaws.com/prefix/5?signed', $response->headers['Location']);
    }

    public function testAvatarFallsThroughToByteServingWhenHookUnclaimed(): void
    {
        $file           = new File();
        $file->file_id  = 5;
        $file->link     = File::LINK_USER;
        $file->filename = 'avatar.png';

        $fileMapper = $this->createMock(FileMapper::class);
        $fileMapper->method('findAvatarForUser')->with(9)->willReturn($file);

        $fileService = $this->createMock(FileService::class);
        $fileService->expects($this->once())->method('retrieve')->willReturn('avatar bytes');

        $ctrl     = $this->makeController(['fileMapper' => $fileMapper, 'fileService' => $fileService]);
        $response = $ctrl->avatar(new Request(tokens: ['user_id' => '9']));

        $this->assertSame(200, $response->status);
        $this->assertSame('avatar bytes', $response->body);
    }
}

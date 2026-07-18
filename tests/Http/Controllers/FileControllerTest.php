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

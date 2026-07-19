<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Http\Controllers\LegacyRedirectController;
use Phorum\Http\Request;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Tests\Http\ControllerTestCase;

class LegacyRedirectControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): LegacyRedirectController
    {
        return new LegacyRedirectController(
            config:   $this->makeConfig(),
            twig:     $this->makeTwig(),
            forums:   $deps['forums']   ?? $this->createMock(ForumMapper::class),
            messages: $deps['messages'] ?? $this->createMock(MessageMapper::class),
        );
    }

    public function testIndexRedirectsNumberedForumIdToForumUrl(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request(server: ['QUERY_STRING' => '23']));
        $this->assertSame(301, $response->status);
        $this->assertSame('/forum/23', $response->headers['Location']);
    }

    public function testIndexRedirectsBareRequestToRoot(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request(server: ['QUERY_STRING' => '']));
        $this->assertSame(301, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // read
    // -------------------------------------------------------------------------

    public function testReadRedirectsForumAndThreadOnly(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->read(new Request(server: ['QUERY_STRING' => '5,123']));
        $this->assertSame(301, $response->status);
        $this->assertSame('/forum/5/thread/123', $response->headers['Location']);
    }

    public function testReadRedirectsBareRequestToRoot(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->read(new Request(server: ['QUERY_STRING' => '']));
        $this->assertSame(301, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }

    public function testReadFallsBackToRootOnlyLinkWhenMsgIdEqualsThreadId(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->read(new Request(server: ['QUERY_STRING' => '5,123,123']));
        $this->assertSame(301, $response->status);
        $this->assertSame('/forum/5/thread/123', $response->headers['Location']);
    }

    public function testReadRedirectsWithResolvedPageForDeepLinkedMessage(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(5, ['read_length' => 10]));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findMessagePosition')->willReturn(21); // page 3 at 10/page

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->read(new Request(server: ['QUERY_STRING' => '5,123,456']));
        $this->assertSame(301, $response->status);
        $this->assertSame('/forum/5/thread/123?page=3#msg-456', $response->headers['Location']);
    }

    public function testReadOmitsPageForThreadedForum(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(5, ['threaded_read' => 1]));

        $messages = $this->createMock(MessageMapper::class);
        $messages->expects($this->never())->method('findMessagePosition');

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->read(new Request(server: ['QUERY_STRING' => '5,123,456']));
        $this->assertSame(301, $response->status);
        $this->assertSame('/forum/5/thread/123#msg-456', $response->headers['Location']);
    }

    public function testReadOmitsPageWhenForumNotFound(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->read(new Request(server: ['QUERY_STRING' => '5,123,456']));
        $this->assertSame(301, $response->status);
        $this->assertSame('/forum/5/thread/123#msg-456', $response->headers['Location']);
    }

    public function testReadOmitsPageWhenPositionUnresolvable(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(5, ['read_length' => 10]));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findMessagePosition')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->read(new Request(server: ['QUERY_STRING' => '5,123,456']));
        $this->assertSame(301, $response->status);
        $this->assertSame('/forum/5/thread/123#msg-456', $response->headers['Location']);
    }
}

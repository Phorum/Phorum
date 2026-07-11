<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\ReportController;
use Phorum\Http\Request;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\ReportMapper;
use Phorum\Tests\Http\ControllerTestCase;

class ReportControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): ReportController
    {
        return new ReportController(
            config:   $this->makeConfig(),
            twig:     $this->makeTwig(),
            messages: $deps['messages'] ?? $this->createMock(MessageMapper::class),
            forums:   $deps['forums']   ?? $this->createMock(ForumMapper::class),
            reports:  $deps['reports']  ?? $this->createMock(ReportMapper::class),
        );
    }

    public function testCreateReturns404WhenMessageNotFound(): void
    {
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['messages' => $messages]);
        $response = $ctrl->create(new Request(tokens: ['message_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testCreateReturns404WhenForumNotFound(): void
    {
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($this->makeMessage());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->create(new Request(tokens: ['message_id' => '1']));
        $this->assertSame(404, $response->status);
    }

    public function testCreateRedirectsAnonymousUser(): void
    {
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($this->makeMessage());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->create(new Request(tokens: ['message_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
    }

    public function testCreateGetReturns200(): void
    {
        Auth::setUser($this->makeUser(5));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($this->makeMessage());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->create($this->makeGetRequest(tokens: ['message_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testCreatePostSavesReportAndRedirects(): void
    {
        Auth::setUser($this->makeUser(5));

        $msg = $this->makeMessage(10, 1, 10);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $reports = $this->createMock(ReportMapper::class);
        $reports->expects($this->once())->method('create')->with(10, 1, 5, 'Spam');

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums, 'reports' => $reports]);
        $response = $ctrl->create($this->makePostRequest(['reason' => 'Spam'], tokens: ['message_id' => '10']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/1/thread/10#msg-10', $response->headers['Location']);
    }

    public function testCreatePostReturns403WithBadCsrf(): void
    {
        Auth::setUser($this->makeUser(5));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($this->makeMessage());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->create(new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
            tokens: ['message_id' => '1'],
        ));
        $this->assertSame(403, $response->status);
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\ReportController;
use Phorum\Http\Request;
use Phorum\Mapper\ForumMapper;
use Phorum\Model\Message;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\ReportMapper;
use Phorum\Service\PermissionService;
use PHPUnit\Framework\Attributes\DataProvider;
use Phorum\Tests\Http\ControllerTestCase;

class ReportControllerTest extends ControllerTestCase
{
    /**
     * Build a ReportController with mocked collaborators. `canRead` and
     * `canModerate` default to the common case (readable forum, ordinary user)
     * and are overridable per test.
     *
     * @param array $deps Optional overrides: messages, forums, reports, perms,
     *                    canRead, canModerate.
     */
    private function makeController(array $deps = []): ReportController
    {
        $perms = $deps['perms'] ?? $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn($deps['canRead'] ?? true);
        $perms->method('canModerate')->willReturn($deps['canModerate'] ?? false);

        return new ReportController(
            config:   $this->makeConfig(),
            twig:     $this->makeTwig(),
            messages: $deps['messages'] ?? $this->createMock(MessageMapper::class),
            forums:   $deps['forums']   ?? $this->createMock(ForumMapper::class),
            reports:  $deps['reports']  ?? $this->createMock(ReportMapper::class),
            perms:    $perms,
        );
    }

    /**
     * Wire up message+forum mocks for a single message, so the permission and
     * visibility tests below don't each repeat the same two mocks.
     *
     * @param Message $msg The message /message/{id}/report should resolve to.
     */
    private function controllerFor(Message $msg, array $deps = []): ReportController
    {
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        return $this->makeController($deps + ['messages' => $messages, 'forums' => $forums]);
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

    // -------------------------------------------------------------------------
    // Read permission and message visibility
    // -------------------------------------------------------------------------

    /**
     * The confirmation page renders the message body, so a user without read
     * permission on the containing forum must not reach it — this route had no
     * permission check at all, making it a way to read any forum's posts.
     */
    public function testCreateReturns403WhenForumNotReadable(): void
    {
        Auth::setUser($this->makeUser(5));

        $ctrl     = $this->controllerFor($this->makeMessage(), ['canRead' => false]);
        $response = $ctrl->create($this->makeGetRequest(tokens: ['message_id' => '1']));

        $this->assertSame(403, $response->status);
    }

    /** The same check gates the POST, not just the page render. */
    public function testCreatePostReturns403WhenForumNotReadable(): void
    {
        Auth::setUser($this->makeUser(5));

        $reports = $this->createMock(ReportMapper::class);
        $reports->expects($this->never())->method('create');

        $ctrl     = $this->controllerFor($this->makeMessage(), ['canRead' => false, 'reports' => $reports]);
        $response = $ctrl->create($this->makePostRequest(['reason' => 'Spam'], tokens: ['message_id' => '1']));

        $this->assertSame(403, $response->status);
    }

    /**
     * Read permission alone isn't enough: a thread view never shows deleted,
     * pending, or other people's shadow-banned posts, so neither may this page.
     *
     * @param int $status   The message's status column.
     * @param int $authorId The message author's user id (the viewer is user 5).
     */
    #[DataProvider('hiddenMessageProvider')]
    public function testCreateReturns404ForMessagesTheViewerCannotSee(int $status, int $authorId): void
    {
        Auth::setUser($this->makeUser(5));

        $msg          = $this->makeMessage();
        $msg->status  = $status;
        $msg->user_id = $authorId;

        $response = $this->controllerFor($msg)->create($this->makeGetRequest(tokens: ['message_id' => '1']));

        $this->assertSame(404, $response->status);
    }

    /**
     * Message states an ordinary reader should never be shown: deleted (gone
     * for everyone), and pending/shadow-banned posts written by someone else.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function hiddenMessageProvider(): array
    {
        return [
            'deleted, own post'      => [MessageMapper::STATUS_DELETED, 5],
            'deleted, other author'  => [MessageMapper::STATUS_DELETED, 99],
            'pending, other author'  => [MessageMapper::STATUS_UNAPPROVED, 99],
            'shadow, other author'   => [MessageMapper::STATUS_SHADOW, 99],
        ];
    }

    /** An author can still reach their own pending post. */
    public function testCreateAllowsAuthorToReportTheirOwnPendingMessage(): void
    {
        Auth::setUser($this->makeUser(5));

        $msg          = $this->makeMessage();
        $msg->status  = MessageMapper::STATUS_UNAPPROVED;
        $msg->user_id = 5;

        $response = $this->controllerFor($msg)->create($this->makeGetRequest(tokens: ['message_id' => '1']));

        $this->assertSame(200, $response->status);
    }

    /** A moderator of the forum can still reach a pending post by someone else. */
    public function testCreateAllowsModeratorToReportPendingMessage(): void
    {
        Auth::setUser($this->makeUser(5));

        $msg          = $this->makeMessage();
        $msg->status  = MessageMapper::STATUS_UNAPPROVED;
        $msg->user_id = 99;

        $response = $this->controllerFor($msg, ['canModerate' => true])
            ->create($this->makeGetRequest(tokens: ['message_id' => '1']));

        $this->assertSame(200, $response->status);
    }
}

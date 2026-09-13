<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\SubscriptionController;
use Phorum\Http\Request;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Service\PermissionService;
use Phorum\Service\SubscriptionService;
use Phorum\Tests\Http\ControllerTestCase;

class SubscriptionControllerTest extends ControllerTestCase
{
    /**
     * Build a SubscriptionController with mocked collaborators. `canRead`
     * defaults to true; pass `canRead => false` for the permission tests.
     *
     * @param array $deps Optional overrides: subscriptionService, messages,
     *                    forums, perms, canRead.
     */
    private function makeController(array $deps = []): SubscriptionController
    {
        $perms = $deps['perms'] ?? $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn($deps['canRead'] ?? true);

        return new SubscriptionController(
            config:              $this->makeConfig(),
            twig:                $this->makeTwig(),
            subscriptionService: $deps['subscriptionService'] ?? $this->createMock(SubscriptionService::class),
            messages:            $deps['messages']            ?? $this->createMock(MessageMapper::class),
            forums:              $deps['forums']              ?? $this->createMock(ForumMapper::class),
            perms:               $perms,
        );
    }

    // -------------------------------------------------------------------------
    // follow — auth guard
    // -------------------------------------------------------------------------

    public function testFollowRedirectsAnonymousUser(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->follow(new Request(
            server: ['REQUEST_URI' => '/follow/5'],
            tokens: ['thread_id' => '5'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('/login', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // follow — 404 cases
    // -------------------------------------------------------------------------

    public function testFollowReturns404WhenThreadNotFound(): void
    {
        Auth::setUser($this->makeUser());
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['messages' => $messages]);
        $response = $ctrl->follow(new Request(
            server: ['REQUEST_URI' => '/follow/99'],
            tokens: ['thread_id' => '99'],
        ));
        $this->assertSame(404, $response->status);
    }

    public function testFollowReturns404WhenMessageIsNotRoot(): void
    {
        Auth::setUser($this->makeUser());
        $reply            = $this->makeMessage(5, 1, 1);
        $reply->parent_id = 1; // reply, not root

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($reply);

        $ctrl     = $this->makeController(['messages' => $messages]);
        $response = $ctrl->follow(new Request(
            server: ['REQUEST_URI' => '/follow/5'],
            tokens: ['thread_id' => '5'],
        ));
        $this->assertSame(404, $response->status);
    }

    public function testFollowReturns404WhenForumNotFound(): void
    {
        Auth::setUser($this->makeUser());
        $root = $this->makeMessage(5, 1, 5);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($root);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->follow(new Request(
            server: ['REQUEST_URI' => '/follow/5'],
            tokens: ['thread_id' => '5'],
        ));
        $this->assertSame(404, $response->status);
    }

    // -------------------------------------------------------------------------
    // follow — GET shows form
    // -------------------------------------------------------------------------

    public function testFollowGetReturnsForm(): void
    {
        Auth::setUser($this->makeUser());
        $root = $this->makeMessage(5, 1, 5);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($root);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_NONE);

        $ctrl     = $this->makeController([
            'messages'            => $messages,
            'forums'              => $forums,
            'subscriptionService' => $subs,
        ]);
        $response = $ctrl->follow($this->makeGetRequest(
            server: ['REQUEST_URI' => '/follow/5'],
            tokens: ['thread_id' => '5'],
        ));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // follow — POST subscribe
    // -------------------------------------------------------------------------

    public function testFollowPostSubscribeEmailRedirects(): void
    {
        Auth::setUser($this->makeUser());
        $root = $this->makeMessage(5, 1, 5);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($root);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_NONE);
        $subs->expects($this->once())->method('subscribe')->with(
            1, 1, 5, SubscriberMapper::SUB_MESSAGE
        );

        $ctrl     = $this->makeController([
            'messages'            => $messages,
            'forums'              => $forums,
            'subscriptionService' => $subs,
        ]);
        $response = $ctrl->follow($this->makePostRequest(
            post:   ['action' => 'subscribe_email'],
            server: ['REQUEST_URI' => '/follow/5'],
            tokens: ['thread_id' => '5'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/1/thread/5', $response->headers['Location']);
    }

    public function testFollowPostSubscribeEmailIgnoredWhenForumDisallowsEmailNotify(): void
    {
        Auth::setUser($this->makeUser());
        $root = $this->makeMessage(5, 1, 5);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($root);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['allow_email_notify' => 0]));

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_NONE);
        $subs->expects($this->never())->method('subscribe');

        $ctrl     = $this->makeController([
            'messages'            => $messages,
            'forums'              => $forums,
            'subscriptionService' => $subs,
        ]);
        $response = $ctrl->follow($this->makePostRequest(
            post:   ['action' => 'subscribe_email'],
            server: ['REQUEST_URI' => '/follow/5'],
            tokens: ['thread_id' => '5'],
        ));
        $this->assertSame(302, $response->status);
    }

    public function testFollowPostUnsubscribeRedirects(): void
    {
        Auth::setUser($this->makeUser());
        $root = $this->makeMessage(5, 1, 5);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($root);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_MESSAGE);
        $subs->expects($this->once())->method('unsubscribe')->with(1, 1, 5);

        $ctrl     = $this->makeController([
            'messages'            => $messages,
            'forums'              => $forums,
            'subscriptionService' => $subs,
        ]);
        $response = $ctrl->follow($this->makePostRequest(
            post:   ['action' => 'unsubscribe'],
            server: ['REQUEST_URI' => '/follow/5'],
            tokens: ['thread_id' => '5'],
        ));
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // follow — quick-action GET shows confirmation form
    // -------------------------------------------------------------------------

    public function testFollowQuickActionGetShowsConfirmForm(): void
    {
        Auth::setUser($this->makeUser());
        $root = $this->makeMessage(5, 1, 5);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($root);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_MESSAGE);

        $ctrl     = $this->makeController([
            'messages'            => $messages,
            'forums'              => $forums,
            'subscriptionService' => $subs,
        ]);
        $response = $ctrl->follow($this->makeGetRequest(
            query:  ['action' => 'remove'],
            server: ['REQUEST_URI' => '/follow/5?action=remove'],
            tokens: ['thread_id' => '5'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testFollowQuickActionPostUnsubscribesAndRedirects(): void
    {
        Auth::setUser($this->makeUser());
        $root = $this->makeMessage(5, 1, 5);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($root);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_MESSAGE);
        $subs->expects($this->once())->method('unsubscribe')->with(1, 1, 5);

        $ctrl     = $this->makeController([
            'messages'            => $messages,
            'forums'              => $forums,
            'subscriptionService' => $subs,
        ]);
        $token    = \Phorum\Core\CsrfGuard::token();
        $request  = new Request(
            post:   [\Phorum\Core\CsrfGuard::fieldName() => $token],
            query:  ['action' => 'remove'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/follow/5?action=remove'],
            tokens: ['thread_id' => '5'],
        );
        $response = $ctrl->follow($request);
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/1/thread/5', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // followForum
    // -------------------------------------------------------------------------

    public function testFollowForumRedirectsAnonymousUser(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->followForum(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    public function testFollowForumReturns404WhenForumNotFound(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->followForum(new Request(tokens: ['forum_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testFollowForumReturns404ForFolder(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['folder_flag' => 1]));

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->followForum(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(404, $response->status);
    }

    public function testFollowForumGetReturnsForm(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_NONE);

        $ctrl     = $this->makeController(['forums' => $forums, 'subscriptionService' => $subs]);
        $response = $ctrl->followForum(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testFollowForumPostSubscribeEmailRedirects(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_NONE);
        $subs->expects($this->once())->method('subscribe')->with(1, 1, 0, SubscriberMapper::SUB_MESSAGE);

        $ctrl     = $this->makeController(['forums' => $forums, 'subscriptionService' => $subs]);
        $response = $ctrl->followForum($this->makePostRequest(
            post:   ['action' => 'subscribe_email'],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/1', $response->headers['Location']);
    }

    public function testFollowForumPostSubscribeEmailIgnoredWhenForumDisallowsEmailNotify(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['allow_email_notify' => 0]));

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_NONE);
        $subs->expects($this->never())->method('subscribe');

        $ctrl     = $this->makeController(['forums' => $forums, 'subscriptionService' => $subs]);
        $response = $ctrl->followForum($this->makePostRequest(
            post:   ['action' => 'subscribe_email'],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
    }

    public function testFollowForumPostUnsubscribeRedirects(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_MESSAGE);
        $subs->expects($this->once())->method('unsubscribe')->with(1, 1, 0);

        $ctrl     = $this->makeController(['forums' => $forums, 'subscriptionService' => $subs]);
        $response = $ctrl->followForum($this->makePostRequest(
            post:   ['action' => 'unsubscribe'],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/1', $response->headers['Location']);
    }

    public function testFollowForumQuickActionGetShowsConfirmForm(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_MESSAGE);

        $ctrl     = $this->makeController(['forums' => $forums, 'subscriptionService' => $subs]);
        $response = $ctrl->followForum($this->makeGetRequest(
            query:  ['action' => 'remove'],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testFollowForumQuickActionPostUnsubscribesAndRedirects(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(SubscriberMapper::SUB_MESSAGE);
        $subs->expects($this->once())->method('unsubscribe')->with(1, 1, 0);

        $ctrl    = $this->makeController(['forums' => $forums, 'subscriptionService' => $subs]);
        $token   = \Phorum\Core\CsrfGuard::token();
        $request = new Request(
            post:   [\Phorum\Core\CsrfGuard::fieldName() => $token],
            query:  ['action' => 'remove'],
            server: ['REQUEST_METHOD' => 'POST'],
            tokens: ['forum_id' => '1'],
        );
        $response = $ctrl->followForum($request);
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/1', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // Read-permission gating
    // -------------------------------------------------------------------------

    /**
     * Following a thread in a forum the user can't read must be refused: the
     * page prints the subject, and an email subscription would go on
     * delivering the subject of every future reply.
     */
    public function testFollowIsForbiddenWhenForumNotReadable(): void
    {
        Auth::setUser($this->makeUser(5));

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($this->makeMessage(10, 1, 10));
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $service = $this->createMock(SubscriptionService::class);
        $service->expects($this->never())->method('subscribe');

        $ctrl = $this->makeController([
            'messages'            => $messages,
            'forums'              => $forums,
            'subscriptionService' => $service,
            'canRead'             => false,
        ]);

        $response = $ctrl->follow($this->makePostRequest(
            ['action' => 'subscribe_email'],
            tokens: ['thread_id' => '10'],
        ));

        $this->assertSame(403, $response->status);
    }

    /** Forum-wide follow is gated the same way — it mails every new thread. */
    public function testFollowForumIsForbiddenWhenForumNotReadable(): void
    {
        Auth::setUser($this->makeUser(5));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $service = $this->createMock(SubscriptionService::class);
        $service->expects($this->never())->method('subscribe');

        $ctrl = $this->makeController([
            'forums'              => $forums,
            'subscriptionService' => $service,
            'canRead'             => false,
        ]);

        $response = $ctrl->followForum($this->makePostRequest(
            ['action' => 'subscribe_email'],
            tokens: ['forum_id' => '1'],
        ));

        $this->assertSame(403, $response->status);
    }
}

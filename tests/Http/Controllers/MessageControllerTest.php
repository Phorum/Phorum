<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\MessageController;
use Phorum\Http\Request;
use Phorum\Mapper\BanMapper;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\NewflagMapper;
use Phorum\Mapper\SearchMapper;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\AnnouncementService;
use Phorum\Service\BanService;
use Phorum\Service\CustomFieldService;
use Phorum\Service\FileService;
use Phorum\Service\FloodControlService;
use Phorum\Service\MessageService;
use Phorum\Service\NewflagService;
use Phorum\Service\PermissionService;
use Phorum\Service\SubscriptionService;
use Phorum\Tests\Http\ControllerTestCase;

class MessageControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): MessageController
    {
        $perms = $deps['perms'] ?? $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn($deps['canRead'] ?? true);
        $perms->method('canReply')->willReturn($deps['canReply'] ?? true);
        $perms->method('canNewThread')->willReturn($deps['canNewThread'] ?? true);
        $perms->method('canModerate')->willReturn($deps['canModerate'] ?? false);

        if (isset($deps['floodControl'])) {
            $floodControl = $deps['floodControl'];
        } else {
            $floodControl = $this->createMock(FloodControlService::class);
            $floodControl->method('secondsRemaining')->willReturn($deps['floodWait'] ?? 0);
        }

        return new MessageController(
            config:         $this->makeConfig(),
            twig:           $this->makeTwig(),
            forums:         $deps['forums']         ?? $this->createMock(ForumMapper::class),
            messages:       $deps['messages']       ?? $this->createMock(MessageMapper::class),
            perms:          $perms,
            fileService:    $deps['fileService']    ?? $this->createMock(FileService::class),
            newflags:       $deps['newflags']       ?? $this->createMock(NewflagService::class),
            banService:     $deps['banService']     ?? $this->createMock(BanService::class),
            subscriptions:  $deps['subscriptions']  ?? $this->createMock(SubscriptionService::class),
            cfService:      $deps['cfService']      ?? $this->createMock(CustomFieldService::class),
            searchIndex:    $deps['searchIndex']    ?? $this->createMock(SearchMapper::class),
            messageService: $deps['messageService'] ?? $this->createMock(MessageService::class),
            users:          $deps['users']          ?? $this->createMock(UserMapper::class),
            announcements:  $deps['announcements']  ?? $this->createMock(AnnouncementService::class),
            floodControl:   $floodControl,
        );
    }

    // -------------------------------------------------------------------------
    // thread
    // -------------------------------------------------------------------------

    public function testThreadReturns404WhenForumNotFound(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '10']));
        $this->assertSame(404, $response->status);
    }

    public function testThreadReturns403WhenCannotRead(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['forums' => $forums, 'canRead' => false]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '10']));
        $this->assertSame(403, $response->status);
    }

    public function testThreadReturns404WhenNoMessages(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByThread')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '10']));
        $this->assertSame(404, $response->status);
    }

    public function testThreadReturns404WhenRootNotInThread(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        // Returns messages but none has message_id == thread_id (10)
        $msg = $this->makeMessage(99, 1, 10);
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByThread')->willReturn([$msg]);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '10']));
        $this->assertSame(404, $response->status);
    }

    public function testThreadReturns200WhenReadable(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $root = $this->makeMessage(10, 1, 10);
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findByThread')->willReturn([$root]);

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(0);

        $ctrl     = $this->makeController([
            'forums'       => $forums,
            'messages'     => $messages,
            'subscriptions' => $subs,
        ]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '10']));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // post
    // -------------------------------------------------------------------------

    public function testPostReturns404WhenForumNotFound(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->post(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(404, $response->status);
    }

    public function testPostReturns404ForFolder(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['folder_flag' => 1]));

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->post(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(404, $response->status);
    }

    public function testPostRedirectsAnonymousUser(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->post(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
    }

    public function testPostReturns403WhenCannotStartThread(): void
    {
        Auth::setUser($this->makeUser());
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['forums' => $forums, 'canNewThread' => false]);
        $response = $ctrl->post(new Request(tokens: ['forum_id' => '1']));
        $this->assertSame(403, $response->status);
    }

    public function testPostGetFormReturns200(): void
    {
        Auth::setUser($this->makeUser());
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->post($this->makeGetRequest(tokens: ['forum_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testPostValidationErrorForEmptyBody(): void
    {
        Auth::setUser($this->makeUser());
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['forums' => $forums]);
        $response = $ctrl->post($this->makePostRequest(
            post:   ['subject' => 'Hello', 'body' => ''],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testPostSuccessRedirects(): void
    {
        $user = $this->makeUser();
        Auth::setUser($user);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $ban = $this->createMock(BanService::class);
        $ban->method('checkIp')->willReturn(false);
        $ban->method('checkEmail')->willReturn(false);
        $ban->method('checkUsername')->willReturn(false);
        $ban->method('checkSpamWords')->willReturn(false);

        $postedMsg        = $this->makeMessage(42, 1, 42);
        $postedMsg->status = 2;

        $msgService = $this->createMock(MessageService::class);
        $msgService->method('post')->willReturn($postedMsg);

        $search = $this->createMock(SearchMapper::class);
        $search->expects($this->once())->method('indexMessage');

        $subs = $this->createMock(SubscriptionService::class);
        $subs->expects($this->once())->method('notifySubscribers');
        $subs->expects($this->once())->method('notifyModerators');

        $ctrl     = $this->makeController([
            'forums'        => $forums,
            'banService'    => $ban,
            'messageService'=> $msgService,
            'searchIndex'   => $search,
            'subscriptions' => $subs,
        ]);
        $response = $ctrl->post($this->makePostRequest(
            post:   ['subject' => 'New Thread', 'body' => 'Hello world!'],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('/forum/1/thread/42', $response->headers['Location']);
    }

    public function testPostBlockedByFloodControl(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $msgService = $this->createMock(MessageService::class);
        $msgService->expects($this->never())->method('post');

        $ctrl     = $this->makeController([
            'forums'        => $forums,
            'messageService'=> $msgService,
            'floodWait'     => 12,
        ]);
        $response = $ctrl->post($this->makePostRequest(
            post:   ['subject' => 'New Thread', 'body' => 'Hello world!'],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testPostFloodControlSkippedForModerators(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $ban = $this->createMock(BanService::class);
        $ban->method('checkIp')->willReturn(false);
        $ban->method('checkEmail')->willReturn(false);
        $ban->method('checkUsername')->willReturn(false);
        $ban->method('checkSpamWords')->willReturn(false);

        $postedMsg         = $this->makeMessage(43, 1, 43);
        $postedMsg->status = 2;

        $msgService = $this->createMock(MessageService::class);
        $msgService->method('post')->willReturn($postedMsg);

        $floodControl = $this->createMock(FloodControlService::class);
        $floodControl->expects($this->never())->method('secondsRemaining');

        $ctrl     = $this->makeController([
            'forums'        => $forums,
            'banService'    => $ban,
            'messageService'=> $msgService,
            'canModerate'   => true,
            'floodControl'  => $floodControl,
        ]);
        $response = $ctrl->post($this->makePostRequest(
            post:   ['subject' => 'New Thread', 'body' => 'Hello world!'],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // editMessage
    // -------------------------------------------------------------------------

    public function testEditMessageReturns404WhenNotFound(): void
    {
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['messages' => $messages]);
        $response = $ctrl->editMessage(new Request(tokens: ['message_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testEditMessageReturns403WhenNotOwnerOrModerator(): void
    {
        $user      = $this->makeUser(5);
        Auth::setUser($user);

        $msg          = $this->makeMessage(1, 1, 1);
        $msg->user_id = 99; // different user owns the message

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums, 'canModerate' => false]);
        $response = $ctrl->editMessage(new Request(tokens: ['message_id' => '1']));
        $this->assertSame(403, $response->status);
    }

    public function testEditMessageGetFormReturns200ForOwner(): void
    {
        $user = $this->makeUser(1);
        Auth::setUser($user);

        $msg          = $this->makeMessage(1, 1, 1);
        $msg->user_id = 1;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $fileService = $this->createMock(FileService::class);
        $fileService->method('getAttachments')->willReturn([]);

        $ctrl     = $this->makeController([
            'messages'   => $messages,
            'forums'     => $forums,
            'fileService' => $fileService,
        ]);
        $response = $ctrl->editMessage($this->makeGetRequest(tokens: ['message_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testEditMessagePostValidationErrorForEmptySubject(): void
    {
        $user = $this->makeUser(1);
        Auth::setUser($user);

        $msg          = $this->makeMessage(1, 1, 1);
        $msg->user_id = 1;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $fileService = $this->createMock(FileService::class);
        $fileService->method('getAttachments')->willReturn([]);

        $ctrl     = $this->makeController([
            'messages'   => $messages,
            'forums'     => $forums,
            'fileService' => $fileService,
        ]);
        $response = $ctrl->editMessage($this->makePostRequest(
            post:   ['subject' => '', 'body' => 'Some content'],
            tokens: ['message_id' => '1'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testEditMessagePostSuccessRedirects(): void
    {
        $user = $this->makeUser(1);
        Auth::setUser($user);

        $msg           = $this->makeMessage(1, 1, 1);
        $msg->user_id  = 1;
        $msg->status   = MessageMapper::STATUS_APPROVED;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $updatedMsg = $this->makeMessage(1, 1, 1);
        $updatedMsg->status = MessageMapper::STATUS_APPROVED;

        $msgService = $this->createMock(MessageService::class);
        $msgService->method('edit')->willReturn($updatedMsg);

        $search = $this->createMock(SearchMapper::class);
        $search->expects($this->once())->method('indexMessage');

        $fileService = $this->createMock(FileService::class);
        $fileService->method('getAttachments')->willReturn([]);

        $ctrl     = $this->makeController([
            'messages'      => $messages,
            'forums'        => $forums,
            'messageService'=> $msgService,
            'searchIndex'   => $search,
            'fileService'   => $fileService,
        ]);
        $response = $ctrl->editMessage($this->makePostRequest(
            post:   ['subject' => 'Updated Subject', 'body' => 'Updated body.'],
            tokens: ['message_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
    }
}

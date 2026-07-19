<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\CsrfGuard;
use Phorum\Http\Controllers\MessageController;
use Phorum\Http\Request;
use Phorum\Mapper\BanMapper;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\NewflagMapper;
use Phorum\Mapper\SearchMapper;
use Phorum\Mapper\SettingMapper;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\Message;
use Phorum\Service\AnnouncementService;
use Phorum\Service\BanService;
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
        $perms->method('canEdit')->willReturn($deps['canEdit'] ?? true);

        if (isset($deps['floodControl'])) {
            $floodControl = $deps['floodControl'];
        } else {
            $floodControl = $this->createMock(FloodControlService::class);
            $floodControl->method('secondsRemaining')->willReturn($deps['floodWait'] ?? 0);
        }

        $settings = $deps['settings'] ?? $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn($deps['edit_time_limit'] ?? null);

        return new MessageController(
            config:         $this->makeConfig(),
            twig:           $deps['twig'] ?? $this->makeTwig(),
            forums:         $deps['forums']         ?? $this->createMock(ForumMapper::class),
            messages:       $deps['messages']       ?? $this->createMock(MessageMapper::class),
            perms:          $perms,
            fileService:    $deps['fileService']    ?? $this->createMock(FileService::class),
            newflags:       $deps['newflags']       ?? $this->createMock(NewflagService::class),
            banService:     $deps['banService']     ?? $this->createMock(BanService::class),
            subscriptions:  $deps['subscriptions']  ?? $this->createMock(SubscriptionService::class),
            searchIndex:    $deps['searchIndex']    ?? $this->createMock(SearchMapper::class),
            messageService: $deps['messageService'] ?? $this->createMock(MessageService::class),
            users:          $deps['users']          ?? $this->createMock(UserMapper::class),
            announcements:  $deps['announcements']  ?? $this->createMock(AnnouncementService::class),
            floodControl:   $floodControl,
            settings:       $settings,
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

        $root = $this->makeMessage(10, 1, 10);
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findRoot')->willReturn($root);
        $messages->method('findByThread')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '10']));
        $this->assertSame(404, $response->status);
    }

    public function testThreadReturns404WhenRootNotFound(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findRoot')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '10']));
        $this->assertSame(404, $response->status);
    }

    public function testThreadReturns404WhenRootNotInThreadedTree(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['threaded_read' => 1]));

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
        $messages->method('findRoot')->willReturn($root);
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

    public function testThreadDefaultsToPageOne(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $root = $this->makeMessage(10, 1, 10);
        $root->thread_count = 5;
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findRoot')->willReturn($root);
        $messages->expects($this->once())->method('findByThread')->with(10, null, 25, 0)->willReturn([$root]);

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(0);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages, 'subscriptions' => $subs]);
        $response = $ctrl->thread(new Request(tokens: ['forum_id' => '1', 'thread_id' => '10']));
        $this->assertSame(200, $response->status);
    }

    public function testThreadUsesReadLengthForPerPage(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['read_length' => 10]));

        $root = $this->makeMessage(10, 1, 10);
        $root->thread_count = 25;
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findRoot')->willReturn($root);
        $messages->expects($this->once())->method('findByThread')->with(10, null, 10, 10)->willReturn([$root]);

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(0);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages, 'subscriptions' => $subs]);
        $response = $ctrl->thread(new Request(
            tokens: ['forum_id' => '1', 'thread_id' => '10'],
            query:  ['page' => '2'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testThreadClampsPageBeyondTotalPages(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['read_length' => 10]));

        $root = $this->makeMessage(10, 1, 10);
        $root->thread_count = 15; // 2 pages at 10/page
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findRoot')->willReturn($root);
        // Clamped to page 2 (offset 10), not the requested page 99.
        $messages->expects($this->once())->method('findByThread')->with(10, null, 10, 10)->willReturn([$root]);

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(0);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages, 'subscriptions' => $subs]);
        $response = $ctrl->thread(new Request(
            tokens: ['forum_id' => '1', 'thread_id' => '10'],
            query:  ['page' => '99'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testThreadedModeIgnoresPageParam(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['threaded_read' => 1]));

        $root = $this->makeMessage(10, 1, 10);
        $messages = $this->createMock(MessageMapper::class);
        // Threaded mode fetches the whole thread via findByThread(), no findRoot().
        $messages->expects($this->once())->method('findByThread')->with(10, null)->willReturn([$root]);

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(0);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages, 'subscriptions' => $subs]);
        $response = $ctrl->thread(new Request(
            tokens: ['forum_id' => '1', 'thread_id' => '10'],
            query:  ['page' => '5'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testThreadRedirectsWhenMsgParamResolvesToDifferentPage(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['read_length' => 10]));

        $root = $this->makeMessage(10, 1, 10);
        $root->thread_count = 25;
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findRoot')->willReturn($root);
        $messages->method('findMessagePosition')->willReturn(21); // lands on page 3

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->thread(new Request(
            tokens: ['forum_id' => '1', 'thread_id' => '10'],
            query:  ['msg' => '55'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('page=3', $response->headers['Location']);
        $this->assertStringContainsString('#msg-55', $response->headers['Location']);
    }

    public function testThreadDoesNotRedirectWhenMsgParamAlreadyOnRequestedPage(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['read_length' => 10]));

        $root = $this->makeMessage(10, 1, 10);
        $root->thread_count = 25;
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findRoot')->willReturn($root);
        $messages->method('findMessagePosition')->willReturn(11); // page 2
        $messages->method('findByThread')->willReturn([$root]);

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(0);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages, 'subscriptions' => $subs]);
        $response = $ctrl->thread(new Request(
            tokens: ['forum_id' => '1', 'thread_id' => '10'],
            query:  ['msg' => '55', 'page' => '2'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testThreadIgnoresMsgParamWhenPositionUnresolvable(): void
    {
        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $root = $this->makeMessage(10, 1, 10);
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findRoot')->willReturn($root);
        $messages->method('findMessagePosition')->willReturn(null);
        $messages->method('findByThread')->willReturn([$root]);

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(0);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages, 'subscriptions' => $subs]);
        $response = $ctrl->thread(new Request(
            tokens: ['forum_id' => '1', 'thread_id' => '10'],
            query:  ['msg' => '999'],
        ));
        $this->assertSame(200, $response->status);
    }

    /**
     * canModerate/canEdit/edit_time_limit are the same for every message in
     * the thread — thread() must resolve each once per request, not once per
     * message (a regression that turned a thread render into an N+1 query
     * storm of permission checks).
     */
    public function testThreadResolvesEditPermissionsOncePerRequestNotPerMessage(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $root  = $this->makeMessage(10, 1, 10);
        $reply1 = $this->makeMessage(11, 1, 10);
        $reply2 = $this->makeMessage(12, 1, 10);
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findRoot')->willReturn($root);
        $messages->method('findByThread')->willReturn([$root, $reply1, $reply2]);

        $perms = $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn(true);
        $perms->method('canReply')->willReturn(true);
        $perms->method('canModerate')->willReturn(false);
        $perms->expects($this->once())->method('canEdit')->willReturn(true);

        $settings = $this->createMock(SettingMapper::class);
        $settings->expects($this->once())->method('getSetting')->with('edit_time_limit')->willReturn(null);

        $subs = $this->createMock(SubscriptionService::class);
        $subs->method('getSubscription')->willReturn(0);

        $newflags = $this->createMock(NewflagService::class);
        $newflags->method('getNewMessageIds')->willReturn([]);

        $announcements = $this->createMock(AnnouncementService::class);
        $announcements->method('getAnnouncementsFor')->willReturn([]);

        // Built directly (not via makeController()) so the perms/settings
        // mocks above keep their exact once() expectations — makeController()
        // unconditionally layers its own default stubs on top of any mock it's given.
        $ctrl = new MessageController(
            config:        $this->makeConfig(),
            twig:          $this->makeTwig(),
            forums:        $forums,
            messages:      $messages,
            perms:         $perms,
            fileService:   $this->createMock(FileService::class),
            newflags:      $newflags,
            subscriptions: $subs,
            users:         $this->createMock(UserMapper::class),
            announcements: $announcements,
            settings:      $settings,
        );
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

    public function testPostReplyRedirectsToResolvedPageWhenForumIsPaginatedFlat(): void
    {
        $user = $this->makeUser();
        Auth::setUser($user);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['read_length' => 10]));

        $ban = $this->createMock(BanService::class);
        $ban->method('checkIp')->willReturn(false);
        $ban->method('checkEmail')->willReturn(false);
        $ban->method('checkUsername')->willReturn(false);
        $ban->method('checkSpamWords')->willReturn(false);

        $postedMsg        = $this->makeMessage(42, 1, 5);
        $postedMsg->status = 2;

        $msgService = $this->createMock(MessageService::class);
        $msgService->method('post')->willReturn($postedMsg);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findMessagePosition')->willReturn(21); // page 3 at 10/page

        $ctrl     = $this->makeController([
            'forums'        => $forums,
            'banService'    => $ban,
            'messageService'=> $msgService,
            'messages'      => $messages,
        ]);
        $response = $ctrl->post($this->makePostRequest(
            post:   ['subject' => 'Re: Thread', 'body' => 'Hello world!'],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('/forum/1/thread/5?page=3#msg-42', $response->headers['Location']);
    }

    /**
     * Built directly (not via makeController()) since makeController()'s
     * settings stub returns one fixed value for every getSetting() key
     * (see the edit_time_limit comment on testThreadResolvesEditPermissions...
     * above) — these tests need file_uploads to differ per-test.
     */
    private function makeControllerWithSettings(SettingMapper $settings, array $deps = []): MessageController
    {
        $perms = $this->createMock(PermissionService::class);
        $perms->method('canRead')->willReturn(true);
        $perms->method('canReply')->willReturn(true);
        $perms->method('canNewThread')->willReturn(true);
        $perms->method('canModerate')->willReturn(false);
        $perms->method('canEdit')->willReturn(true);

        $ban = $this->createMock(BanService::class);
        $ban->method('checkIp')->willReturn(false);
        $ban->method('checkEmail')->willReturn(false);
        $ban->method('checkUsername')->willReturn(false);
        $ban->method('checkSpamWords')->willReturn(false);

        $floodControl = $this->createMock(FloodControlService::class);
        $floodControl->method('secondsRemaining')->willReturn(0);

        return new MessageController(
            config:         $this->makeConfig(),
            twig:           $this->makeTwig(),
            forums:         $deps['forums']         ?? $this->createMock(ForumMapper::class),
            messages:       $deps['messages']       ?? $this->createMock(MessageMapper::class),
            perms:          $perms,
            fileService:    $deps['fileService']    ?? $this->createMock(FileService::class),
            newflags:       $this->createMock(NewflagService::class),
            banService:     $ban,
            subscriptions:  $this->createMock(SubscriptionService::class),
            searchIndex:    $this->createMock(SearchMapper::class),
            messageService: $deps['messageService'] ?? $this->createMock(MessageService::class),
            users:          $this->createMock(UserMapper::class),
            announcements:  $this->createMock(AnnouncementService::class),
            floodControl:   $floodControl,
            settings:       $settings,
        );
    }

    private function makePostRequestWithFile(array $post, array $tokens = []): Request
    {
        return new Request(
            post:   array_merge([CsrfGuard::fieldName() => CsrfGuard::token()], $post),
            server: ['REQUEST_METHOD' => 'POST'],
            tokens: $tokens,
            files: [
                'files' => [
                    'name'     => ['photo.jpg'],
                    'type'     => ['image/jpeg'],
                    'tmp_name' => ['/tmp/phpFAKE'],
                    'error'    => [UPLOAD_ERR_OK],
                    'size'     => [1234],
                ],
            ],
        );
    }

    public function testStoreUploadsBlockedWhenGloballyDisabledForNonAdmin(): void
    {
        Auth::setUser($this->makeUser(1, admin: false));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $postedMsg         = $this->makeMessage(42, 1, 42);
        $postedMsg->status = 2;
        $msgService = $this->createMock(MessageService::class);
        $msgService->method('post')->willReturn($postedMsg);

        $fileService = $this->createMock(FileService::class);
        $fileService->expects($this->never())->method('store');

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturnCallback(fn($k) => $k === 'file_uploads' ? 0 : null);

        $ctrl = $this->makeControllerWithSettings($settings, [
            'forums'         => $forums,
            'messageService' => $msgService,
            'fileService'    => $fileService,
        ]);
        $response = $ctrl->post($this->makePostRequestWithFile(
            ['subject' => 'New Thread', 'body' => 'Hello world!'],
            ['forum_id' => '1'],
        ));

        // storeUploads() adds an error, so post() falls through to re-render
        // the form instead of redirecting, even though the message itself
        // (created before storeUploads runs) already succeeded.
        $this->assertSame(200, $response->status);
    }

    public function testStoreUploadsProceedsWhenGloballyDisabledButUserIsAdmin(): void
    {
        Auth::setUser($this->makeUser(1, admin: true));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $postedMsg         = $this->makeMessage(42, 1, 42);
        $postedMsg->status = 2;
        $msgService = $this->createMock(MessageService::class);
        $msgService->method('post')->willReturn($postedMsg);

        $fileService = $this->createMock(FileService::class);
        $fileService->method('getAttachments')->willReturn([]);
        $fileService->expects($this->once())->method('store');

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturnCallback(fn($k) => $k === 'file_uploads' ? 0 : null);

        $ctrl = $this->makeControllerWithSettings($settings, [
            'forums'         => $forums,
            'messageService' => $msgService,
            'fileService'    => $fileService,
        ]);
        $response = $ctrl->post($this->makePostRequestWithFile(
            ['subject' => 'New Thread', 'body' => 'Hello world!'],
            ['forum_id' => '1'],
        ));

        $this->assertSame(302, $response->status);
    }

    public function testStoreUploadsProceedsWhenGloballyEnabled(): void
    {
        Auth::setUser($this->makeUser(1, admin: false));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $postedMsg         = $this->makeMessage(42, 1, 42);
        $postedMsg->status = 2;
        $msgService = $this->createMock(MessageService::class);
        $msgService->method('post')->willReturn($postedMsg);

        $fileService = $this->createMock(FileService::class);
        $fileService->method('getAttachments')->willReturn([]);
        $fileService->expects($this->once())->method('store');

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturnCallback(fn($k) => $k === 'file_uploads' ? 1 : null);

        $ctrl = $this->makeControllerWithSettings($settings, [
            'forums'         => $forums,
            'messageService' => $msgService,
            'fileService'    => $fileService,
        ]);
        $response = $ctrl->post($this->makePostRequestWithFile(
            ['subject' => 'New Thread', 'body' => 'Hello world!'],
            ['forum_id' => '1'],
        ));

        $this->assertSame(302, $response->status);
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

    public function testPostPreviewShowsRenderedBodyWithoutPersisting(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $msgService = $this->createMock(MessageService::class);
        $msgService->expects($this->never())->method('post');

        $twig = $this->makeTwig();
        $twig->expects($this->once())->method('render')->with(
            'message/post.html.twig',
            $this->callback(function (array $ctx): bool {
                return $ctx['show_preview'] === true
                    && $ctx['body'] === 'Hello world!'
                    && $ctx['preview_msg'] instanceof Message
                    && $ctx['preview_msg']->subject === 'New Thread'
                    && $ctx['preview_msg']->body === 'Hello world!'
                    && !empty($ctx['preview_users_map']);
            })
        );

        $ctrl     = $this->makeController([
            'forums'        => $forums,
            'messageService'=> $msgService,
            'twig'          => $twig,
        ]);
        $response = $ctrl->post($this->makePostRequest(
            post:   ['action' => 'preview', 'subject' => 'New Thread', 'body' => 'Hello world!'],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testPostPreviewStillValidatesRequiredFields(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $msgService = $this->createMock(MessageService::class);
        $msgService->expects($this->never())->method('post');

        $twig = $this->makeTwig();
        $twig->expects($this->once())->method('render')->with(
            'message/post.html.twig',
            $this->callback(function (array $ctx): bool {
                return $ctx['show_preview'] === false
                    && in_array('Message body is required.', $ctx['errors'], true);
            })
        );

        $ctrl     = $this->makeController([
            'forums'        => $forums,
            'messageService'=> $msgService,
            'twig'          => $twig,
        ]);
        $response = $ctrl->post($this->makePostRequest(
            post:   ['action' => 'preview', 'subject' => 'New Thread', 'body' => ''],
            tokens: ['forum_id' => '1'],
        ));
        $this->assertSame(200, $response->status);
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

    public function testEditMessageReturns403WhenThreadClosed(): void
    {
        $user = $this->makeUser(1);
        Auth::setUser($user);

        $msg          = $this->makeMessage(1, 1, 1);
        $msg->user_id = 1;
        $msg->closed  = 1;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->editMessage(new Request(tokens: ['message_id' => '1']));
        $this->assertSame(403, $response->status);
    }

    public function testEditMessageModeratorCanEditClosedThread(): void
    {
        $user = $this->makeUser(1);
        Auth::setUser($user);

        $msg          = $this->makeMessage(1, 1, 1);
        $msg->user_id = 99; // not the owner
        $msg->closed  = 1;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $fileService = $this->createMock(FileService::class);
        $fileService->method('getAttachments')->willReturn([]);

        $ctrl     = $this->makeController([
            'messages'    => $messages,
            'forums'      => $forums,
            'fileService' => $fileService,
            'canModerate' => true,
        ]);
        $response = $ctrl->editMessage($this->makeGetRequest(tokens: ['message_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testEditMessageReturns403WhenEditBitNotGranted(): void
    {
        $user = $this->makeUser(1);
        Auth::setUser($user);

        $msg          = $this->makeMessage(1, 1, 1);
        $msg->user_id = 1;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums, 'canEdit' => false]);
        $response = $ctrl->editMessage(new Request(tokens: ['message_id' => '1']));
        $this->assertSame(403, $response->status);
    }

    public function testEditMessageReturns403WhenPastTimeLimit(): void
    {
        $user = $this->makeUser(1);
        Auth::setUser($user);

        $msg              = $this->makeMessage(1, 1, 1);
        $msg->user_id     = 1;
        $msg->datestamp   = time() - 3600; // posted an hour ago

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController([
            'messages'        => $messages,
            'forums'          => $forums,
            'edit_time_limit' => 30, // 30-minute limit
        ]);
        $response = $ctrl->editMessage(new Request(tokens: ['message_id' => '1']));
        $this->assertSame(403, $response->status);
    }

    public function testEditMessageReturns200WithinTimeLimit(): void
    {
        $user = $this->makeUser(1);
        Auth::setUser($user);

        $msg            = $this->makeMessage(1, 1, 1);
        $msg->user_id   = 1;
        $msg->datestamp = time() - 60; // posted a minute ago

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $fileService = $this->createMock(FileService::class);
        $fileService->method('getAttachments')->willReturn([]);

        $ctrl     = $this->makeController([
            'messages'        => $messages,
            'forums'          => $forums,
            'fileService'     => $fileService,
            'edit_time_limit' => 30,
        ]);
        $response = $ctrl->editMessage($this->makeGetRequest(tokens: ['message_id' => '1']));
        $this->assertSame(200, $response->status);
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

    public function testEditMessagePostSuccessRedirectsToResolvedPageWhenForumIsPaginatedFlat(): void
    {
        $user = $this->makeUser(1);
        Auth::setUser($user);

        $msg           = $this->makeMessage(7, 1, 3);
        $msg->user_id  = 1;
        $msg->status   = MessageMapper::STATUS_APPROVED;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);
        $messages->method('findMessagePosition')->willReturn(31); // page 4 at 10/page

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1, ['read_length' => 10]));

        $updatedMsg = $this->makeMessage(7, 1, 3);
        $updatedMsg->status = MessageMapper::STATUS_APPROVED;

        $msgService = $this->createMock(MessageService::class);
        $msgService->method('edit')->willReturn($updatedMsg);

        $fileService = $this->createMock(FileService::class);
        $fileService->method('getAttachments')->willReturn([]);

        $ctrl     = $this->makeController([
            'messages'      => $messages,
            'forums'        => $forums,
            'messageService'=> $msgService,
            'fileService'   => $fileService,
        ]);
        $response = $ctrl->editMessage($this->makePostRequest(
            post:   ['subject' => 'Updated Subject', 'body' => 'Updated body.'],
            tokens: ['message_id' => '7'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('/forum/1/thread/3?page=4#msg-7', $response->headers['Location']);
    }

    public function testEditMessagePreviewShowsRenderedBodyWithoutPersisting(): void
    {
        $user = $this->makeUser(1);
        Auth::setUser($user);

        $msg          = $this->makeMessage(1, 1, 1);
        $msg->user_id = 1;
        $msg->status  = MessageMapper::STATUS_APPROVED;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $msgService = $this->createMock(MessageService::class);
        $msgService->expects($this->never())->method('edit');

        $fileService = $this->createMock(FileService::class);
        $fileService->method('getAttachments')->willReturn([]);

        $users = $this->createMock(UserMapper::class);
        $users->method('findByIds')->willReturn([1 => $user]);

        $twig = $this->makeTwig();
        $twig->expects($this->once())->method('render')->with(
            'message/edit.html.twig',
            $this->callback(function (array $ctx) use ($msg): bool {
                return $ctx['show_preview'] === true
                    && $ctx['body'] === 'Updated body.'
                    && $ctx['preview_msg'] instanceof Message
                    && $ctx['preview_msg']->subject === 'Updated Subject'
                    && $ctx['preview_msg']->body === 'Updated body.'
                    && $ctx['preview_msg']->meta === $msg->meta
                    && !empty($ctx['preview_users_map']);
            })
        );

        $ctrl     = $this->makeController([
            'messages'      => $messages,
            'forums'        => $forums,
            'messageService'=> $msgService,
            'fileService'   => $fileService,
            'users'         => $users,
            'twig'          => $twig,
        ]);
        $response = $ctrl->editMessage($this->makePostRequest(
            post:   ['action' => 'preview', 'subject' => 'Updated Subject', 'body' => 'Updated body.'],
            tokens: ['message_id' => '1'],
        ));
        $this->assertSame(200, $response->status);
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\ModerationController;
use Phorum\Http\Request;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mapper\ReportMapper;
use Phorum\Mapper\SearchMapper;
use Phorum\Model\Report;
use Phorum\Service\ModerationService;
use Phorum\Service\PermissionService;
use Phorum\Service\SubscriptionService;
use Phorum\Tests\Http\ControllerTestCase;

class ModerationControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): ModerationController
    {
        $perms = $deps['perms'] ?? $this->createMock(PermissionService::class);
        $perms->method('canModerate')->willReturn($deps['canModerate'] ?? true);

        return new ModerationController(
            config:            $this->makeConfig(),
            twig:              $this->makeTwig(),
            messages:          $deps['messages']          ?? $this->createMock(MessageMapper::class),
            forums:            $deps['forums']            ?? $this->createMock(ForumMapper::class),
            perms:             $perms,
            searchIndex:       $deps['searchIndex']       ?? $this->createMock(SearchMapper::class),
            subscriptions:     $deps['subscriptions']     ?? $this->createMock(SubscriptionService::class),
            moderationService: $deps['moderationService'] ?? $this->createMock(ModerationService::class),
            modLog:            $deps['modLog']            ?? $this->createMock(ModLogMapper::class),
            reports:           $deps['reports']           ?? $this->createMock(ReportMapper::class),
        );
    }

    private function makeReport(int $id = 1, array $override = []): Report
    {
        $report            = new Report();
        $report->report_id = $id;
        $report->forum_id  = 1;
        $report->message_id = 1;
        foreach ($override as $k => $v) {
            $report->$k = $v;
        }
        return $report;
    }

    // -------------------------------------------------------------------------
    // message action
    // -------------------------------------------------------------------------

    public function testMessageReturns404ForInvalidAction(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->message(new Request(tokens: ['message_id' => '1', 'action' => 'invalid']));
        $this->assertSame(404, $response->status);
    }

    public function testMessageReturns404ForUnknownMessage(): void
    {
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['messages' => $messages]);
        $response = $ctrl->message(new Request(tokens: ['message_id' => '99', 'action' => 'delete']));
        $this->assertSame(404, $response->status);
    }

    public function testMessageReturns404WhenForumNotFound(): void
    {
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($this->makeMessage(1, 5));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->message(new Request(tokens: ['message_id' => '1', 'action' => 'delete']));
        $this->assertSame(404, $response->status);
    }

    public function testMessageRedirectsAnonymousUser(): void
    {
        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($this->makeMessage());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->message(new Request(tokens: ['message_id' => '1', 'action' => 'delete']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
    }

    public function testMessageReturns403WhenCannotModerate(): void
    {
        Auth::setUser($this->makeUser());

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($this->makeMessage());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $perms = $this->createMock(PermissionService::class);
        $perms->method('canModerate')->willReturn(false);

        $ctrl     = $this->makeController([
            'messages'     => $messages,
            'forums'       => $forums,
            'perms'        => $perms,
            'canModerate'  => false,
        ]);
        $response = $ctrl->message(new Request(tokens: ['message_id' => '1', 'action' => 'delete']));
        $this->assertSame(403, $response->status);
    }

    public function testMessageReturnsConfirmFormOnGet(): void
    {
        Auth::setUser($this->makeUser());

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($this->makeMessage());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->message($this->makeGetRequest(tokens: ['message_id' => '1', 'action' => 'delete']));
        $this->assertSame(200, $response->status);
    }

    public function testMessageApproveOnPostCallsServiceAndRedirects(): void
    {
        Auth::setUser($this->makeUser());

        $msg = $this->makeMessage(10, 1, 10);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $modService = $this->createMock(ModerationService::class);
        $modService->expects($this->once())->method('approveMessage')->with(10);

        $search = $this->createMock(SearchMapper::class);
        $search->expects($this->once())->method('indexMessage');

        $subs = $this->createMock(SubscriptionService::class);
        $subs->expects($this->once())->method('notifySubscribers');

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with($this->anything(), 'approve', 'message', 10, 1, $msg->subject);

        $ctrl     = $this->makeController([
            'messages'          => $messages,
            'forums'            => $forums,
            'moderationService' => $modService,
            'searchIndex'       => $search,
            'subscriptions'     => $subs,
            'modLog'            => $modLog,
        ]);
        $response = $ctrl->message($this->makePostRequest(tokens: ['message_id' => '10', 'action' => 'approve']));
        $this->assertSame(302, $response->status);
    }

    public function testMessageDeleteRootPostRedirectsToForum(): void
    {
        Auth::setUser($this->makeUser());

        $msg           = $this->makeMessage(5, 2, 5);
        $msg->parent_id = 0; // root post

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($msg);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(2));

        $modService = $this->createMock(ModerationService::class);
        $modService->expects($this->once())->method('deleteMessage')->with(5);

        $search = $this->createMock(SearchMapper::class);
        $search->expects($this->once())->method('removeMessage')->with(5);

        $ctrl     = $this->makeController([
            'messages'          => $messages,
            'forums'            => $forums,
            'moderationService' => $modService,
            'searchIndex'       => $search,
        ]);
        $response = $ctrl->message($this->makePostRequest(tokens: ['message_id' => '5', 'action' => 'delete']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/2', $response->headers['Location']);
    }

    public function testMessagePostReturns403WithBadCsrf(): void
    {
        Auth::setUser($this->makeUser());

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($this->makeMessage());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->message(new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
            tokens: ['message_id' => '1', 'action' => 'delete'],
        ));
        $this->assertSame(403, $response->status);
    }

    // -------------------------------------------------------------------------
    // thread action
    // -------------------------------------------------------------------------

    public function testThreadCloseLogsAction(): void
    {
        Auth::setUser($this->makeUser());

        $root = $this->makeMessage(20, 3, 20);
        $root->parent_id = 0;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($root);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(3));

        $modService = $this->createMock(ModerationService::class);
        $modService->expects($this->once())->method('closeThread')->with(20);

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with($this->anything(), 'close', 'thread', 20, 3, $root->subject);

        $ctrl     = $this->makeController([
            'messages'          => $messages,
            'forums'            => $forums,
            'moderationService' => $modService,
            'modLog'            => $modLog,
        ]);
        $response = $ctrl->thread($this->makePostRequest(tokens: ['thread_id' => '20', 'action' => 'close']));
        $this->assertSame(302, $response->status);
    }

    public function testThreadMoveLogsDestinationForum(): void
    {
        Auth::setUser($this->makeUser());

        $root = $this->makeMessage(21, 3, 21);
        $root->parent_id = 0;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($root);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(3));

        $modService = $this->createMock(ModerationService::class);
        $modService->expects($this->once())->method('moveThread')->with(21, 9);

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with($this->anything(), 'move', 'thread', 21, 3, $this->stringContains('forum #9'));

        $ctrl     = $this->makeController([
            'messages'          => $messages,
            'forums'            => $forums,
            'moderationService' => $modService,
            'modLog'            => $modLog,
        ]);
        $response = $ctrl->thread($this->makePostRequest(
            post:   ['to_forum_id' => '9'],
            tokens: ['thread_id' => '21', 'action' => 'move'],
        ));
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // thread merge action
    // -------------------------------------------------------------------------

    public function testThreadMergeGetReturnsForm(): void
    {
        Auth::setUser($this->makeUser());

        $root = $this->makeMessage(30, 4, 30);
        $root->parent_id = 0;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($root);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(4));

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->thread($this->makeGetRequest(tokens: ['thread_id' => '30', 'action' => 'merge']));
        $this->assertSame(200, $response->status);
    }

    public function testThreadMergeSuccessRedirectsToTargetThread(): void
    {
        Auth::setUser($this->makeUser());

        $root       = $this->makeMessage(30, 4, 30);
        $root->parent_id = 0;
        $targetRoot = $this->makeMessage(31, 4, 31);
        $targetRoot->parent_id = 0;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturnCallback(fn($id) => match ((int) $id) {
            30 => $root,
            31 => $targetRoot,
            default => null,
        });

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(4));

        $modService = $this->createMock(ModerationService::class);
        $modService->expects($this->once())->method('mergeThread')->with(30, 31)->willReturn(true);

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with($this->anything(), 'merge', 'thread', 30, 4, $this->stringContains('thread #31'));

        $search = $this->createMock(SearchMapper::class);
        $search->expects($this->never())->method('updateForum'); // same forum — no search update needed

        $ctrl     = $this->makeController([
            'messages'          => $messages,
            'forums'            => $forums,
            'moderationService' => $modService,
            'modLog'            => $modLog,
            'searchIndex'       => $search,
        ]);
        $response = $ctrl->thread($this->makePostRequest(
            post:   ['target_thread_id' => '31'],
            tokens: ['thread_id' => '30', 'action' => 'merge'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/4/thread/31', $response->headers['Location']);
    }

    public function testThreadMergeUpdatesSearchWhenForumDiffers(): void
    {
        Auth::setUser($this->makeUser());

        $root       = $this->makeMessage(30, 4, 30);
        $root->parent_id = 0;
        $root->forum_id  = 4;
        $targetRoot = $this->makeMessage(31, 4, 31);
        $targetRoot->parent_id = 0;
        $targetRoot->forum_id  = 9;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturnCallback(fn($id) => match ((int) $id) {
            30 => $root,
            31 => $targetRoot,
            default => null,
        });

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturnCallback(fn($id) => match ((int) $id) {
            4 => $this->makeForum(4),
            9 => $this->makeForum(9),
            default => null,
        });

        $modService = $this->createMock(ModerationService::class);
        $modService->method('mergeThread')->willReturn(true);

        $search = $this->createMock(SearchMapper::class);
        $search->expects($this->once())->method('updateForum')->with(31, 9);

        $ctrl     = $this->makeController([
            'messages'          => $messages,
            'forums'            => $forums,
            'moderationService' => $modService,
            'searchIndex'       => $search,
        ]);
        $response = $ctrl->thread($this->makePostRequest(
            post:   ['target_thread_id' => '31'],
            tokens: ['thread_id' => '30', 'action' => 'merge'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/9/thread/31', $response->headers['Location']);
    }

    public function testThreadMergeReturns200WithErrorWhenTargetThreadNotFound(): void
    {
        Auth::setUser($this->makeUser());

        $root = $this->makeMessage(30, 4, 30);
        $root->parent_id = 0;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturnCallback(fn($id) => (int) $id === 30 ? $root : null);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(4));

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->thread($this->makePostRequest(
            post:   ['target_thread_id' => '999'],
            tokens: ['thread_id' => '30', 'action' => 'merge'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testThreadMergeReturns403WhenCannotModerateTargetForum(): void
    {
        Auth::setUser($this->makeUser());

        $root       = $this->makeMessage(30, 4, 30);
        $root->parent_id = 0;
        $root->forum_id  = 4;
        $targetRoot = $this->makeMessage(31, 4, 31);
        $targetRoot->parent_id = 0;
        $targetRoot->forum_id  = 9;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturnCallback(fn($id) => match ((int) $id) {
            30 => $root,
            31 => $targetRoot,
            default => null,
        });

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturnCallback(fn($id) => match ((int) $id) {
            4 => $this->makeForum(4),
            9 => $this->makeForum(9),
            default => null,
        });

        $perms = $this->createMock(PermissionService::class);
        $perms->method('canModerate')->willReturnCallback(fn($f) => $f->forum_id === 4);

        $ctrl     = $this->makeController([
            'messages' => $messages,
            'forums'   => $forums,
            'perms'    => $perms,
        ]);
        $response = $ctrl->thread($this->makePostRequest(
            post:   ['target_thread_id' => '31'],
            tokens: ['thread_id' => '30', 'action' => 'merge'],
        ));
        $this->assertSame(403, $response->status);
    }

    public function testThreadMergeReturns200WithErrorWhenServiceRejects(): void
    {
        Auth::setUser($this->makeUser());

        $root       = $this->makeMessage(30, 4, 30);
        $root->parent_id = 0;
        $targetRoot = $this->makeMessage(31, 4, 31);
        $targetRoot->parent_id = 0;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturnCallback(fn($id) => match ((int) $id) {
            30 => $root,
            31 => $targetRoot,
            default => null,
        });

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(4));

        $modService = $this->createMock(ModerationService::class);
        $modService->method('mergeThread')->willReturn(false);

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->never())->method('record');

        $ctrl     = $this->makeController([
            'messages'          => $messages,
            'forums'            => $forums,
            'moderationService' => $modService,
            'modLog'            => $modLog,
        ]);
        $response = $ctrl->thread($this->makePostRequest(
            post:   ['target_thread_id' => '31'],
            tokens: ['thread_id' => '30', 'action' => 'merge'],
        ));
        $this->assertSame(200, $response->status);
    }

    public function testThreadMergePostReturns403WithBadCsrf(): void
    {
        Auth::setUser($this->makeUser());

        $root = $this->makeMessage(30, 4, 30);
        $root->parent_id = 0;

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('load')->willReturn($root);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(4));

        $ctrl     = $this->makeController(['messages' => $messages, 'forums' => $forums]);
        $response = $ctrl->thread(new Request(
            post:   ['csrf_token' => 'bad', 'target_thread_id' => '31'],
            server: ['REQUEST_METHOD' => 'POST'],
            tokens: ['thread_id' => '30', 'action' => 'merge'],
        ));
        $this->assertSame(403, $response->status);
    }

    // -------------------------------------------------------------------------
    // queue
    // -------------------------------------------------------------------------

    public function testQueueRedirectsAnonymousUser(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->queue(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
    }

    public function testQueueReturns403WhenNoModeratableForums(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([$this->makeForum(1)]);

        $ctrl     = $this->makeController(['forums' => $forums, 'canModerate' => false]);
        $response = $ctrl->queue(new Request());
        $this->assertSame(403, $response->status);
    }

    public function testQueueReturns200WithPendingMessages(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([$this->makeForum(1)]);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findUnapprovedInForums')->with([1])->willReturn([$this->makeMessage()]);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->queue(new Request());
        $this->assertSame(200, $response->status);
    }

    public function testQueueReturns200WithEmptyQueue(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([$this->makeForum(1)]);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('findUnapprovedInForums')->willReturn(null);

        $ctrl     = $this->makeController(['forums' => $forums, 'messages' => $messages]);
        $response = $ctrl->queue(new Request());
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // reports (listing)
    // -------------------------------------------------------------------------

    public function testReportsRedirectsAnonymousUser(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->reports(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
    }

    public function testReportsReturns403WhenNoModeratableForums(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([$this->makeForum(1)]);

        $ctrl     = $this->makeController(['forums' => $forums, 'canModerate' => false]);
        $response = $ctrl->reports(new Request());
        $this->assertSame(403, $response->status);
    }

    public function testReportsReturns200WithOpenReports(): void
    {
        Auth::setUser($this->makeUser());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([$this->makeForum(1)]);

        $reports = $this->createMock(ReportMapper::class);
        $reports->method('findOpenInForums')->with([1])->willReturn([$this->makeReport(1)]);

        $messages = $this->createMock(MessageMapper::class);
        $messages->method('loadMulti')->willReturn([$this->makeMessage(1, 1, 1)]);

        $ctrl     = $this->makeController(['forums' => $forums, 'reports' => $reports, 'messages' => $messages]);
        $response = $ctrl->reports(new Request());
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // report (resolve/dismiss)
    // -------------------------------------------------------------------------

    public function testReportActionReturns404ForInvalidAction(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->report(new Request(tokens: ['report_id' => '1', 'action' => 'bogus']));
        $this->assertSame(404, $response->status);
    }

    public function testReportActionReturns404WhenReportNotFound(): void
    {
        $reports = $this->createMock(ReportMapper::class);
        $reports->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['reports' => $reports]);
        $response = $ctrl->report(new Request(tokens: ['report_id' => '99', 'action' => 'resolve']));
        $this->assertSame(404, $response->status);
    }

    public function testReportActionRedirectsAnonymousUser(): void
    {
        $reports = $this->createMock(ReportMapper::class);
        $reports->method('load')->willReturn($this->makeReport());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['reports' => $reports, 'forums' => $forums]);
        $response = $ctrl->report(new Request(tokens: ['report_id' => '1', 'action' => 'resolve']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
    }

    public function testReportActionReturns403WhenCannotModerate(): void
    {
        Auth::setUser($this->makeUser());

        $reports = $this->createMock(ReportMapper::class);
        $reports->method('load')->willReturn($this->makeReport());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['reports' => $reports, 'forums' => $forums, 'canModerate' => false]);
        $response = $ctrl->report(new Request(tokens: ['report_id' => '1', 'action' => 'resolve']));
        $this->assertSame(403, $response->status);
    }

    public function testReportActionResolvesAndLogsThenRedirects(): void
    {
        Auth::setUser($this->makeUser(7));

        $reports = $this->createMock(ReportMapper::class);
        $reports->method('load')->willReturn($this->makeReport(3));
        $reports->expects($this->once())->method('resolve')->with(3, 7, ReportMapper::STATUS_RESOLVED);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(7, 'resolve', 'report', 3, 1, '');

        $ctrl     = $this->makeController(['reports' => $reports, 'forums' => $forums, 'modLog' => $modLog]);
        $response = $ctrl->report($this->makePostRequest(tokens: ['report_id' => '3', 'action' => 'resolve']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/moderate/reports', $response->headers['Location']);
    }

    public function testReportActionDismissesReport(): void
    {
        Auth::setUser($this->makeUser(7));

        $reports = $this->createMock(ReportMapper::class);
        $reports->method('load')->willReturn($this->makeReport(4));
        $reports->expects($this->once())->method('resolve')->with(4, 7, ReportMapper::STATUS_DISMISSED);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum(1));

        $ctrl     = $this->makeController(['reports' => $reports, 'forums' => $forums]);
        $response = $ctrl->report($this->makePostRequest(tokens: ['report_id' => '4', 'action' => 'dismiss']));
        $this->assertSame(302, $response->status);
    }

    public function testReportActionGetReturns404(): void
    {
        Auth::setUser($this->makeUser());

        $reports = $this->createMock(ReportMapper::class);
        $reports->method('load')->willReturn($this->makeReport());

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('load')->willReturn($this->makeForum());

        $ctrl     = $this->makeController(['reports' => $reports, 'forums' => $forums]);
        $response = $ctrl->report($this->makeGetRequest(tokens: ['report_id' => '1', 'action' => 'resolve']));
        $this->assertSame(404, $response->status);
    }
}

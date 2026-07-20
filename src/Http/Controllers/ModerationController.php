<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\Lang;
use Phorum\Core\Url;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mapper\NewflagMapper;
use Phorum\Mapper\ReportMapper;
use Phorum\Mapper\SearchMapper;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Mapper\UserPermissionMapper;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Model\User;
use Phorum\Service\MailService;
use Phorum\Service\ModerationService;
use Phorum\Service\PermissionService;
use Phorum\Service\SubscriptionService;
use Twig\Environment;

class ModerationController extends Controller
{
    private readonly MessageMapper      $messages;
    private readonly ForumMapper        $forums;
    private readonly PermissionService  $perms;
    private readonly SearchMapper       $searchIndex;
    private readonly SubscriptionService $subscriptions;
    private readonly ModerationService  $moderationService;
    private readonly ModLogMapper       $modLog;
    private readonly ReportMapper       $reports;

    public function __construct(
        Config                $config,
        Environment           $twig,
        ?MessageMapper        $messages          = null,
        ?ForumMapper          $forums            = null,
        ?PermissionService    $perms             = null,
        ?SearchMapper         $searchIndex       = null,
        ?SubscriptionService  $subscriptions     = null,
        ?ModerationService    $moderationService = null,
        ?ModLogMapper         $modLog            = null,
        ?ReportMapper         $reports           = null,
    ) {
        parent::__construct($config, $twig);
        $this->messages          = $messages          ?? new MessageMapper();
        $this->forums            = $forums            ?? new ForumMapper();
        $this->perms             = $perms             ?? new PermissionService(new UserPermissionMapper());
        $this->searchIndex       = $searchIndex       ?? new SearchMapper();
        $this->subscriptions     = $subscriptions     ?? new SubscriptionService(new SubscriberMapper(), new UserMapper(), new MailService($config), $config);
        $this->moderationService = $moderationService ?? new ModerationService($this->messages, $this->forums, new UserMapper(), new SubscriberMapper(), new NewflagMapper());
        $this->modLog            = $modLog            ?? new ModLogMapper();
        $this->reports           = $reports           ?? new ReportMapper();
    }

    /** Forums the given user has moderate rights on. */
    private function moderatableForums(User $user): array
    {
        $forums = $this->forums->find(filter: ['active' => 1], order: 'name ASC') ?? [];
        return array_values(array_filter($forums, fn($f) => $this->perms->canModerate($f, $user)));
    }

    // -------------------------------------------------------------------------
    // Pending-message review queue
    // -------------------------------------------------------------------------

    public function queue(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        $moderatable = $this->moderatableForums($user);
        if (empty($moderatable)) {
            return $this->forbidden();
        }

        $forumIds   = array_map(fn($f) => $f->forum_id, $moderatable);
        $forumNames = [];
        foreach ($moderatable as $f) {
            $forumNames[$f->forum_id] = $f->name;
        }

        $pending = $this->messages->findUnapprovedInForums($forumIds) ?? [];

        return $this->respond($this->render('moderation/queue.html.twig', [
            'pending'     => $pending,
            'forum_names' => $forumNames,
        ]));
    }

    // -------------------------------------------------------------------------
    // Reported-content review queue
    // -------------------------------------------------------------------------

    public function reports(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        $moderatable = $this->moderatableForums($user);
        if (empty($moderatable)) {
            return $this->forbidden();
        }

        $forumIds   = array_map(fn($f) => $f->forum_id, $moderatable);
        $forumNames = [];
        foreach ($moderatable as $f) {
            $forumNames[$f->forum_id] = $f->name;
        }

        $openReports = $this->reports->findOpenInForums($forumIds) ?? [];

        $messageIds = array_values(array_unique(array_map(fn($r) => $r->message_id, $openReports)));
        $messagesMap = [];
        foreach ($this->messages->loadMulti($messageIds) ?? [] as $msg) {
            $messagesMap[$msg->message_id] = $msg;
        }

        return $this->respond($this->render('moderation/reports.html.twig', [
            'reports'     => $openReports,
            'messages_map' => $messagesMap,
            'forum_names' => $forumNames,
        ]));
    }

    /** Resolve or dismiss a report. */
    public function report(Request $request): Response
    {
        $reportId = (int) ($request->tokens['report_id'] ?? 0);
        $action   = $request->tokens['action'] ?? '';

        if (!in_array($action, ['resolve', 'dismiss'], strict: true)) {
            return $this->notFound();
        }

        $report = $this->reports->load($reportId);
        if ($report === null) {
            return $this->notFound();
        }

        $forum = $this->forums->load($report->forum_id);
        if ($forum === null) {
            return $this->notFound();
        }

        $user = Auth::user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        if (!$this->perms->canModerate($forum, $user)) {
            return $this->forbidden();
        }

        if (!$request->isPost()) {
            return $this->notFound();
        }

        if ($r = $this->checkCsrf($request)) { return $r; }

        $status = $action === 'resolve' ? ReportMapper::STATUS_RESOLVED : ReportMapper::STATUS_DISMISSED;
        $this->reports->resolve($reportId, $user->user_id, $status);
        $this->modLog->record($user->user_id, $action, 'report', $reportId, $report->forum_id, '');

        return $this->redirect('/moderate/reports');
    }

    // -------------------------------------------------------------------------
    // Single-message actions: delete, approve
    // -------------------------------------------------------------------------

    public function message(Request $request): Response
    {
        $msgId  = (int) ($request->tokens['message_id'] ?? 0);
        $action = $request->tokens['action'] ?? '';

        if (!in_array($action, ['delete', 'approve'], strict: true)) {
            return $this->notFound();
        }

        $msg = $this->messages->load($msgId);
        if ($msg === null) {
            return $this->notFound();
        }

        $forum = $this->forums->load($msg->forum_id);
        if ($forum === null) {
            return $this->notFound();
        }

        $user = Auth::user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        if (!$this->perms->canModerate($forum, $user)) {
            return $this->forbidden();
        }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            match ($action) {
                'delete'  => $this->moderationService->deleteMessage($msgId),
                'approve' => $this->moderationService->approveMessage($msgId),
            };

            $this->modLog->record($user->user_id, $action, 'message', $msgId, $msg->forum_id, $msg->subject);

            if ($action === 'approve') {
                $this->searchIndex->indexMessage(
                    $msg->message_id, $msg->forum_id, $msg->author, $msg->subject, $msg->body
                );
            } elseif ($action === 'delete') {
                $this->searchIndex->removeMessage($msgId);
            }

            if ($action === 'approve') {
                $this->subscriptions->notifySubscribers($msg, $forum, excludeUserId: $msg->user_id);
            }

            // deleteMessage on a root post cascades to deleteThread → land on forum
            if ($action === 'delete' && $msg->parent_id === 0) {
                return $this->redirect(Url::forum($msg->forum_id));
            } else {
                return $this->redirect(Url::thread($msg->forum_id, $msg->thread));
            }
        }

        return $this->respond($this->render('moderation/confirm.html.twig', [
            'action' => $action,
            'msg'    => $msg,
            'forum'  => $forum,
        ]));
    }

    // -------------------------------------------------------------------------
    // Thread-level actions: delete, close, open, move
    // -------------------------------------------------------------------------

    public function thread(Request $request): Response
    {
        $threadId = (int) ($request->tokens['thread_id'] ?? 0);
        $action   = $request->tokens['action'] ?? '';

        if (!in_array($action, ['delete', 'close', 'open', 'move', 'merge', 'sticky', 'unsticky'], strict: true)) {
            return $this->notFound();
        }

        $root = $this->messages->load($threadId);
        if ($root === null || $root->parent_id !== 0) {
            return $this->notFound();
        }

        $forum = $this->forums->load($root->forum_id);
        if ($forum === null) {
            return $this->notFound();
        }

        $user = Auth::user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        if (!$this->perms->canModerate($forum, $user)) {
            return $this->forbidden();
        }

        if ($action === 'merge' && $request->isPost()) {
            return $this->mergeThread($request, $user, $root, $forum);
        }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $toForumId = (int) ($request->post['to_forum_id'] ?? 0);

            match ($action) {
                'delete'   => $this->moderationService->deleteThread($threadId),
                'close'    => $this->moderationService->closeThread($threadId),
                'open'     => $this->moderationService->openThread($threadId),
                'move'     => $this->moderationService->moveThread($threadId, $toForumId),
                'sticky'   => $this->moderationService->stickyThread($threadId, true),
                'unsticky' => $this->moderationService->stickyThread($threadId, false),
            };

            $details = $action === 'move' && $toForumId > 0
                ? "{$root->subject} \u{2192} forum #{$toForumId}"
                : $root->subject;
            $this->modLog->record($user->user_id, $action, 'thread', $threadId, $root->forum_id, $details);

            if ($action === 'delete') {
                $this->searchIndex->removeThread($threadId);
            } elseif ($action === 'move' && $toForumId > 0) {
                $this->searchIndex->updateForum($threadId, $toForumId);
            }

            if ($action === 'delete') {
                return $this->redirect(Url::forum($root->forum_id));
            } elseif ($action === 'move' && $toForumId > 0) {
                return $this->redirect(Url::thread($toForumId, $threadId));
            } else {
                return $this->redirect(Url::thread($root->forum_id, $threadId));
            }
        }

        $forumList = $action === 'move'
            ? ($this->forums->find(filter: ['active' => 1, 'folder_flag' => 0], order: 'name ASC') ?? [])
            : [];

        $template = match ($action) {
            'move'  => 'moderation/move.html.twig',
            'merge' => 'moderation/merge.html.twig',
            default => 'moderation/confirm.html.twig',
        };

        return $this->respond($this->render($template, [
            'action'     => $action,
            'root'       => $root,
            'forum'      => $forum,
            'forum_list' => $forumList,
            'errors'     => [],
        ]));
    }

    /**
     * Merge $root's thread into another thread the moderator specifies.
     * Kept separate from the delete/close/open/move match() above since it
     * needs to load and permission-check a second thread/forum, and can
     * fail validation in ways that redisplay the form with an error instead
     * of a flat redirect.
     */
    private function mergeThread(Request $request, User $user, Message $root, Forum $forum): Response
    {
        if ($r = $this->checkCsrf($request)) { return $r; }

        $targetThreadId = (int) ($request->post['target_thread_id'] ?? 0);
        $targetRoot     = $targetThreadId > 0 ? $this->messages->load($targetThreadId) : null;
        $targetForum    = $targetRoot !== null ? $this->forums->load($targetRoot->forum_id) : null;

        $formError = function (string $message) use ($root, $forum): Response {
            return $this->respond($this->render('moderation/merge.html.twig', [
                'action'     => 'merge',
                'root'       => $root,
                'forum'      => $forum,
                'forum_list' => [],
                'errors'     => [$message],
            ]));
        };

        if ($targetRoot === null || $targetRoot->parent_id !== 0) {
            return $formError(Lang::get('mod.merge_error_not_found'));
        }
        if ($targetThreadId === $root->message_id) {
            return $formError(Lang::get('mod.merge_error_same_thread'));
        }
        if ($targetForum === null || !$this->perms->canModerate($targetForum, $user)) {
            return $this->forbidden();
        }

        $merged = $this->moderationService->mergeThread($root->message_id, $targetThreadId);
        if (!$merged) {
            return $formError(Lang::get('mod.merge_error_failed'));
        }

        $this->modLog->record(
            $user->user_id,
            'merge',
            'thread',
            $root->message_id,
            $root->forum_id,
            "{$root->subject} \u{2192} thread #{$targetThreadId}"
        );

        if ($targetRoot->forum_id !== $root->forum_id) {
            $this->searchIndex->updateForum($targetThreadId, $targetRoot->forum_id);
        }

        return $this->redirect(Url::thread($targetRoot->forum_id, $targetThreadId));
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\Url;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\ReportMapper;
use Phorum\Mapper\UserPermissionMapper;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Model\User;
use Phorum\Service\PermissionService;
use Twig\Environment;

class ReportController extends Controller
{
    private readonly MessageMapper     $messages;
    private readonly ForumMapper       $forums;
    private readonly ReportMapper      $reports;
    private readonly PermissionService $perms;

    public function __construct(
        Config             $config,
        Environment        $twig,
        ?MessageMapper     $messages = null,
        ?ForumMapper       $forums   = null,
        ?ReportMapper      $reports  = null,
        ?PermissionService $perms    = null,
    ) {
        parent::__construct($config, $twig);
        $this->messages = $messages ?? new MessageMapper();
        $this->forums   = $forums   ?? new ForumMapper();
        $this->reports  = $reports  ?? new ReportMapper();
        $this->perms    = $perms    ?? new PermissionService(new UserPermissionMapper());
    }

    public function create(Request $request): Response
    {
        $msgId = (int) ($request->tokens['message_id'] ?? 0);
        $msg   = $this->messages->load($msgId);
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

        // This page renders the reported message's full body, so it needs the
        // same read permission the thread view does — without it, any logged-in
        // user could read any forum's posts by walking message ids.
        if (!$this->perms->canRead($forum, $user)) {
            return $this->forbidden();
        }

        if (!$this->isReportable($msg, $forum, $user)) {
            return $this->notFound();
        }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            $reason = mb_substr(trim($request->post['reason'] ?? ''), 0, 255);
            $this->reports->create($msg->message_id, $msg->forum_id, $user->user_id, $reason);

            return $this->redirect(Url::thread($msg->forum_id, $msg->thread, $msg->message_id));
        }

        return $this->respond($this->render('report/confirm.html.twig', [
            'msg'   => $msg,
            'forum' => $forum,
        ]));
    }

    /**
     * True if $user is allowed to see — and therefore report — $msg.
     *
     * Read permission on the forum isn't the whole story: a thread view only
     * ever shows approved posts (plus the viewer's own shadow-banned ones, and
     * whatever moderators are shown), so without this the report page would
     * hand back deleted, still-pending, and shadow-banned bodies to anyone who
     * guessed the message id.
     */
    private function isReportable(Message $msg, Forum $forum, User $user): bool
    {
        if ($msg->status === MessageMapper::STATUS_DELETED) {
            return false;
        }

        if ($msg->status === MessageMapper::STATUS_APPROVED) {
            return true;
        }

        // Pending or shadow-banned: visible only to its own author and to
        // moderators of the forum it's in.
        return $msg->user_id === $user->user_id || $this->perms->canModerate($forum, $user);
    }
}

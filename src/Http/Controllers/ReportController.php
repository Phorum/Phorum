<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\ReportMapper;
use Twig\Environment;

class ReportController extends Controller
{
    private readonly MessageMapper $messages;
    private readonly ForumMapper   $forums;
    private readonly ReportMapper  $reports;

    public function __construct(
        Config         $config,
        Environment    $twig,
        ?MessageMapper $messages = null,
        ?ForumMapper   $forums   = null,
        ?ReportMapper  $reports  = null,
    ) {
        parent::__construct($config, $twig);
        $this->messages = $messages ?? new MessageMapper();
        $this->forums   = $forums   ?? new ForumMapper();
        $this->reports  = $reports  ?? new ReportMapper();
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

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            $reason = mb_substr(trim($request->post['reason'] ?? ''), 0, 255);
            $this->reports->create($msg->message_id, $msg->forum_id, $user->user_id, $reason);

            return $this->redirect("/forum/{$msg->forum_id}/thread/{$msg->thread}#msg-{$msg->message_id}");
        }

        return $this->respond($this->render('report/confirm.html.twig', [
            'msg'   => $msg,
            'forum' => $forum,
        ]));
    }
}

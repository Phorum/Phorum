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
use Phorum\Mapper\SubscriberMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\MailService;
use Phorum\Service\SubscriptionService;
use Twig\Environment;

class SubscriptionController extends Controller
{
    private readonly SubscriptionService $subscriptionService;
    private readonly MessageMapper       $messages;
    private readonly ForumMapper         $forums;

    public function __construct(
        Config                $config,
        Environment           $twig,
        ?SubscriptionService  $subscriptionService = null,
        ?MessageMapper        $messages            = null,
        ?ForumMapper          $forums              = null,
    ) {
        parent::__construct($config, $twig);
        $this->messages            = $messages            ?? new MessageMapper();
        $this->forums              = $forums              ?? new ForumMapper();
        $this->subscriptionService = $subscriptionService ?? new SubscriptionService(new SubscriberMapper(), new UserMapper(), new MailService($config), $config);
    }

    /**
     * GET  /follow/{thread_id}        — show subscribe options
     * POST /follow/{thread_id}        — handle subscribe/unsubscribe
     * GET  /follow/{thread_id}?action=remove   — quick unsubscribe via email link
     * GET  /follow/{thread_id}?action=bookmark — downgrade to bookmark via email link
     */
    public function follow(Request $request): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $this->redirect('/login?redirect=' . urlencode($request->server['REQUEST_URI'] ?? '/'));
        }

        $threadId = (int) ($request->tokens['thread_id'] ?? 0);

        $root     = $this->messages->load($threadId);
        if ($root === null || $root->parent_id !== 0) {
            return $this->notFound();
        }

        $forum  = $this->forums->load($root->forum_id);
        if ($forum === null) {
            return $this->notFound();
        }

        $service = $this->subscriptionService;
        $current = $service->getSubscription($user->user_id, $root->forum_id, $threadId);

        // Quick-action links from notification emails arrive as GET requests.
        // Show a confirmation form so the action only fires on a CSRF-protected POST.
        $quickAction = $request->query['action'] ?? '';
        if (in_array($quickAction, ['remove', 'bookmark'], true)) {
            if ($request->isPost()) {
                if ($r = $this->checkCsrf($request)) { return $r; }
                if ($quickAction === 'remove') {
                    $service->unsubscribe($user->user_id, $root->forum_id, $threadId);
                } else {
                    $service->subscribe($user->user_id, $root->forum_id, $threadId, SubscriberMapper::SUB_BOOKMARK);
                }
                return $this->redirect("/forum/{$root->forum_id}/thread/{$threadId}");
            }
            return $this->respond($this->render('subscription/quick_confirm.html.twig', [
                'root'        => $root,
                'forum'       => $forum,
                'quick_action'=> $quickAction,
                'theme'       => $this->resolveTheme($forum),
            ]));
        }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $action = $request->post['action'] ?? '';

            match ($action) {
                'subscribe_email' => $service->subscribe(
                    $user->user_id, $root->forum_id, $threadId, SubscriberMapper::SUB_MESSAGE
                ),
                'subscribe_bookmark' => $service->subscribe(
                    $user->user_id, $root->forum_id, $threadId, SubscriberMapper::SUB_BOOKMARK
                ),
                'unsubscribe' => $service->unsubscribe($user->user_id, $root->forum_id, $threadId),
                default       => null,
            };

            return $this->redirect("/forum/{$root->forum_id}/thread/{$threadId}");
        }

        return $this->respond($this->render('subscription/follow.html.twig', [
            'root'        => $root,
            'forum'       => $forum,
            'current_sub' => $current,
            'SUB_NONE'    => SubscriberMapper::SUB_NONE,
            'SUB_MESSAGE' => SubscriberMapper::SUB_MESSAGE,
            'SUB_BOOKMARK'=> SubscriberMapper::SUB_BOOKMARK,
            'theme'       => $this->resolveTheme($forum),
        ]));
    }
}

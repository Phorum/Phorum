<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\FeedStatus;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\UserPermissionMapper;
use Phorum\Service\FeedService;
use Phorum\Service\ForumService;
use Phorum\Service\PermissionService;
use Twig\Environment;

/**
 * RSS 2.0 / Atom 1.0 / JSON Feed 1.1 feeds: site-wide recent posts, one
 * forum's recent threads, and one thread's replies. Gated by
 * FeedStatus::enabled() (the enable_rss site setting, resolved once per
 * request — see App::run()) and the same PermissionService::canRead()
 * check every other read-only route uses — the site-wide feed additionally
 * scopes its query to only the forums the current viewer can read (see
 * ForumService::getReadableForumIds()), since MessageMapper::findRecent()
 * itself does no permission filtering.
 */
class FeedController extends Controller
{
    private const ITEM_LIMIT = 30; // matches Phorum 6's fixed feed length

    private readonly ForumMapper       $forums;
    private readonly MessageMapper     $messages;
    private readonly PermissionService $perms;
    private readonly FeedService       $feed;

    public function __construct(
        Config             $config,
        Environment        $twig,
        ?ForumMapper       $forums   = null,
        ?MessageMapper     $messages = null,
        ?PermissionService $perms    = null,
        ?FeedService       $feed     = null,
    ) {
        parent::__construct($config, $twig);
        $this->forums   = $forums   ?? new ForumMapper();
        $this->messages = $messages ?? new MessageMapper();
        $this->perms    = $perms    ?? new PermissionService(new UserPermissionMapper());
        $this->feed     = $feed     ?? new FeedService($config);
    }

    /** GET /feed.{rss|atom|json} — recent approved posts across all forums the viewer can read. */
    public function site(Request $request): Response
    {
        if ($r = $this->requireEnabled()) { return $r; }

        $format = (string) ($request->tokens['format'] ?? 'rss');

        $readableForumIds = (new ForumService($this->forums))
            ->getReadableForumIds(Auth::user(), $this->perms);

        $messages = $this->messages->findRecentInForums($readableForumIds, self::ITEM_LIMIT) ?? [];

        $body = $this->feed->siteWide($messages, $this->forums, $format, $this->siteName());

        return $this->feedResponse($body, $format);
    }

    /** GET /forum/{forum_id}/feed.{rss|atom|json} — recent threads in one forum. */
    public function forum(Request $request): Response
    {
        if ($r = $this->requireEnabled()) { return $r; }

        $forumId = (int) ($request->tokens['forum_id'] ?? 0);
        $format  = (string) ($request->tokens['format'] ?? 'rss');

        $forum = $this->forums->load($forumId);
        if ($forum === null || $forum->folder_flag) {
            return $this->notFound();
        }
        if (!$this->perms->canRead($forum, Auth::user())) {
            return $this->forbidden();
        }

        $threads = $this->messages->findThreadsInForum($forumId, self::ITEM_LIMIT) ?? [];

        $body = $this->feed->forumThreads($forum, $threads, $format, $this->siteName());

        return $this->feedResponse($body, $format);
    }

    /** GET /forum/{forum_id}/thread/{thread_id}/feed.{rss|atom|json} — replies in one thread. */
    public function thread(Request $request): Response
    {
        if ($r = $this->requireEnabled()) { return $r; }

        $forumId  = (int) ($request->tokens['forum_id']  ?? 0);
        $threadId = (int) ($request->tokens['thread_id'] ?? 0);
        $format   = (string) ($request->tokens['format']  ?? 'rss');

        $forum = $this->forums->load($forumId);
        if ($forum === null) {
            return $this->notFound();
        }
        if (!$this->perms->canRead($forum, Auth::user())) {
            return $this->forbidden();
        }

        $threadMessages = $this->messages->findByThread($threadId);
        if ($threadMessages === null) {
            return $this->notFound();
        }

        $root = null;
        foreach ($threadMessages as $msg) {
            if ($msg->message_id === $threadId) {
                $root = $msg;
                break;
            }
        }
        if ($root === null) {
            return $this->notFound();
        }

        $body = $this->feed->threadReplies($forum, $root, $threadMessages, $format, $this->siteName());

        return $this->feedResponse($body, $format);
    }

    // -------------------------------------------------------------------------

    /** Feeds are a site-wide feature flag, not an access-control decision — off means "doesn't exist." */
    private function requireEnabled(): ?Response
    {
        return FeedStatus::enabled() ? null : $this->notFound();
    }

    private function siteName(): string
    {
        return (string) $this->config->get('site_name', 'Phorum');
    }

    private function feedResponse(string $body, string $format): Response
    {
        $contentType = match ($format) {
            'atom'  => 'application/atom+xml; charset=UTF-8',
            'json'  => 'application/feed+json; charset=UTF-8',
            default => 'application/rss+xml; charset=UTF-8',
        };

        return new Response($body, 200, ['Content-Type' => $contentType]);
    }
}

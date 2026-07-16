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
use Phorum\Mapper\NewflagMapper;
use Phorum\Mapper\SubscriberMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Mapper\UserPermissionMapper;
use Phorum\Model\Forum;
use Phorum\Service\AnnouncementService;
use Phorum\Service\ForumService;
use Phorum\Service\MailService;
use Phorum\Service\NewflagService;
use Phorum\Service\PermissionService;
use Phorum\Service\SchemaOrgService;
use Phorum\Service\SubscriptionService;
use Twig\Environment;

class ForumController extends Controller
{
    private readonly ForumMapper          $forums;
    private readonly MessageMapper        $messages;
    private readonly PermissionService    $perms;
    private readonly NewflagService       $newflags;
    private readonly SubscriptionService  $subscriptions;
    private readonly AnnouncementService  $announcements;
    private readonly SchemaOrgService     $schemaOrg;

    public function __construct(
        Config                $config,
        Environment           $twig,
        ?ForumMapper          $forums        = null,
        ?MessageMapper        $messages      = null,
        ?PermissionService    $perms         = null,
        ?NewflagService       $newflags      = null,
        ?SubscriptionService  $subscriptions = null,
        ?AnnouncementService  $announcements = null,
        ?SchemaOrgService     $schemaOrg     = null,
    ) {
        parent::__construct($config, $twig);
        $this->forums        = $forums        ?? new ForumMapper();
        $this->messages      = $messages      ?? new MessageMapper();
        $this->perms         = $perms         ?? new PermissionService(new UserPermissionMapper());
        $this->newflags      = $newflags      ?? new NewflagService(new NewflagMapper());
        $this->subscriptions = $subscriptions ?? new SubscriptionService(new SubscriberMapper(), new UserMapper(), new MailService($config), $config);
        $this->announcements = $announcements ?? new AnnouncementService();
        $this->schemaOrg     = $schemaOrg     ?? new SchemaOrgService($config);
    }

    public function index(Request $request): Response
    {
        $service    = new ForumService($this->forums);
        $tree       = $service->getTree();
        $flatForums = $this->flattenTree($tree);

        $hookResult = phorum_api_hook('index', $tree);
        if (is_array($hookResult)) {
            $tree = $hookResult;
        }

        return $this->respond($this->render('forum/index.html.twig', [
            'tree'          => $tree,
            'announcements' => $this->announcements->getAnnouncementsFor('index', Auth::user()?->user_id ?? 0),
            'json_ld'       => $this->schemaOrg->forumIndex($flatForums, (string) $this->config->get('site_name', 'Phorum')),
        ]));
    }

    public function show(Request $request): Response
    {
        $forumId = (int) ($request->tokens['forum_id'] ?? 0);
        $forum   = $this->forums->load($forumId);

        if ($forum === null) {
            return $this->notFound();
        }

        if ($forum->folder_flag) {
            return $this->showFolder($forum);
        }

        if (!$this->perms->canRead($forum, Auth::user())) {
            return $this->forbidden();
        }

        $perPage = $forum->list_length_flat ?: 25;
        $page    = max(1, (int) ($request->query['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $threads = $this->messages->findThreadsInForum($forumId, $perPage, $offset);
        $total   = $forum->thread_count;
        $pages   = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $currentUser     = Auth::user();
        $threadNewCounts = [];
        if ($currentUser !== null) {
            $threadNewCounts = $this->newflags->getNewCountsForThreads($currentUser->user_id, $forumId);
        }

        $hookResult = phorum_api_hook('list', $threads ?? []);
        if (is_array($hookResult)) {
            $threads = $hookResult;
        }

        return $this->respond($this->render('forum/show.html.twig', [
            'forum'             => $forum,
            'threads'           => $threads ?? [],
            'thread_new_counts' => $threadNewCounts,
            'page'              => $page,
            'pages'             => $pages,
            'base_url'          => Url::forum($forumId),
            'can_post'          => $this->perms->canPost($forum, $currentUser),
            'can_moderate'      => $this->perms->canModerate($forum, $currentUser),
            'theme'             => $this->resolveTheme($forum),
            'announcements'     => $this->announcements->getAnnouncementsFor('list', $currentUser?->user_id ?? 0),
            'json_ld'           => $this->schemaOrg->forumShow($forum, $threads ?? [], (string) $this->config->get('site_name', 'Phorum')),
        ]));
    }

    /** Render a folder scoped to just its own forums/sub-folders. */
    private function showFolder(Forum $folder): Response
    {
        $service    = new ForumService($this->forums);
        $tree       = $service->getTree($folder->forum_id);
        $flatForums = $this->flattenTree($tree);

        $hookResult = phorum_api_hook('index', $tree);
        if (is_array($hookResult)) {
            $tree = $hookResult;
        }

        return $this->respond($this->render('forum/folder.html.twig', [
            'folder'        => $folder,
            'tree'          => $tree,
            'theme'         => $this->resolveTheme($folder),
            'announcements' => $this->announcements->getAnnouncementsFor('index', Auth::user()?->user_id ?? 0),
            'json_ld'       => $this->schemaOrg->folderShow($folder, $flatForums, (string) $this->config->get('site_name', 'Phorum')),
        ]));
    }

    public function markForumRead(Request $request): Response
    {
        $forumId = (int) ($request->tokens['forum_id'] ?? 0);
        $forum   = $this->forums->load($forumId);

        if ($forum === null || $forum->folder_flag) {
            return $this->notFound();
        }

        $currentUser = Auth::user();
        if ($currentUser === null) {
            return $this->redirect('/login');
        }

        if (!$request->isPost()) {
            return $this->redirect(Url::forum($forumId));
        }
        if ($r = $this->checkCsrf($request)) { return $r; }

        $this->newflags->markForumRead($currentUser->user_id, $forumId);
        return $this->redirect(Url::forum($forumId));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Recursively collect all Forum objects from the tree (folders and forums).
     *
     * @param  Forum[] $nodes
     * @return Forum[]
     */
    private function flattenTree(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $result[] = $node;
            if (!empty($node->children)) {
                $result = array_merge($result, $this->flattenTree($node->children));
            }
        }
        return $result;
    }

    /**
     * Recursively collect all non-folder forum IDs from the tree.
     *
     * @param  Forum[] $nodes
     * @return int[]
     */
    private function extractForumIds(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            if ($node->folder_flag) {
                $ids = array_merge($ids, $this->extractForumIds($node->children ?? []));
            } else {
                $ids[] = $node->forum_id;
            }
        }
        return $ids;
    }
}

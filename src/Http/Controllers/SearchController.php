<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\UserPermissionMapper;
use Phorum\Service\MysqlSearchService;
use Phorum\Service\PermissionService;
use Twig\Environment;

class SearchController extends Controller
{
    private const PAGE_SIZE = 20;

    private readonly ForumMapper       $forums;
    private readonly PermissionService $perms;

    public function __construct(
        Config             $config,
        Environment        $twig,
        ?ForumMapper       $forums = null,
        ?PermissionService $perms  = null,
    ) {
        parent::__construct($config, $twig);
        $this->forums = $forums ?? new ForumMapper();
        $this->perms  = $perms  ?? new PermissionService(new UserPermissionMapper());
    }

    public function index(Request $request): Response
    {
        $user   = Auth::user();

        // Build list of all readable non-folder forums for the filter dropdown
        $allForums  = $this->forums->find(
            filter: ['active' => 1, 'folder_flag' => 0],
            order:  'name ASC'
        ) ?? [];
        $readableForums = array_filter(
            $allForums,
            fn($f) => $this->perms->canRead($f, $user)
        );

        // No search submitted yet
        if (!isset($request->query['search']) && !isset($request->query['author'])) {
            return $this->respond($this->render('search/results.html.twig', [
                'readable_forums' => array_values($readableForums),
                'submitted'       => false,
            ]));
        }

        // Parse query params
        $query        = trim($request->query['search']        ?? '');
        $author       = trim($request->query['author']        ?? '');
        $matchType    = $request->query['match_type']          ?? 'ALL';
        $matchThreads = !empty($request->query['match_threads']);
        $dateRange    = (int) ($request->query['match_dates'] ?? 30);
        $page         = max(1, (int) ($request->query['page'] ?? 1));

        if (!in_array($matchType, ['ALL', 'ANY', 'PHRASE'], strict: true)) {
            $matchType = 'ALL';
        }
        if (!in_array($dateRange, [0, 30, 90, 365], strict: true)) {
            $dateRange = 30;
        }

        // Determine which forums to search
        $readableIds = array_map(fn($f) => $f->forum_id, array_values($readableForums));

        $requestedForumIds = array_map('intval', (array) ($request->query['match_forum'] ?? []));
        // Intersect with readable to prevent privilege escalation
        $searchForumIds = !empty($requestedForumIds)
            ? array_values(array_intersect($requestedForumIds, $readableIds))
            : $readableIds;

        $offset  = ($page - 1) * self::PAGE_SIZE;
        $service = new MysqlSearchService(
            dbName: defined('PHORUM_DB') ? PHORUM_DB : 'phorum',
            prefix: defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum',
        );

        $result  = $service->search(
            query:        $query,
            author:       $author,
            matchType:    $matchType,
            forumIds:     $searchForumIds,
            dateRange:    $dateRange,
            matchThreads: $matchThreads,
            limit:        self::PAGE_SIZE,
            offset:       $offset,
        );

        $total  = $result['total'];
        $pages  = $total > 0 ? (int) ceil($total / self::PAGE_SIZE) : 1;

        return $this->respond($this->render('search/results.html.twig', [
            'readable_forums'  => array_values($readableForums),
            'submitted'        => true,
            'query'            => $query,
            'author'           => $author,
            'match_type'       => $matchType,
            'match_threads'    => $matchThreads,
            'match_dates'      => $dateRange,
            'match_forum'      => $requestedForumIds,
            'messages'         => $result['messages'],
            'total'            => $total,
            'page'             => $page,
            'pages'            => $pages,
            'base_url'         => (static function () use ($query, $author, $matchType, $matchThreads, $dateRange, $requestedForumIds): string {
                $params = array_filter([
                    'search'        => $query,
                    'author'        => $author,
                    'match_type'    => $matchType,
                    'match_threads' => $matchThreads ? '1' : '',
                    'match_dates'   => (string) $dateRange,
                    'match_forum'   => $requestedForumIds,
                ], fn($v) => $v !== '' && $v !== null && $v !== []);
                return $params ? '/search?' . http_build_query($params) : '/search';
            })(),
        ]));
    }
}

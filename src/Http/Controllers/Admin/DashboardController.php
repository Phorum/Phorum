<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use DealNews\DB\CRUD;
use Phorum\Core\Config;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\SiteStatusService;
use Twig\Environment;

class DashboardController extends AdminController
{
    private readonly UserMapper        $users;
    private readonly MessageMapper     $messages;
    private readonly SiteStatusService $siteStatus;

    public function __construct(
        Config              $config,
        Environment         $twig,
        ?UserMapper         $users      = null,
        ?MessageMapper      $messages   = null,
        ?SiteStatusService  $siteStatus = null,
    ) {
        parent::__construct($config, $twig);
        $this->users      = $users      ?? new UserMapper();
        $this->messages   = $messages   ?? new MessageMapper();
        $this->siteStatus = $siteStatus ?? new SiteStatusService();
    }

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $db     = defined('PHORUM_DB') ? PHORUM_DB : 'phorum';
        $crud   = CRUD::factory($db);

        $stats = [
            'users'      => (int) ($crud->runFetch(
                "SELECT COUNT(*) AS n FROM {$prefix}_users WHERE active = 1", []
            )[0]['n'] ?? 0),
            'posts'      => (int) ($crud->runFetch(
                "SELECT COUNT(*) AS n FROM {$prefix}_messages WHERE status = 2", []
            )[0]['n'] ?? 0),
            'forums'     => (int) ($crud->runFetch(
                "SELECT COUNT(*) AS n FROM {$prefix}_forums WHERE active = 1 AND folder_flag = 0", []
            )[0]['n'] ?? 0),
            'unapproved' => (int) ($crud->runFetch(
                "SELECT COUNT(*) AS n FROM {$prefix}_messages WHERE status = 0", []
            )[0]['n'] ?? 0),
        ];

        $recentUsers = $this->users->find(
            filter: ['active' => 1],
            limit:  5,
            order:  'date_added DESC'
        ) ?? [];

        $recentPosts = $this->messages->findRecent(limit: 5) ?? [];

        return $this->respond($this->renderAdmin('admin/dashboard.html.twig', [
            'stats'          => $stats,
            'recent_users'   => $recentUsers,
            'recent_posts'   => $recentPosts,
            'current_status' => $this->siteStatus->current(),
            'status_labels'  => SiteStatusService::LABELS,
        ]));
    }

    /** Set the site-wide status (normal/read-only/admin-only/disabled). */
    public function setStatus(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }
        if ($r = $this->checkCsrf($request)) { return $r; }

        $this->siteStatus->set((string) ($request->post['status'] ?? ''));

        return $this->redirect('/admin');
    }
}

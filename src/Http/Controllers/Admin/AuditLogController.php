<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\Config;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mapper\UserMapper;
use Twig\Environment;

class AuditLogController extends AdminController
{
    private readonly ModLogMapper $modLog;
    private readonly UserMapper   $users;
    private readonly ForumMapper  $forums;

    public function __construct(
        Config        $config,
        Environment   $twig,
        ?ModLogMapper $modLog = null,
        ?UserMapper   $users  = null,
        ?ForumMapper  $forums = null,
    ) {
        parent::__construct($config, $twig);
        $this->modLog = $modLog ?? new ModLogMapper();
        $this->users  = $users  ?? new UserMapper();
        $this->forums = $forums ?? new ForumMapper();
    }

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $entries = $this->modLog->findRecent(200) ?? [];

        $userIds  = array_values(array_unique(array_map(fn($e) => $e->user_id, $entries)));
        $usersMap = $this->users->findByIds($userIds);

        $forumIds   = array_values(array_unique(array_map(fn($e) => $e->forum_id, $entries)));
        $forumNames = [];
        foreach ($this->forums->loadMulti($forumIds) ?? [] as $forum) {
            $forumNames[$forum->forum_id] = $forum->name;
        }

        return $this->respond($this->renderAdmin('admin/audit_log/index.html.twig', [
            'entries'     => $entries,
            'users_map'   => $usersMap,
            'forum_names' => $forumNames,
        ]));
    }
}

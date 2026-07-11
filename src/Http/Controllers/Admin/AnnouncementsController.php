<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\Config;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\SettingMapper;
use Twig\Environment;

class AnnouncementsController extends AdminController
{
    private readonly SettingMapper $settings;
    private readonly ForumMapper   $forums;

    public function __construct(
        Config         $config,
        Environment    $twig,
        ?SettingMapper $settings = null,
        ?ForumMapper   $forums   = null,
    ) {
        parent::__construct($config, $twig);
        $this->settings = $settings ?? new SettingMapper();
        $this->forums   = $forums   ?? new ForumMapper();
    }

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $stored = $this->settings->getSetting('announcements');
        $stored = is_array($stored) ? $stored : [];
        $stored += [
            'forum_id'         => 0,
            'number_to_show'   => 5,
            'days_to_show'     => 0,
            'only_show_unread' => false,
            'pages'            => [],
        ];
        $stored['pages'] += ['index' => true, 'list' => true, 'read' => true];

        $success = '';

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            $stored = [
                'forum_id'         => (int) ($request->post['forum_id'] ?? 0),
                'number_to_show'   => max(1, (int) ($request->post['number_to_show'] ?? 5)),
                'days_to_show'     => max(0, (int) ($request->post['days_to_show'] ?? 0)),
                'only_show_unread' => !empty($request->post['only_show_unread']),
                'pages'            => [
                    'index' => !empty($request->post['pages']['index']),
                    'list'  => !empty($request->post['pages']['list']),
                    'read'  => !empty($request->post['pages']['read']),
                ],
            ];

            $this->settings->saveSetting('announcements', $stored);
            $success = 'Settings saved.';
        }

        return $this->respond($this->renderAdmin('admin/announcements.html.twig', [
            'stored'  => $stored,
            'forums'  => $this->forums->find(filter: ['active' => 1, 'folder_flag' => 0], order: 'name ASC') ?? [],
            'success' => $success,
        ]));
    }
}

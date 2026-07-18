<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\AdminAuth;
use Phorum\Core\Config;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\BanMapper;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Model\Ban;
use Phorum\Service\BanService;
use Twig\Environment;

class BanController extends AdminController
{
    private const TYPES = [
        BanService::TYPE_IP         => 'IP Address',
        BanService::TYPE_NAME       => 'Username',
        BanService::TYPE_EMAIL      => 'Email Address',
        BanService::TYPE_USERID     => 'User ID',
        BanService::TYPE_SPAM_WORDS => 'Spam Words (message body)',
    ];

    private readonly BanMapper    $bans;
    private readonly ForumMapper  $forums;
    private readonly ModLogMapper $modLog;

    public function __construct(
        Config        $config,
        Environment   $twig,
        ?BanMapper    $bans   = null,
        ?ForumMapper  $forums = null,
        ?ModLogMapper $modLog = null,
    ) {
        parent::__construct($config, $twig);
        $this->bans   = $bans   ?? new BanMapper();
        $this->forums = $forums ?? new ForumMapper();
        $this->modLog = $modLog ?? new ModLogMapper();
    }

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $bans  = $this->bans->find(filter: [], order: 'forum_id ASC, type ASC') ?? [];
        $names = [];
        foreach ($this->loadForums() as $forum) {
            $names[$forum->forum_id] = $forum->name;
        }

        return $this->respond($this->renderAdmin('admin/bans/index.html.twig', [
            'bans'         => $bans,
            'types'        => self::TYPES,
            'forum_names'  => $names,
        ]));
    }

    public function create(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $errors = [];
        $ban    = new Ban();

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $errors = $this->applyPost($ban, $request);

            if (empty($errors)) {
                $this->bans->save($ban);
                $this->logAction('create', $ban);
                phorum_api_hook('after_ban_create', $ban);
                return $this->redirect('/admin/bans');
            }
        }

        return $this->respond($this->renderAdmin('admin/bans/edit.html.twig', [
            'ban'    => $ban,
            'types'  => self::TYPES,
            'forums' => $this->loadForums(),
            'errors' => $errors,
            'is_new' => true,
        ]));
    }

    public function edit(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $banId = (int) ($request->tokens['ban_id'] ?? 0);
        $ban   = $this->bans->load($banId);
        if ($ban === null) { return $this->notFound(); }

        $errors = [];

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $errors = $this->applyPost($ban, $request);

            if (empty($errors)) {
                $this->bans->save($ban);
                $this->logAction('update', $ban);
                return $this->redirect('/admin/bans');
            }
        }

        return $this->respond($this->renderAdmin('admin/bans/edit.html.twig', [
            'ban'    => $ban,
            'types'  => self::TYPES,
            'forums' => $this->loadForums(),
            'errors' => $errors,
            'is_new' => false,
        ]));
    }

    public function delete(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $banId = (int) ($request->tokens['ban_id'] ?? 0);
        $ban   = $this->bans->load($banId);
        if ($ban === null) { return $this->notFound(); }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $this->logAction('delete', $ban);
            $this->bans->delete($ban->id);
            return $this->redirect('/admin/bans');
        }

        return $this->respond($this->renderAdmin('admin/bans/delete_confirm.html.twig', [
            'ban' => $ban,
        ]));
    }

    // -------------------------------------------------------------------------

    private function applyPost(Ban $ban, Request $request): array
    {
        $errors = [];

        $type   = (int) ($request->post['type'] ?? 0);
        $string = trim($request->post['string'] ?? '');

        if (!isset(self::TYPES[$type])) {
            $errors[] = 'A valid ban type is required.';
        }
        if ($string === '') {
            $errors[] = 'The value to match is required.';
        } elseif (mb_strlen($string) > 255) {
            $errors[] = 'The value to match must be 255 characters or fewer.';
        }

        if (empty($errors)) {
            $ban->forum_id = (int) ($request->post['forum_id'] ?? 0);
            $ban->type     = $type;
            $ban->pcre     = !empty($request->post['pcre']) ? 1 : 0;
            $ban->string   = $string;
            $ban->comments = trim($request->post['comments'] ?? '');
        }

        return $errors;
    }

    private function loadForums(): array
    {
        return $this->forums->find(
            filter: ['active' => 1, 'folder_flag' => 0],
            order:  'name ASC'
        ) ?? [];
    }

    private function logAction(string $action, Ban $ban): void
    {
        $admin = AdminAuth::user();
        $this->modLog->record(
            userId:     $admin?->user_id ?? 0,
            action:     $action,
            objectType: 'ban',
            objectId:   $ban->id,
            forumId:    $ban->forum_id,
            details:    $ban->string,
        );
    }
}

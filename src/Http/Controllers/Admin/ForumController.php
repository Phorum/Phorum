<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\Config;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\ForumMapper;
use Phorum\Model\Forum;
use Phorum\Service\PermissionFlags;
use Twig\Environment;

class ForumController extends AdminController
{
    private readonly ForumMapper $forums;

    public function __construct(
        Config       $config,
        Environment  $twig,
        ?ForumMapper $forums = null,
    ) {
        parent::__construct($config, $twig);
        $this->forums = $forums ?? new ForumMapper();
    }

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $forums = $this->forums->find(filter: [], order: 'vroot ASC, display_order ASC, name ASC') ?? [];

        return $this->respond($this->renderAdmin('admin/forums/index.html.twig', [
            'forums' => $forums,
        ]));
    }

    public function create(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $errors  = [];
        $forum   = new Forum();
        $folders = $this->loadFolders();
        $themes  = $this->loadThemes(withDefault: true);

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $errors = $this->applyPost($forum, $request, $themes);

            if (empty($errors)) {
                $this->forums->save($forum);
                return $this->redirect('/admin/forums');
            }
        }

        return $this->respond($this->renderAdmin('admin/forums/edit.html.twig', [
            'forum'          => $forum,
            'folders'        => $folders,
            'themes'         => $themes,
            'errors'         => $errors,
            'is_new'         => true,
            'perm_flags'     => PermissionFlags::FLAGS,
            'pub_perm_bits'  => PermissionFlags::decode($forum->pub_perms),
            'reg_perm_bits'  => PermissionFlags::decode($forum->reg_perms),
        ]));
    }

    public function edit(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $forumId = (int) ($request->tokens['forum_id'] ?? 0);
        $forum   = $this->forums->load($forumId);
        if ($forum === null) { return $this->notFound(); }

        $errors  = [];
        $folders = $this->loadFolders();
        $themes  = $this->loadThemes(withDefault: true);

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $errors = $this->applyPost($forum, $request, $themes);

            if (empty($errors)) {
                $this->forums->save($forum);
                return $this->redirect('/admin/forums');
            }
        }

        return $this->respond($this->renderAdmin('admin/forums/edit.html.twig', [
            'forum'          => $forum,
            'folders'        => $folders,
            'themes'         => $themes,
            'errors'         => $errors,
            'is_new'         => false,
            'perm_flags'     => PermissionFlags::FLAGS,
            'pub_perm_bits'  => PermissionFlags::decode($forum->pub_perms),
            'reg_perm_bits'  => PermissionFlags::decode($forum->reg_perms),
        ]));
    }

    public function delete(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $forumId = (int) ($request->tokens['forum_id'] ?? 0);
        $forum   = $this->forums->load($forumId);
        if ($forum === null) { return $this->notFound(); }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            // Soft-delete: deactivate rather than DROP data
            $forum->active = 0;
            $this->forums->save($forum);
            if ($forum->folder_flag) {
                phorum_api_hook('admin_folder_delete', $forum->forum_id);
            } else {
                phorum_api_hook('admin_forum_delete', $forum->forum_id);
            }
            return $this->redirect('/admin/forums');
        }

        return $this->respond($this->renderAdmin('admin/forums/delete_confirm.html.twig', [
            'forum' => $forum,
        ]));
    }

    // -------------------------------------------------------------------------

    private function applyPost(Forum $forum, Request $request, array $themes = []): array
    {
        $errors = [];

        $name = trim($request->post['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Name is required.';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = 'Name must be 255 characters or fewer.';
        }

        if (empty($errors)) {
            $forum->name              = $name;
            $forum->description       = trim($request->post['description']   ?? '');
            $forum->active            = !empty($request->post['active'])      ? 1 : 0;
            $forum->folder_flag       = !empty($request->post['folder_flag']) ? 1 : 0;
            $forum->vroot             = (int) ($request->post['vroot']      ?? 0);
            $forum->parent_id         = (int) ($request->post['parent_id'] ?? $forum->vroot);
            $forum->moderation        = (int) ($request->post['moderation']   ?? 0);
            $forum->email_moderators  = !empty($request->post['email_moderators']) ? 1 : 0;
            $forum->threaded_read     = !empty($request->post['threaded_read'])    ? 1 : 0;
            $forum->threaded_list     = !empty($request->post['threaded_list'])    ? 1 : 0;
            $forum->list_length_flat  = max(1, (int) ($request->post['list_length_flat'] ?? 25));
            $forum->pub_perms         = PermissionFlags::combine($request->post['pub_perms'] ?? []);
            $forum->reg_perms         = PermissionFlags::combine($request->post['reg_perms'] ?? []);
            $forum->display_order     = (int) ($request->post['display_order'] ?? 0);
            $selectedTheme            = trim($request->post['template'] ?? '');
            $forum->template          = array_key_exists($selectedTheme, $themes) ? $selectedTheme : '';
            phorum_api_hook('admin_editforum_form_save_after_defaults', $forum);
        }

        return $errors;
    }

    private function loadFolders(): array
    {
        return $this->forums->find(
            filter: ['folder_flag' => 1, 'active' => 1],
            order:  'name ASC'
        ) ?? [];
    }
}

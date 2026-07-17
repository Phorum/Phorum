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

        return $this->renderForumList(null);
    }

    /** Show just one folder's own forums/sub-folders (`/admin/forums/folder/{id}`). */
    public function folder(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $folderId = (int) ($request->tokens['forum_id'] ?? 0);
        $folder   = $this->forums->load($folderId);
        if ($folder === null || !$folder->folder_flag) {
            return $this->notFound();
        }

        return $this->renderForumList($folder);
    }

    public function create(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $errors    = [];
        $forum     = new Forum();
        $folders   = $this->loadFolders();
        $themes    = $this->loadThemes(withDefault: true);
        $parentId  = (int) ($request->query['parent_id'] ?? 0);
        $backUrl   = $parentId > 0 ? '/admin/forums/folder/' . $parentId : '/admin/forums';
        $forum->vroot = $parentId;

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $errors = $this->applyPost($forum, $request, $themes, isNew: true);

            if (empty($errors)) {
                $this->forums->save($forum);
                return $this->redirect($this->backUrlFor($forum));
            }
        }

        return $this->respond($this->renderAdmin('admin/forums/edit.html.twig', [
            'forum'          => $forum,
            'folders'        => $folders,
            'themes'         => $themes,
            'errors'         => $errors,
            'is_new'         => true,
            'back_url'       => $backUrl,
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
                return $this->redirect($this->backUrlFor($forum));
            }
        }

        return $this->respond($this->renderAdmin('admin/forums/edit.html.twig', [
            'forum'          => $forum,
            'folders'        => $folders,
            'themes'         => $themes,
            'errors'         => $errors,
            'is_new'         => false,
            'back_url'       => $this->backUrlFor($forum),
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
            return $this->redirect($this->backUrlFor($forum));
        }

        return $this->respond($this->renderAdmin('admin/forums/delete_confirm.html.twig', [
            'forum'    => $forum,
            'back_url' => $this->backUrlFor($forum),
        ]));
    }

    /** Move a forum/folder one position earlier within its sibling group. */
    public function moveUp(Request $request): Response
    {
        return $this->move($request, -1);
    }

    /** Move a forum/folder one position later within its sibling group. */
    public function moveDown(Request $request): Response
    {
        return $this->move($request, 1);
    }

    // -------------------------------------------------------------------------

    /**
     * Swap $forumId with the sibling immediately before ($direction = -1) or
     * after ($direction = 1) it in display order, then renumber the whole
     * sibling group to a clean 0..N-1 sequence matching the new order.
     * Renumbering (rather than swapping the two raw display_order values) is
     * necessary because ties are the common case — most rows default to
     * display_order = 0, so swapping two equal values would be a silent no-op.
     */
    private function move(Request $request, int $direction): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }
        if (!$request->isPost()) { return $this->notFound(); }
        if ($r = $this->checkCsrf($request)) { return $r; }

        $forumId = (int) ($request->tokens['forum_id'] ?? 0);
        $forum   = $this->forums->load($forumId);
        if ($forum === null) { return $this->notFound(); }

        $siblings = $this->forums->find(
            filter: ['parent_id' => $forum->parent_id],
            order:  'display_order ASC, name ASC'
        ) ?? [];

        $index = null;
        foreach ($siblings as $i => $sibling) {
            if ($sibling->forum_id === $forum->forum_id) {
                $index = $i;
                break;
            }
        }
        $target = $index !== null ? $index + $direction : null;

        if ($index !== null && $target !== null && $target >= 0 && $target < count($siblings)) {
            [$siblings[$index], $siblings[$target]] = [$siblings[$target], $siblings[$index]];
            foreach ($siblings as $position => $sibling) {
                if ($sibling->display_order !== $position) {
                    $sibling->display_order = $position;
                    $this->forums->save($sibling);
                }
            }
        }

        return $this->redirect($this->backUrlFor($forum));
    }

    /** Render the forum list scoped to $folder's children, or the root level when null. */
    private function renderForumList(?Forum $folder): Response
    {
        $forums = $this->forums->find(
            filter: ['parent_id' => $folder?->forum_id ?? 0],
            order:  'display_order ASC, name ASC'
        ) ?? [];

        return $this->respond($this->renderAdmin('admin/forums/index.html.twig', [
            'forums'     => $forums,
            'folder'     => $folder,
            'breadcrumb' => $folder !== null ? $this->breadcrumbFor($folder) : [],
        ]));
    }

    /**
     * Walk parent_id up from $folder to the root, returning an ordered
     * root-to-leaf list of ['name' => ..., 'url' => ...] pairs (leaf = $folder
     * itself). Capped at a fixed depth as a guard against a self-referential
     * parent_id cycle, since nothing currently prevents one on save.
     *
     * @return array<int, array{name: string, url: string}>
     */
    private function breadcrumbFor(Forum $folder): array
    {
        $chain = [[
            'name' => $folder->name,
            'url'  => '/admin/forums/folder/' . $folder->forum_id,
        ]];

        $current = $folder;
        for ($i = 0; $i < 20 && $current->parent_id !== 0; $i++) {
            $current = $this->forums->load($current->parent_id);
            if ($current === null) {
                break;
            }
            array_unshift($chain, [
                'name' => $current->name,
                'url'  => '/admin/forums/folder/' . $current->forum_id,
            ]);
        }

        return $chain;
    }

    /** Where to redirect/link back to after saving/deleting $forum. */
    private function backUrlFor(Forum $forum): string
    {
        return $forum->parent_id > 0
            ? '/admin/forums/folder/' . $forum->parent_id
            : '/admin/forums';
    }

    private function applyPost(Forum $forum, Request $request, array $themes = [], bool $isNew = false): array
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
            // folder_flag can't change after creation (see edit.html.twig) —
            // the field is rendered disabled on edit, so it's simply absent
            // from the POST body then; only read it while creating.
            if ($isNew) {
                $forum->folder_flag = !empty($request->post['folder_flag']) ? 1 : 0;
            }
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

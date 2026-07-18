<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\AdminAuth;
use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\Impersonation;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\CustomFieldConfigMapper;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mapper\SearchMapper;
use Phorum\Mapper\UserCustomFieldMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\CustomFieldService;
use Twig\Environment;

class UserController extends AdminController
{
    private const PAGE_SIZE = 30;

    private readonly UserMapper         $users;
    private readonly CustomFieldService $cfService;
    private readonly ModLogMapper       $modLog;
    private readonly MessageMapper      $messages;
    private readonly SearchMapper       $searchIndex;

    public function __construct(
        Config              $config,
        Environment         $twig,
        ?UserMapper         $users       = null,
        ?CustomFieldService $cfService   = null,
        ?ModLogMapper       $modLog      = null,
        ?MessageMapper      $messages    = null,
        ?SearchMapper       $searchIndex = null,
    ) {
        parent::__construct($config, $twig);
        $this->users       = $users       ?? new UserMapper();
        $this->cfService   = $cfService   ?? new CustomFieldService(new CustomFieldConfigMapper(), new UserCustomFieldMapper());
        $this->modLog      = $modLog      ?? new ModLogMapper();
        $this->messages    = $messages    ?? new MessageMapper();
        $this->searchIndex = $searchIndex ?? new SearchMapper();
    }

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $search = trim($request->query['search'] ?? '');
        $page   = max(1, (int) ($request->query['page'] ?? 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        if ($search !== '') {
            // Simple search by username or display_name
            $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
            $db     = defined('PHORUM_DB') ? PHORUM_DB : 'phorum';
            $crud   = \DealNews\DB\CRUD::factory($db);
            $like = "%{$search}%";
            $countRow = $crud->runFetch(
                "SELECT COUNT(*) AS n FROM {$prefix}_users"
                . " WHERE username LIKE :s1 OR display_name LIKE :s2 OR email LIKE :s3",
                [':s1' => $like, ':s2' => $like, ':s3' => $like]
            );
            $total = (int) ($countRow[0]['n'] ?? 0);
            // Fetch IDs then use loadMulti to get typed objects
            $idRows = $crud->runFetch(
                "SELECT user_id FROM {$prefix}_users"
                . " WHERE username LIKE :s1 OR display_name LIKE :s2 OR email LIKE :s3"
                . " ORDER BY username ASC LIMIT " . self::PAGE_SIZE . " OFFSET {$offset}",
                [':s1' => $like, ':s2' => $like, ':s3' => $like]
            ) ?: [];
            $ids   = array_column($idRows, 'user_id');
            $users = $ids ? ($this->users->loadMulti($ids) ?? []) : [];
        } else {
            $users = $this->users->find(filter: [], limit: self::PAGE_SIZE, start: $offset, order: 'date_added DESC') ?? [];
            $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
            $db     = defined('PHORUM_DB') ? PHORUM_DB : 'phorum';
            $total  = (int) (\DealNews\DB\CRUD::factory($db)->runFetch(
                "SELECT COUNT(*) AS n FROM {$prefix}_users", []
            )[0]['n'] ?? 0);
        }

        return $this->respond($this->renderAdmin('admin/users/index.html.twig', [
            'users'  => $users,
            'search' => $search,
            'page'   => $page,
            'pages'  => $total > 0 ? (int) ceil($total / self::PAGE_SIZE) : 1,
            'total'  => $total,
        ]));
    }

    public function edit(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $userId = (int) ($request->tokens['user_id'] ?? 0);
        $user   = $this->users->load($userId);
        if ($user === null) { return $this->notFound(); }

        $errors  = [];
        $success = '';

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $displayName = trim($request->post['display_name'] ?? '');
            $email       = trim($request->post['email']        ?? '');
            $active      = !empty($request->post['active']);
            $admin       = !empty($request->post['admin']);
            $forcePwChange = !empty($request->post['force_password_change']);
            $shadowBanned  = !empty($request->post['shadow_banned']);
            $password    = $request->post['password'] ?? '';

            if ($displayName === '') {
                $errors[] = 'Display name is required.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Valid email is required.';
            } else {
                $taken = $this->users->findByEmail($email);
                if ($taken !== null && $taken->user_id !== $userId) {
                    $errors[] = 'Email already in use.';
                }
            }
            if ($password !== '' && strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }
            $currentAdmin = AdminAuth::user();
            if ($shadowBanned && ($admin || $userId === $currentAdmin->user_id)) {
                $errors[] = 'Admins cannot be shadow banned.';
            }

            $cfErrors = $this->cfService->saveUserFields($userId, $request->post['custom_fields'] ?? [], dryRun: true);
            $errors   = array_merge($errors, $cfErrors);

            if (empty($errors)) {
                $wasShadowBanned = (bool) $user->shadow_banned;

                $user->display_name = $displayName;
                $user->email        = $email;
                $user->active       = $active ? 1 : 0;
                $user->admin        = $admin  ? 1 : 0;
                $user->force_password_change = $forcePwChange ? 1 : 0;
                $user->shadow_banned = $shadowBanned ? 1 : 0;
                if ($password !== '') {
                    $user->password = password_hash($password, PASSWORD_BCRYPT);
                }
                $this->users->save($user);
                $this->cfService->saveUserFields($userId, $request->post['custom_fields'] ?? []);

                if ($shadowBanned && !$wasShadowBanned) {
                    $this->applyShadowBan($userId);
                    $this->modLog->record($currentAdmin->user_id, 'shadow_ban_enable', 'user', $userId, 0, $user->username);
                } elseif (!$shadowBanned && $wasShadowBanned) {
                    $this->liftShadowBan($userId);
                    $this->modLog->record($currentAdmin->user_id, 'shadow_ban_disable', 'user', $userId, 0, $user->username);
                }

                phorum_api_hook('admin_users_form_save', $request->post);
                $success = 'Changes saved.';
            }
        }

        phorum_api_hook('admin_users_form', $user);
        return $this->respond($this->renderAdmin('admin/users/edit.html.twig', [
            'profile'        => $user,
            'admin_fields'   => $this->cfService->getAdminUserFields($userId),
            'errors'         => $errors,
            'success'        => $success,
            'admin_user_id'  => AdminAuth::user()->user_id,
        ]));
    }

    /**
     * Retroactively hide a newly shadow-banned user's existing approved
     * posts, and drop them from the search index — mirrors how
     * ModerationService keeps the index in sync on approve/delete.
     */
    private function applyShadowBan(int $userId): void
    {
        $ids = $this->messages->findIdsByUserStatus($userId, MessageMapper::STATUS_APPROVED);
        $this->messages->setStatusForUser($userId, MessageMapper::STATUS_APPROVED, MessageMapper::STATUS_SHADOW);
        foreach ($ids as $id) {
            $this->searchIndex->removeMessage($id);
        }
    }

    /** Restore a lifted shadow ban's posts to visible and back into the search index. */
    private function liftShadowBan(int $userId): void
    {
        $ids = $this->messages->findIdsByUserStatus($userId, MessageMapper::STATUS_SHADOW);
        $this->messages->setStatusForUser($userId, MessageMapper::STATUS_SHADOW, MessageMapper::STATUS_APPROVED);
        foreach ($ids as $id) {
            $msg = $this->messages->load($id);
            if ($msg !== null) {
                $this->searchIndex->indexMessage($msg->message_id, $msg->forum_id, $msg->author, $msg->subject, $msg->body);
            }
        }
    }

    /** Start impersonating the target user as the current admin. */
    public function impersonate(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }
        if ($r = $this->checkCsrf($request)) { return $r; }

        $userId = (int) ($request->tokens['user_id'] ?? 0);
        $target = $this->users->load($userId);
        if ($target === null) { return $this->notFound(); }

        $admin = AdminAuth::user();
        if ($target->admin || $target->user_id === $admin->user_id) {
            return $this->forbidden();
        }

        if (!Impersonation::start($admin, $target, $this->config)) {
            return $this->forbidden();
        }

        $this->modLog->record(
            userId:     $admin->user_id,
            action:     'impersonate_start',
            objectType: 'user',
            objectId:   $target->user_id,
            forumId:    0,
            details:    $target->username,
        );

        return $this->redirect('/');
    }

    /** Stop impersonating and restore the admin's own front-end identity. */
    public function stopImpersonate(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }
        if ($r = $this->checkCsrf($request)) { return $r; }

        $admin  = Impersonation::admin();
        $target = Auth::user();

        Impersonation::stop($this->config);

        if ($admin !== null && $target !== null) {
            $this->modLog->record(
                userId:     $admin->user_id,
                action:     'impersonate_stop',
                objectType: 'user',
                objectId:   $target->user_id,
                forumId:    0,
                details:    $target->username,
            );
        }

        return $this->redirect('/admin/users');
    }
}

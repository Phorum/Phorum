<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\AdminAuth;
use Phorum\Core\Config;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\ForumGroupXrefMapper;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\GroupMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mapper\UserGroupXrefMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\Group;
use Phorum\Service\PermissionFlags;
use Twig\Environment;

class GroupController extends AdminController
{
    private readonly GroupMapper          $groups;
    private readonly UserGroupXrefMapper  $memberships;
    private readonly ForumGroupXrefMapper $forumGrants;
    private readonly UserMapper           $users;
    private readonly ForumMapper          $forums;
    private readonly ModLogMapper         $modLog;

    public function __construct(
        Config                 $config,
        Environment            $twig,
        ?GroupMapper           $groups      = null,
        ?UserGroupXrefMapper   $memberships = null,
        ?ForumGroupXrefMapper  $forumGrants = null,
        ?UserMapper            $users       = null,
        ?ForumMapper           $forums      = null,
        ?ModLogMapper          $modLog      = null,
    ) {
        parent::__construct($config, $twig);
        $this->groups      = $groups      ?? new GroupMapper();
        $this->memberships = $memberships ?? new UserGroupXrefMapper();
        $this->forumGrants = $forumGrants ?? new ForumGroupXrefMapper();
        $this->users       = $users       ?? new UserMapper();
        $this->forums      = $forums      ?? new ForumMapper();
        $this->modLog      = $modLog      ?? new ModLogMapper();
    }

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $groups = $this->groups->find(filter: [], order: 'name ASC') ?? [];

        return $this->respond($this->renderAdmin('admin/groups/index.html.twig', [
            'groups' => $groups,
        ]));
    }

    public function create(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $errors = [];
        $group  = new Group();

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $errors = $this->applyPost($group, $request);

            if (empty($errors)) {
                $this->groups->save($group);
                $this->logAction('create', $group);
                return $this->redirect('/admin/groups/' . $group->group_id . '/edit');
            }
        }

        return $this->respond($this->renderAdmin('admin/groups/edit.html.twig', [
            'group'   => $group,
            'errors'  => $errors,
            'is_new'  => true,
            'members' => [],
            'grants'  => [],
            'forums'  => [],
            'perm_flags' => PermissionFlags::FLAGS,
        ]));
    }

    public function edit(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $groupId = (int) ($request->tokens['group_id'] ?? 0);
        $group   = $this->groups->load($groupId);
        if ($group === null) { return $this->notFound(); }

        $errors = [];

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $errors = $this->applyPost($group, $request);

            if (empty($errors)) {
                $this->groups->save($group);
                $this->logAction('update', $group);
                return $this->redirect('/admin/groups/' . $group->group_id . '/edit');
            }
        }

        return $this->respond($this->renderAdmin('admin/groups/edit.html.twig', [
            'group'      => $group,
            'errors'     => $errors,
            'is_new'     => false,
            'members'    => $this->loadMembers($groupId),
            'grants'     => $this->loadGrants($groupId),
            'forums'     => $this->forums->find(filter: ['active' => 1, 'folder_flag' => 0], order: 'name ASC') ?? [],
            'perm_flags' => PermissionFlags::FLAGS,
        ]));
    }

    public function delete(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $groupId = (int) ($request->tokens['group_id'] ?? 0);
        $group   = $this->groups->load($groupId);
        if ($group === null) { return $this->notFound(); }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            foreach ($this->memberships->findByGroup($groupId) ?? [] as $member) {
                $this->memberships->delete($member->user_group_xref_id);
            }
            foreach ($this->forumGrants->findByGroup($groupId) ?? [] as $grant) {
                $this->forumGrants->delete($grant->forum_group_xref_id);
            }
            $this->groups->delete($groupId);
            $this->logAction('delete', $group);

            return $this->redirect('/admin/groups');
        }

        return $this->respond($this->renderAdmin('admin/groups/delete_confirm.html.twig', [
            'group' => $group,
        ]));
    }

    // -------------------------------------------------------------------------
    // Member management
    // -------------------------------------------------------------------------

    public function addMember(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $groupId = (int) ($request->tokens['group_id'] ?? 0);
        $group   = $this->groups->load($groupId);
        if ($group === null) { return $this->notFound(); }
        if (!$request->isPost()) { return $this->notFound(); }
        if ($r = $this->checkCsrf($request)) { return $r; }

        $username = trim($request->post['username'] ?? '');
        $status   = (int) ($request->post['status'] ?? UserGroupXrefMapper::STATUS_APPROVED);
        $user     = $username !== '' ? $this->users->findByUsername($username) : null;

        if ($user !== null) {
            $this->memberships->setMembership($user->user_id, $groupId, $status);
            $this->logAction('add_member', $group, $user->username);
        }

        return $this->redirect('/admin/groups/' . $groupId . '/edit');
    }

    public function removeMember(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $groupId = (int) ($request->tokens['group_id'] ?? 0);
        $userId  = (int) ($request->tokens['user_id'] ?? 0);
        $group   = $this->groups->load($groupId);
        if ($group === null) { return $this->notFound(); }
        if (!$request->isPost()) { return $this->notFound(); }
        if ($r = $this->checkCsrf($request)) { return $r; }

        $this->memberships->removeMembership($userId, $groupId);
        $this->logAction('remove_member', $group, (string) $userId);

        return $this->redirect('/admin/groups/' . $groupId . '/edit');
    }

    public function setMemberStatus(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $groupId = (int) ($request->tokens['group_id'] ?? 0);
        $userId  = (int) ($request->tokens['user_id'] ?? 0);
        $group   = $this->groups->load($groupId);
        if ($group === null) { return $this->notFound(); }
        if (!$request->isPost()) { return $this->notFound(); }
        if ($r = $this->checkCsrf($request)) { return $r; }

        $status = (int) ($request->post['status'] ?? UserGroupXrefMapper::STATUS_APPROVED);
        $this->memberships->setMembership($userId, $groupId, $status);
        $this->logAction('set_member_status', $group, (string) $userId);

        return $this->redirect('/admin/groups/' . $groupId . '/edit');
    }

    // -------------------------------------------------------------------------
    // Per-forum permission grants
    // -------------------------------------------------------------------------

    public function savePermissions(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $groupId = (int) ($request->tokens['group_id'] ?? 0);
        $group   = $this->groups->load($groupId);
        if ($group === null) { return $this->notFound(); }
        if (!$request->isPost()) { return $this->notFound(); }
        if ($r = $this->checkCsrf($request)) { return $r; }

        $posted = $request->post['perms'] ?? [];
        foreach ($this->forums->find(filter: ['active' => 1, 'folder_flag' => 0]) ?? [] as $forum) {
            $bits = $posted[$forum->forum_id] ?? [];
            $perm = PermissionFlags::combine($bits);
            if ($perm > 0) {
                $this->forumGrants->setPermission($forum->forum_id, $groupId, $perm);
            } else {
                $this->forumGrants->removePermission($forum->forum_id, $groupId);
            }
        }
        $this->logAction('set_permissions', $group);

        return $this->redirect('/admin/groups/' . $groupId . '/edit');
    }

    // -------------------------------------------------------------------------

    private function applyPost(Group $group, Request $request): array
    {
        $errors = [];

        $name = trim($request->post['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Name is required.';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = 'Name must be 255 characters or fewer.';
        }

        if (empty($errors)) {
            $group->name = $name;
            $group->open = !empty($request->post['open']) ? 1 : 0;
        }

        return $errors;
    }

    /** Members of a group, joined with usernames, newest membership first. */
    private function loadMembers(int $groupId): array
    {
        $rows = $this->memberships->findByGroup($groupId) ?? [];
        if (empty($rows)) {
            return [];
        }

        $userIds  = array_map(fn($r) => $r->user_id, $rows);
        $usersMap = $this->users->findByIds($userIds);

        $members = [];
        foreach ($rows as $row) {
            $members[] = [
                'membership' => $row,
                'user'       => $usersMap[$row->user_id] ?? null,
            ];
        }
        return $members;
    }

    /** [forum_id => permission bitmask] for the group's existing grants. */
    private function loadGrants(int $groupId): array
    {
        $grants = [];
        foreach ($this->forumGrants->findByGroup($groupId) ?? [] as $grant) {
            $grants[$grant->forum_id] = $grant->permission;
        }
        return $grants;
    }

    private function logAction(string $action, Group $group, string $details = ''): void
    {
        $admin = AdminAuth::user();
        $this->modLog->record(
            userId:     $admin?->user_id ?? 0,
            action:     $action,
            objectType: 'group',
            objectId:   $group->group_id,
            forumId:    0,
            details:    $details !== '' ? $details : $group->name,
        );
    }
}

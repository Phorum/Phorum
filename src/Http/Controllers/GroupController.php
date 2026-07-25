<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\GroupMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mapper\UserGroupXrefMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\Group;
use Twig\Environment;

/**
 * Front-end (non-admin) group self-service: browse/join/leave groups, and a
 * lightweight approval panel for users who hold Moderator status on a
 * specific group. Site-wide group CRUD and moderator appointment remain
 * admin-only (Admin\GroupController) — a group moderator granted here can
 * never promote anyone to Moderator or touch another moderator's status.
 */
class GroupController extends Controller
{
    private readonly GroupMapper         $groups;
    private readonly UserGroupXrefMapper $memberships;
    private readonly UserMapper          $users;
    private readonly ModLogMapper        $modLog;

    public function __construct(
        Config                $config,
        Environment           $twig,
        ?GroupMapper          $groups      = null,
        ?UserGroupXrefMapper  $memberships = null,
        ?UserMapper           $users       = null,
        ?ModLogMapper         $modLog      = null,
    ) {
        parent::__construct($config, $twig);
        $this->groups      = $groups      ?? new GroupMapper();
        $this->memberships = $memberships ?? new UserGroupXrefMapper();
        $this->users       = $users       ?? new UserMapper();
        $this->modLog      = $modLog      ?? new ModLogMapper();
    }

    private function requireLogin(Request $request): ?Response
    {
        if (Auth::user() === null) {
            return $this->redirect('/login?redirect=' . urlencode($request->server['REQUEST_URI'] ?? '/groups'));
        }
        return null;
    }

    /** GET /groups — your own memberships, plus any open groups you can request to join. */
    public function index(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }
        $user = Auth::user();

        $allGroups          = $this->groups->find(filter: [], order: 'name ASC') ?? [];
        $membershipsByGroup = [];
        foreach ($this->memberships->findByUser($user->user_id) ?? [] as $m) {
            $membershipsByGroup[$m->group_id] = $m;
        }

        $myGroups = [];
        $joinable = [];
        foreach ($allGroups as $group) {
            $membership = $membershipsByGroup[$group->group_id] ?? null;
            if ($membership !== null) {
                $myGroups[] = ['group' => $group, 'membership' => $membership];
            } elseif ($group->open) {
                $joinable[] = $group;
            }
        }

        return $this->respond($this->render('user/groups.html.twig', [
            'my_groups' => $myGroups,
            'joinable'  => $joinable,
        ]));
    }

    /** POST /groups/{group_id}/join — request to join an open group. */
    public function join(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }
        if ($r = $this->checkCsrf($request)) { return $r; }
        $user = Auth::user();

        $groupId = (int) ($request->tokens['group_id'] ?? 0);
        $group   = $this->groups->load($groupId);
        if ($group === null) { return $this->notFound(); }
        if (!$group->open) { return $this->forbidden(); }

        if ($this->memberships->findByUserAndGroup($user->user_id, $groupId) === null) {
            $this->memberships->setMembership($user->user_id, $groupId, UserGroupXrefMapper::STATUS_UNAPPROVED);
        }

        return $this->redirect('/groups');
    }

    /**
     * POST /groups/{group_id}/leave — leave a group you belong to.
     * A Suspended membership can't be lifted this way — that's a moderator
     * decision, not a no-op you can route around by leaving and rejoining.
     */
    public function leave(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }
        if ($r = $this->checkCsrf($request)) { return $r; }
        $user = Auth::user();

        $groupId    = (int) ($request->tokens['group_id'] ?? 0);
        $membership = $this->memberships->findByUserAndGroup($user->user_id, $groupId);

        if ($membership !== null && $membership->status !== UserGroupXrefMapper::STATUS_SUSPENDED) {
            $this->memberships->removeMembership($user->user_id, $groupId);
        }

        return $this->redirect('/groups');
    }

    /** GET /groups/{group_id}/moderate — review pending/approved/suspended members. */
    public function moderate(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }
        $user = Auth::user();

        $groupId = (int) ($request->tokens['group_id'] ?? 0);
        $group   = $this->groups->load($groupId);
        if ($group === null) { return $this->notFound(); }
        if (!$this->memberships->isModerator($user->user_id, $groupId)) { return $this->forbidden(); }

        [$pending, $approved, $suspended] = $this->loadRoster($groupId);

        return $this->respond($this->render('user/group_moderate.html.twig', [
            'group'     => $group,
            'pending'   => $pending,
            'approved'  => $approved,
            'suspended' => $suspended,
        ]));
    }

    /** POST /groups/{group_id}/moderate/{user_id}/status — approve, reinstate, or suspend a non-moderator member. */
    public function setMemberStatus(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }
        if ($r = $this->checkCsrf($request)) { return $r; }
        $user = Auth::user();

        $groupId  = (int) ($request->tokens['group_id'] ?? 0);
        $targetId = (int) ($request->tokens['user_id']  ?? 0);
        $group    = $this->groups->load($groupId);
        if ($group === null) { return $this->notFound(); }
        if ($r = $this->guardModeratorAction($user->user_id, $targetId, $groupId)) { return $r; }

        $status  = (int) ($request->post['status'] ?? UserGroupXrefMapper::STATUS_APPROVED);
        $allowed = [
            UserGroupXrefMapper::STATUS_SUSPENDED,
            UserGroupXrefMapper::STATUS_UNAPPROVED,
            UserGroupXrefMapper::STATUS_APPROVED,
        ];
        if (!in_array($status, $allowed, strict: true)) { return $this->forbidden(); }

        $this->memberships->setMembership($targetId, $groupId, $status);
        $this->logAction($user->user_id, 'set_member_status', $group, $targetId);

        return $this->redirect('/groups/' . $groupId . '/moderate');
    }

    /** POST /groups/{group_id}/moderate/{user_id}/remove — reject a pending request, or remove a member. */
    public function removeMember(Request $request): Response
    {
        if ($r = $this->requireLogin($request)) { return $r; }
        if ($r = $this->checkCsrf($request)) { return $r; }
        $user = Auth::user();

        $groupId  = (int) ($request->tokens['group_id'] ?? 0);
        $targetId = (int) ($request->tokens['user_id']  ?? 0);
        $group    = $this->groups->load($groupId);
        if ($group === null) { return $this->notFound(); }
        if ($r = $this->guardModeratorAction($user->user_id, $targetId, $groupId)) { return $r; }

        $this->memberships->removeMembership($targetId, $groupId);
        $this->logAction($user->user_id, 'remove_member', $group, $targetId);

        return $this->redirect('/groups/' . $groupId . '/moderate');
    }

    /**
     * Shared authorization for the two member-action endpoints: the actor
     * must be a Moderator of this group, and the target must exist and not
     * themselves already hold Moderator status (moderators can only be
     * managed by a site admin, via Admin\GroupController).
     */
    private function guardModeratorAction(int $actorId, int $targetId, int $groupId): ?Response
    {
        if (!$this->memberships->isModerator($actorId, $groupId)) {
            return $this->forbidden();
        }
        $target = $this->memberships->findByUserAndGroup($targetId, $groupId);
        if ($target === null || $target->status === UserGroupXrefMapper::STATUS_MODERATOR) {
            return $this->forbidden();
        }
        return null;
    }

    /** @return array{0: array, 1: array, 2: array} [pending, approved, suspended], each a list of {membership, user}. */
    private function loadRoster(int $groupId): array
    {
        $rows     = $this->memberships->findByGroup($groupId) ?? [];
        $usersMap = $this->users->findByIds(array_map(fn($m) => $m->user_id, $rows));

        $pending = $approved = $suspended = [];
        foreach ($rows as $row) {
            if ($row->status === UserGroupXrefMapper::STATUS_MODERATOR) {
                continue; // moderators aren't managed from this panel
            }
            $entry = ['membership' => $row, 'user' => $usersMap[$row->user_id] ?? null];
            match ($row->status) {
                UserGroupXrefMapper::STATUS_UNAPPROVED => $pending[]   = $entry,
                UserGroupXrefMapper::STATUS_APPROVED   => $approved[]  = $entry,
                UserGroupXrefMapper::STATUS_SUSPENDED  => $suspended[] = $entry,
                default => null,
            };
        }
        return [$pending, $approved, $suspended];
    }

    private function logAction(int $actorId, string $action, Group $group, int $targetUserId): void
    {
        $target = $this->users->load($targetUserId);
        $this->modLog->record(
            userId:     $actorId,
            action:     $action,
            objectType: 'group',
            objectId:   $group->group_id,
            forumId:    0,
            details:    $target?->username ?? ('#' . $targetUserId),
        );
    }
}

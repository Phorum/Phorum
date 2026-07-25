<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Http\Controllers\GroupController;
use Phorum\Http\Request;
use Phorum\Mapper\GroupMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mapper\UserGroupXrefMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\Group;
use Phorum\Model\UserGroupXref;
use Phorum\Tests\Http\ControllerTestCase;
use Twig\Environment;

class GroupControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): GroupController
    {
        return new GroupController(
            config:      $this->makeConfig(),
            twig:        $deps['twig']        ?? $this->makeTwig(),
            groups:      $deps['groups']      ?? $this->createMock(GroupMapper::class),
            memberships: $deps['memberships'] ?? $this->createMock(UserGroupXrefMapper::class),
            users:       $deps['users']       ?? $this->createMock(UserMapper::class),
            modLog:      $deps['modLog']      ?? $this->createMock(ModLogMapper::class),
        );
    }

    private function makeGroup(int $id = 1, array $override = []): Group
    {
        $group           = new Group();
        $group->group_id = $id;
        $group->name     = 'Photography Club';
        foreach ($override as $k => $v) {
            $group->$k = $v;
        }
        return $group;
    }

    private function makeMembership(int $userId, int $groupId, int $status): UserGroupXref
    {
        $m             = new UserGroupXref();
        $m->user_id    = $userId;
        $m->group_id   = $groupId;
        $m->status     = $status;
        return $m;
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function testIndexRedirectsWhenNotLoggedIn(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request());
        $this->assertSame(302, $response->status);
    }

    public function testIndexSplitsMyGroupsAndJoinableGroups(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('find')->willReturn([
            $this->makeGroup(1, ['name' => 'Closed Not Joined', 'open' => 0]),
            $this->makeGroup(2, ['name' => 'Open Not Joined', 'open' => 1]),
            $this->makeGroup(3, ['name' => 'Already A Member', 'open' => 1]),
        ]);

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('findByUser')->with(5)->willReturn([
            $this->makeMembership(5, 3, UserGroupXrefMapper::STATUS_APPROVED),
        ]);

        $twig = $this->createMock(Environment::class);
        $twig->method('getLoader')->willReturn($this->createMock(\Twig\Loader\LoaderInterface::class));
        $twig->expects($this->once())->method('render')->with(
            'user/groups.html.twig',
            $this->callback(function (array $ctx): bool {
                $joinableIds = array_map(fn($g) => $g->group_id, $ctx['joinable']);
                $myGroupIds  = array_map(fn($e) => $e['group']->group_id, $ctx['my_groups']);
                return $joinableIds === [2] && $myGroupIds === [3];
            })
        )->willReturn('<html>ok</html>');

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships, 'twig' => $twig]);
        $response = $ctrl->index(new Request());
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // join
    // -------------------------------------------------------------------------

    public function testJoinRedirectsWhenNotLoggedIn(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->join($this->makePostRequest(tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    public function testJoinReturns403WithBadCsrf(): void
    {
        Auth::setUser($this->makeUser(5));

        $ctrl     = $this->makeController();
        $response = $ctrl->join(new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
            tokens: ['group_id' => '1'],
        ));
        $this->assertSame(403, $response->status);
    }

    public function testJoinReturns404WhenGroupNotFound(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['groups' => $groups]);
        $response = $ctrl->join($this->makePostRequest(tokens: ['group_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testJoinForbiddenWhenGroupNotOpen(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1, ['open' => 0]));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->expects($this->never())->method('setMembership');

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships]);
        $response = $ctrl->join($this->makePostRequest(tokens: ['group_id' => '1']));
        $this->assertSame(403, $response->status);
    }

    public function testJoinCreatesUnapprovedMembershipWhenOpenAndNotAlreadyMember(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1, ['open' => 1]));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('findByUserAndGroup')->willReturn(null);
        $memberships->expects($this->once())->method('setMembership')
            ->with(5, 1, UserGroupXrefMapper::STATUS_UNAPPROVED);

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships]);
        $response = $ctrl->join($this->makePostRequest(tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/groups', $response->headers['Location']);
    }

    public function testJoinDoesNothingWhenAlreadyAMember(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1, ['open' => 1]));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('findByUserAndGroup')->willReturn(
            $this->makeMembership(5, 1, UserGroupXrefMapper::STATUS_APPROVED)
        );
        $memberships->expects($this->never())->method('setMembership');

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships]);
        $response = $ctrl->join($this->makePostRequest(tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // leave
    // -------------------------------------------------------------------------

    public function testLeaveRedirectsWhenNotLoggedIn(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->leave($this->makePostRequest(tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    public function testLeaveRemovesApprovedMembership(): void
    {
        Auth::setUser($this->makeUser(5));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('findByUserAndGroup')->willReturn(
            $this->makeMembership(5, 1, UserGroupXrefMapper::STATUS_APPROVED)
        );
        $memberships->expects($this->once())->method('removeMembership')->with(5, 1);

        $ctrl     = $this->makeController(['memberships' => $memberships]);
        $response = $ctrl->leave($this->makePostRequest(tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/groups', $response->headers['Location']);
    }

    public function testLeaveDoesNothingWhenSuspended(): void
    {
        Auth::setUser($this->makeUser(5));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('findByUserAndGroup')->willReturn(
            $this->makeMembership(5, 1, UserGroupXrefMapper::STATUS_SUSPENDED)
        );
        $memberships->expects($this->never())->method('removeMembership');

        $ctrl     = $this->makeController(['memberships' => $memberships]);
        $response = $ctrl->leave($this->makePostRequest(tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    public function testLeaveDoesNothingWhenNotAMember(): void
    {
        Auth::setUser($this->makeUser(5));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('findByUserAndGroup')->willReturn(null);
        $memberships->expects($this->never())->method('removeMembership');

        $ctrl     = $this->makeController(['memberships' => $memberships]);
        $response = $ctrl->leave($this->makePostRequest(tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // moderate
    // -------------------------------------------------------------------------

    public function testModerateRedirectsWhenNotLoggedIn(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->moderate(new Request(tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    public function testModerateReturns404WhenGroupNotFound(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['groups' => $groups]);
        $response = $ctrl->moderate(new Request(tokens: ['group_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testModerateForbiddenWhenNotAModerator(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('isModerator')->willReturn(false);

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships]);
        $response = $ctrl->moderate(new Request(tokens: ['group_id' => '1']));
        $this->assertSame(403, $response->status);
    }

    public function testModerateSplitsRosterAndExcludesOtherModerators(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('isModerator')->willReturn(true);
        $memberships->method('findByGroup')->willReturn([
            $this->makeMembership(5, 1, UserGroupXrefMapper::STATUS_MODERATOR), // the acting moderator themself
            $this->makeMembership(6, 1, UserGroupXrefMapper::STATUS_MODERATOR), // another moderator
            $this->makeMembership(7, 1, UserGroupXrefMapper::STATUS_UNAPPROVED),
            $this->makeMembership(8, 1, UserGroupXrefMapper::STATUS_APPROVED),
            $this->makeMembership(9, 1, UserGroupXrefMapper::STATUS_SUSPENDED),
        ]);

        $users = $this->createMock(UserMapper::class);
        $users->method('findByIds')->willReturn([]);

        $twig = $this->createMock(Environment::class);
        $twig->method('getLoader')->willReturn($this->createMock(\Twig\Loader\LoaderInterface::class));
        $twig->expects($this->once())->method('render')->with(
            'user/group_moderate.html.twig',
            $this->callback(function (array $ctx): bool {
                $idsOf = fn($entries) => array_map(fn($e) => $e['membership']->user_id, $entries);
                return $idsOf($ctx['pending'])   === [7]
                    && $idsOf($ctx['approved'])  === [8]
                    && $idsOf($ctx['suspended']) === [9];
            })
        )->willReturn('<html>ok</html>');

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships, 'users' => $users, 'twig' => $twig]);
        $response = $ctrl->moderate(new Request(tokens: ['group_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // setMemberStatus
    // -------------------------------------------------------------------------

    public function testSetMemberStatusForbiddenWhenNotModerator(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('isModerator')->willReturn(false);
        $memberships->expects($this->never())->method('setMembership');

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships]);
        $response = $ctrl->setMemberStatus($this->makePostRequest(
            ['status' => '1'],
            tokens: ['group_id' => '1', 'user_id' => '7'],
        ));
        $this->assertSame(403, $response->status);
    }

    public function testSetMemberStatusForbiddenWhenTargetIsModerator(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('isModerator')->willReturn(true);
        $memberships->method('findByUserAndGroup')->willReturn(
            $this->makeMembership(6, 1, UserGroupXrefMapper::STATUS_MODERATOR)
        );
        $memberships->expects($this->never())->method('setMembership');

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships]);
        $response = $ctrl->setMemberStatus($this->makePostRequest(
            ['status' => '-1'],
            tokens: ['group_id' => '1', 'user_id' => '6'],
        ));
        $this->assertSame(403, $response->status);
    }

    public function testSetMemberStatusForbiddenWhenAttemptingToGrantModerator(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('isModerator')->willReturn(true);
        $memberships->method('findByUserAndGroup')->willReturn(
            $this->makeMembership(7, 1, UserGroupXrefMapper::STATUS_UNAPPROVED)
        );
        $memberships->expects($this->never())->method('setMembership');

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships]);
        $response = $ctrl->setMemberStatus($this->makePostRequest(
            ['status' => (string) UserGroupXrefMapper::STATUS_MODERATOR],
            tokens: ['group_id' => '1', 'user_id' => '7'],
        ));
        $this->assertSame(403, $response->status);
    }

    public function testSetMemberStatusApprovesAndRedirects(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('isModerator')->willReturn(true);
        $memberships->method('findByUserAndGroup')->willReturn(
            $this->makeMembership(7, 1, UserGroupXrefMapper::STATUS_UNAPPROVED)
        );
        $memberships->expects($this->once())->method('setMembership')
            ->with(7, 1, UserGroupXrefMapper::STATUS_APPROVED);

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(5, 'set_member_status', 'group', 1, 0, $this->anything());

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships, 'modLog' => $modLog]);
        $response = $ctrl->setMemberStatus($this->makePostRequest(
            ['status' => '1'],
            tokens: ['group_id' => '1', 'user_id' => '7'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertSame('/groups/1/moderate', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // removeMember
    // -------------------------------------------------------------------------

    public function testRemoveMemberForbiddenWhenNotModerator(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('isModerator')->willReturn(false);
        $memberships->expects($this->never())->method('removeMembership');

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships]);
        $response = $ctrl->removeMember($this->makePostRequest(tokens: ['group_id' => '1', 'user_id' => '7']));
        $this->assertSame(403, $response->status);
    }

    public function testRemoveMemberForbiddenWhenTargetIsModerator(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('isModerator')->willReturn(true);
        $memberships->method('findByUserAndGroup')->willReturn(
            $this->makeMembership(6, 1, UserGroupXrefMapper::STATUS_MODERATOR)
        );
        $memberships->expects($this->never())->method('removeMembership');

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships]);
        $response = $ctrl->removeMember($this->makePostRequest(tokens: ['group_id' => '1', 'user_id' => '6']));
        $this->assertSame(403, $response->status);
    }

    public function testRemoveMemberRemovesAndRedirects(): void
    {
        Auth::setUser($this->makeUser(5));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('isModerator')->willReturn(true);
        $memberships->method('findByUserAndGroup')->willReturn(
            $this->makeMembership(7, 1, UserGroupXrefMapper::STATUS_UNAPPROVED)
        );
        $memberships->expects($this->once())->method('removeMembership')->with(7, 1);

        $modLog = $this->createMock(ModLogMapper::class);
        $modLog->expects($this->once())->method('record')
            ->with(5, 'remove_member', 'group', 1, 0, $this->anything());

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships, 'modLog' => $modLog]);
        $response = $ctrl->removeMember($this->makePostRequest(tokens: ['group_id' => '1', 'user_id' => '7']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/groups/1/moderate', $response->headers['Location']);
    }
}

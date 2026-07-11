<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Http\Controllers\Admin\GroupController;
use Phorum\Http\Request;
use Phorum\Mapper\ForumGroupXrefMapper;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\GroupMapper;
use Phorum\Mapper\ModLogMapper;
use Phorum\Mapper\UserGroupXrefMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\Group;
use Phorum\Model\UserGroupXref;
use Phorum\Tests\Http\ControllerTestCase;

class AdminGroupControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): GroupController
    {
        return new GroupController(
            config:      $this->makeConfig(),
            twig:        $this->makeTwig(),
            groups:      $deps['groups']      ?? $this->createMock(GroupMapper::class),
            memberships: $deps['memberships'] ?? $this->createMock(UserGroupXrefMapper::class),
            forumGrants: $deps['forumGrants'] ?? $this->createMock(ForumGroupXrefMapper::class),
            users:       $deps['users']       ?? $this->createMock(UserMapper::class),
            forums:      $deps['forums']      ?? $this->createMock(ForumMapper::class),
            modLog:      $deps['modLog']      ?? $this->createMock(ModLogMapper::class),
        );
    }

    private function makeGroup(int $id = 1, array $override = []): Group
    {
        $group           = new Group();
        $group->group_id = $id;
        $group->name     = 'Moderators';
        foreach ($override as $k => $v) {
            $group->$k = $v;
        }
        return $group;
    }

    // -------------------------------------------------------------------------
    // Auth guards
    // -------------------------------------------------------------------------

    public function testIndexRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    public function testCreateRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->create(new Request());
        $this->assertSame(302, $response->status);
    }

    public function testEditRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->edit(new Request(tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // index / create / edit / delete
    // -------------------------------------------------------------------------

    public function testIndexReturns200WhenAdmin(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('find')->willReturn([]);

        $ctrl     = $this->makeController(['groups' => $groups]);
        $response = $ctrl->index(new Request());
        $this->assertSame(200, $response->status);
    }

    public function testCreatePostValidationErrorForEmptyName(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl     = $this->makeController();
        $response = $ctrl->create($this->makePostRequest(['name' => '']));
        $this->assertSame(200, $response->status);
    }

    public function testCreatePostSuccessRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->expects($this->once())->method('save')->willReturnCallback(function ($g) {
            $g->group_id = 7;
            return $g;
        });

        $ctrl     = $this->makeController(['groups' => $groups]);
        $response = $ctrl->create($this->makePostRequest(['name' => 'Moderators']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/groups/7/edit', $response->headers['Location']);
    }

    public function testEditReturns404WhenGroupNotFound(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['groups' => $groups]);
        $response = $ctrl->edit(new Request(tokens: ['group_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testEditGetReturns200(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('findByGroup')->willReturn([]);

        $forumGrants = $this->createMock(ForumGroupXrefMapper::class);
        $forumGrants->method('findByGroup')->willReturn([]);

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([]);

        $ctrl     = $this->makeController([
            'groups'      => $groups,
            'memberships' => $memberships,
            'forumGrants' => $forumGrants,
            'forums'      => $forums,
        ]);
        $response = $ctrl->edit($this->makeGetRequest(tokens: ['group_id' => '1']));
        $this->assertSame(200, $response->status);
    }

    public function testDeleteReturns404WhenGroupNotFound(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn(null);

        $ctrl     = $this->makeController(['groups' => $groups]);
        $response = $ctrl->delete(new Request(tokens: ['group_id' => '99']));
        $this->assertSame(404, $response->status);
    }

    public function testDeletePostCascadesAndRedirects(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));
        $groups->expects($this->once())->method('delete')->with(1);

        $member = new UserGroupXref();
        $member->user_group_xref_id = 5;
        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->method('findByGroup')->willReturn([$member]);
        $memberships->expects($this->once())->method('delete')->with(5);

        $forumGrants = $this->createMock(ForumGroupXrefMapper::class);
        $forumGrants->method('findByGroup')->willReturn([]);

        $ctrl     = $this->makeController([
            'groups'      => $groups,
            'memberships' => $memberships,
            'forumGrants' => $forumGrants,
        ]);
        $response = $ctrl->delete($this->makePostRequest(tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/groups', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // Members
    // -------------------------------------------------------------------------

    public function testAddMemberFindsUserAndSetsMembership(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $target = $this->makeUser(5);
        $users  = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->with('bob')->willReturn($target);

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->expects($this->once())->method('setMembership')->with(5, 1, 1);

        $ctrl     = $this->makeController(['groups' => $groups, 'users' => $users, 'memberships' => $memberships]);
        $response = $ctrl->addMember($this->makePostRequest(['username' => 'bob', 'status' => '1'], tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/groups/1/edit', $response->headers['Location']);
    }

    public function testAddMemberDoesNothingWhenUserNotFound(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $users = $this->createMock(UserMapper::class);
        $users->method('findByUsername')->willReturn(null);

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->expects($this->never())->method('setMembership');

        $ctrl     = $this->makeController(['groups' => $groups, 'users' => $users, 'memberships' => $memberships]);
        $response = $ctrl->addMember($this->makePostRequest(['username' => 'ghost'], tokens: ['group_id' => '1']));
        $this->assertSame(302, $response->status);
    }

    public function testAddMemberReturns404WhenNotPost(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $ctrl     = $this->makeController(['groups' => $groups]);
        $response = $ctrl->addMember($this->makeGetRequest(tokens: ['group_id' => '1']));
        $this->assertSame(404, $response->status);
    }

    public function testRemoveMemberCallsRemoveMembership(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->expects($this->once())->method('removeMembership')->with(5, 1);

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships]);
        $response = $ctrl->removeMember($this->makePostRequest(tokens: ['group_id' => '1', 'user_id' => '5']));
        $this->assertSame(302, $response->status);
    }

    public function testSetMemberStatusUpdatesStatus(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $memberships = $this->createMock(UserGroupXrefMapper::class);
        $memberships->expects($this->once())->method('setMembership')->with(5, 1, 2);

        $ctrl     = $this->makeController(['groups' => $groups, 'memberships' => $memberships]);
        $response = $ctrl->setMemberStatus($this->makePostRequest(['status' => '2'], tokens: ['group_id' => '1', 'user_id' => '5']));
        $this->assertSame(302, $response->status);
    }

    // -------------------------------------------------------------------------
    // Forum permissions
    // -------------------------------------------------------------------------

    public function testSavePermissionsSetsCheckedForumsAndRemovesUnchecked(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $groups = $this->createMock(GroupMapper::class);
        $groups->method('load')->willReturn($this->makeGroup(1));

        $forums = $this->createMock(ForumMapper::class);
        $forums->method('find')->willReturn([$this->makeForum(1), $this->makeForum(2)]);

        $forumGrants = $this->createMock(ForumGroupXrefMapper::class);
        $forumGrants->expects($this->once())->method('setPermission')->with(1, 1, 3);
        $forumGrants->expects($this->once())->method('removePermission')->with(2, 1);

        $ctrl     = $this->makeController(['groups' => $groups, 'forums' => $forums, 'forumGrants' => $forumGrants]);
        $response = $ctrl->savePermissions($this->makePostRequest(
            ['perms' => [1 => ['1', '2']]],
            tokens: ['group_id' => '1'],
        ));
        $this->assertSame(302, $response->status);
    }
}

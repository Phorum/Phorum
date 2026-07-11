<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Mapper\UserPermissionMapper;
use Phorum\Model\Forum;
use Phorum\Model\User;
use Phorum\Service\PermissionService;
use PHPUnit\Framework\TestCase;

class PermissionServiceTest extends TestCase
{
    private function makeForum(int $pubPerms = 0, int $regPerms = 0): Forum
    {
        $f            = new Forum();
        $f->forum_id  = 1;
        $f->pub_perms = $pubPerms;
        $f->reg_perms = $regPerms;
        return $f;
    }

    private function makeUser(bool $admin = false, bool $active = true): User
    {
        $u         = new User();
        $u->user_id = 42;
        $u->admin  = $admin ? 1 : 0;
        $u->active = $active ? 1 : 0;
        return $u;
    }

    private function makeService(?int $direct = null, int $group = 0): PermissionService
    {
        $mapper = $this->createMock(UserPermissionMapper::class);
        $mapper->method('getDirectPermission')->willReturn($direct);
        $mapper->method('getGroupPermission')->willReturn($group);
        return new PermissionService($mapper);
    }

    // -------------------------------------------------------------------------
    // Anonymous (null user) uses pub_perms
    // -------------------------------------------------------------------------

    public function testAnonymousUsesPublicPerms(): void
    {
        $svc   = $this->makeService();
        $forum = $this->makeForum(pubPerms: PermissionService::ALLOW_READ);
        $this->assertTrue($svc->canRead($forum, null));
    }

    public function testAnonymousCannotReadWhenPubPermsZero(): void
    {
        $svc   = $this->makeService();
        $forum = $this->makeForum(pubPerms: 0);
        $this->assertFalse($svc->canRead($forum, null));
    }

    // -------------------------------------------------------------------------
    // Admin gets full access regardless of forum perms
    // -------------------------------------------------------------------------

    public function testAdminCanReadAnyForum(): void
    {
        $svc   = $this->makeService();
        $forum = $this->makeForum(pubPerms: 0, regPerms: 0);
        $this->assertTrue($svc->canRead($forum, $this->makeUser(admin: true)));
    }

    public function testAdminCanModerate(): void
    {
        $svc   = $this->makeService();
        $forum = $this->makeForum();
        $this->assertTrue($svc->canModerate($forum, $this->makeUser(admin: true)));
    }

    // -------------------------------------------------------------------------
    // Inactive user gets no access
    // -------------------------------------------------------------------------

    public function testInactiveUserCannotRead(): void
    {
        $svc   = $this->makeService(direct: PermissionService::ALLOW_READ);
        $forum = $this->makeForum(pubPerms: PermissionService::ALLOW_READ, regPerms: PermissionService::ALLOW_READ);
        $this->assertFalse($svc->canRead($forum, $this->makeUser(active: false)));
    }

    // -------------------------------------------------------------------------
    // Direct permission overrides everything else for registered users
    // -------------------------------------------------------------------------

    public function testDirectPermissionIsUsedOverRegPerms(): void
    {
        $svc   = $this->makeService(direct: PermissionService::ALLOW_READ);
        $forum = $this->makeForum(regPerms: 0);
        $this->assertTrue($svc->canRead($forum, $this->makeUser()));
    }

    public function testDirectPermissionZeroBlocksAccess(): void
    {
        $svc   = $this->makeService(direct: 0);
        $forum = $this->makeForum(regPerms: PermissionService::ALLOW_READ);
        $this->assertFalse($svc->canRead($forum, $this->makeUser()));
    }

    // -------------------------------------------------------------------------
    // Group permission when no direct entry
    // -------------------------------------------------------------------------

    public function testGroupPermissionIsUsedWhenNoDirectEntry(): void
    {
        $svc   = $this->makeService(direct: null, group: PermissionService::ALLOW_READ | PermissionService::ALLOW_REPLY);
        $forum = $this->makeForum(regPerms: 0);
        $this->assertTrue($svc->canRead($forum, $this->makeUser()));
        $this->assertTrue($svc->canReply($forum, $this->makeUser()));
    }

    public function testGroupPermissionZeroFallsThroughToRegPerms(): void
    {
        $svc   = $this->makeService(direct: null, group: 0);
        $forum = $this->makeForum(regPerms: PermissionService::ALLOW_READ);
        $this->assertTrue($svc->canRead($forum, $this->makeUser()));
    }

    // -------------------------------------------------------------------------
    // Forum default (reg_perms) for registered users with no direct/group
    // -------------------------------------------------------------------------

    public function testRegPermsUsedAsFallback(): void
    {
        $svc   = $this->makeService(direct: null, group: 0);
        $forum = $this->makeForum(regPerms: PermissionService::ALLOW_READ | PermissionService::ALLOW_REPLY);
        $user  = $this->makeUser();
        $this->assertTrue($svc->canRead($forum, $user));
        $this->assertTrue($svc->canReply($forum, $user));
        $this->assertFalse($svc->canNewThread($forum, $user));
    }

    // -------------------------------------------------------------------------
    // canPost() — true if REPLY or NEW_TOPIC bit is set
    // -------------------------------------------------------------------------

    public function testCanPostTrueWhenReplyAllowed(): void
    {
        $svc   = $this->makeService(direct: PermissionService::ALLOW_REPLY);
        $forum = $this->makeForum();
        $this->assertTrue($svc->canPost($forum, $this->makeUser()));
    }

    public function testCanPostTrueWhenNewTopicAllowed(): void
    {
        $svc   = $this->makeService(direct: PermissionService::ALLOW_NEW_TOPIC);
        $forum = $this->makeForum();
        $this->assertTrue($svc->canPost($forum, $this->makeUser()));
    }

    public function testCanPostFalseWhenNeitherAllowed(): void
    {
        $svc   = $this->makeService(direct: PermissionService::ALLOW_READ);
        $forum = $this->makeForum();
        $this->assertFalse($svc->canPost($forum, $this->makeUser()));
    }
}

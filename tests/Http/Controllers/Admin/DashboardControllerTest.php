<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Http\Controllers\Admin\DashboardController;
use Phorum\Http\Request;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\SiteStatusService;
use Phorum\Tests\Http\ControllerTestCase;

class DashboardControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): DashboardController
    {
        return new DashboardController(
            config:     $this->makeConfig(),
            twig:       $this->makeTwig(),
            users:      $deps['users']      ?? $this->createMock(UserMapper::class),
            messages:   $deps['messages']   ?? $this->createMock(MessageMapper::class),
            siteStatus: $deps['siteStatus'] ?? $this->createMock(SiteStatusService::class),
        );
    }

    public function testIndexRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // setStatus
    // -------------------------------------------------------------------------

    public function testSetStatusRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->setStatus($this->makePostRequest(['status' => 'read-only']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    public function testSetStatusReturns403WithBadCsrf(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl    = $this->makeController();
        $badPost = new Request(
            post:   ['csrf_token' => 'bad', 'status' => 'read-only'],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $response = $ctrl->setStatus($badPost);
        $this->assertSame(403, $response->status);
    }

    public function testSetStatusPersistsAndRedirectsToDashboard(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $siteStatus = $this->createMock(SiteStatusService::class);
        $siteStatus->expects($this->once())->method('set')->with('admin-only');

        $ctrl     = $this->makeController(['siteStatus' => $siteStatus]);
        $response = $ctrl->setStatus($this->makePostRequest(['status' => 'admin-only']));
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin', $response->headers['Location']);
    }
}

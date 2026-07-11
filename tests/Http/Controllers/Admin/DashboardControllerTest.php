<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Http\Controllers\Admin\DashboardController;
use Phorum\Http\Request;
use Phorum\Mapper\MessageMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Tests\Http\ControllerTestCase;

class DashboardControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): DashboardController
    {
        return new DashboardController(
            config:   $this->makeConfig(),
            twig:     $this->makeTwig(),
            users:    $deps['users']    ?? $this->createMock(UserMapper::class),
            messages: $deps['messages'] ?? $this->createMock(MessageMapper::class),
        );
    }

    public function testIndexRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }
}

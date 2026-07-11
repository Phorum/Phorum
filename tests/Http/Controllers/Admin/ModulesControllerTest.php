<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Http\Controllers\Admin\ModulesController;
use Phorum\Http\Request;
use Phorum\Mapper\SettingMapper;
use Phorum\Tests\Http\ControllerTestCase;

class ModulesControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): ModulesController
    {
        return new ModulesController(
            config:   $this->makeConfig(),
            twig:     $this->makeTwig(),
            settings: $deps['settings'] ?? $this->createMock(SettingMapper::class),
        );
    }

    public function testIndexRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    public function testIndexGetReturns200WhenAdmin(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn([]);

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testIndexPostEnablesModuleAndReturns200(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(['bbcode' => 0]);
        $settings->expects($this->once())->method('saveSetting')->with(
            'mods',
            $this->callback(fn($v) => isset($v['bbcode']) && $v['bbcode'] === 1)
        );

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            'mod'    => 'bbcode',
            'action' => 'enable',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testIndexPostDisablesModuleAndReturns200(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(['bbcode' => 1]);
        $settings->expects($this->once())->method('saveSetting')->with(
            'mods',
            $this->callback(fn($v) => isset($v['bbcode']) && $v['bbcode'] === 0)
        );

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            'mod'    => 'bbcode',
            'action' => 'disable',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testIndexPostIgnoresInvalidAction(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn([]);
        $settings->expects($this->never())->method('saveSetting');

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            'mod'    => 'bbcode',
            'action' => 'hack',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testIndexPostReturns403WithBadCsrf(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn([]);

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index(new Request(
            post:   ['csrf_token' => 'bad', 'mod' => 'bbcode', 'action' => 'enable'],
            server: ['REQUEST_METHOD' => 'POST'],
        ));
        $this->assertSame(403, $response->status);
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Http\Controllers\Admin\SettingsController;
use Phorum\Http\Request;
use Phorum\Mapper\SettingMapper;
use Phorum\Tests\Http\ControllerTestCase;

class SettingsControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): SettingsController
    {
        return new SettingsController(
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
        $settings->method('getAll')->willReturn([]);

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testIndexPostSavesAndReturns200(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getAll')->willReturn([]);
        $settings->expects($this->once())->method('saveAll');

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            'site_name' => 'My Forum',
            'base_url'  => 'http://example.com',
            'mail_host' => '',
            'mail_port' => '25',
            'mail_from' => '',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testIndexPostReturns403WithBadCsrf(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getAll')->willReturn([]);

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index(new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
        ));
        $this->assertSame(403, $response->status);
    }
}

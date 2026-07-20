<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Http\Request;
use Phorum\Mapper\SettingMapper;
use Phorum\Mod\Cdn\Admin\CdnController;
use Phorum\Tests\Http\ControllerTestCase;

class AdminCdnControllerTest extends ControllerTestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 4) . '/mods/cdn/Admin/CdnController.php';
    }

    private function makeController(array $deps = []): CdnController
    {
        return new CdnController(
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

        $ctrl     = $this->makeController();
        $response = $ctrl->index($this->makeGetRequest());
        $this->assertSame(200, $response->status);
    }

    public function testIndexPostSavesAndReturns200(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(null);
        $settings->expects($this->once())->method('saveSetting')->with('cdn_base_url', 'https://cdn.example.test');

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            'cdn_base_url' => 'https://cdn.example.test',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testIndexPostSavesEmptyStringToClearSetting(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn('https://cdn.example.test');
        $settings->expects($this->once())->method('saveSetting')->with('cdn_base_url', '');

        $ctrl = $this->makeController(['settings' => $settings]);
        $ctrl->index($this->makePostRequest(['cdn_base_url' => '']));
    }

    public function testIndexPostReturns403WithBadCsrf(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
        ));
        $this->assertSame(403, $response->status);
    }
}

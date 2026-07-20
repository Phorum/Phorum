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

    public function testIndexDefaultsFileUploadsToEnabledWhenNotStored(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->method('getLoader')->willReturn($this->createMock(\Twig\Loader\LoaderInterface::class));
        $twig->expects($this->once())->method('render')->with(
            'admin/settings.html.twig',
            $this->callback(fn(array $data) => ($data['stored']['file_uploads'] ?? null) === true),
        )->willReturn('<html>ok</html>');

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getAll')->willReturn([]);

        $ctrl = new SettingsController(config: $this->makeConfig(), twig: $twig, settings: $settings);
        $ctrl->index($this->makeGetRequest());
    }

    public function testIndexPostSavesFileUploadsToggle(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $saved = [];
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getAll')->willReturn([]);
        $settings->method('saveAll')->willReturnCallback(function (array $toSave) use (&$saved) {
            $saved = $toSave;
        });

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            'site_name'     => 'My Forum',
            'base_url'      => 'http://example.com',
            'mail_host'     => '',
            'mail_port'     => '25',
            'mail_from'     => '',
            'file_uploads'  => '1',
        ]));

        $this->assertSame(200, $response->status);
        $this->assertTrue($saved['file_uploads']);
    }

    public function testIndexPostOmittedFileUploadsSavesFalse(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $saved = [];
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getAll')->willReturn([]);
        $settings->method('saveAll')->willReturnCallback(function (array $toSave) use (&$saved) {
            $saved = $toSave;
        });

        $ctrl = $this->makeController(['settings' => $settings]);
        $ctrl->index($this->makePostRequest([
            'site_name' => 'My Forum',
            'base_url'  => 'http://example.com',
            'mail_host' => '',
            'mail_port' => '25',
            'mail_from' => '',
            // file_uploads checkbox omitted entirely, as a browser would when unchecked
        ]));

        $this->assertFalse($saved['file_uploads']);
    }

    public function testIndexDefaultsRequireModApprovalToDisabledWhenNotStored(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $twig = $this->createMock(\Twig\Environment::class);
        $twig->method('getLoader')->willReturn($this->createMock(\Twig\Loader\LoaderInterface::class));
        $twig->expects($this->once())->method('render')->with(
            'admin/settings.html.twig',
            $this->callback(fn(array $data) => ($data['stored']['require_mod_approval'] ?? null) === false),
        )->willReturn('<html>ok</html>');

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getAll')->willReturn([]);

        $ctrl = new SettingsController(config: $this->makeConfig(), twig: $twig, settings: $settings);
        $ctrl->index($this->makeGetRequest());
    }

    public function testIndexPostSavesRequireModApprovalToggle(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $saved    = [];
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getAll')->willReturn([]);
        $settings->method('saveAll')->willReturnCallback(function (array $toSave) use (&$saved) {
            $saved = $toSave;
        });

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            'site_name'            => 'My Forum',
            'base_url'             => 'http://example.com',
            'mail_host'            => '',
            'mail_port'            => '25',
            'mail_from'            => '',
            'require_mod_approval' => '1',
        ]));

        $this->assertSame(200, $response->status);
        $this->assertTrue($saved['require_mod_approval']);
    }

    public function testIndexPostOmittedRequireModApprovalSavesFalse(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $saved    = [];
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getAll')->willReturn([]);
        $settings->method('saveAll')->willReturnCallback(function (array $toSave) use (&$saved) {
            $saved = $toSave;
        });

        $ctrl = $this->makeController(['settings' => $settings]);
        $ctrl->index($this->makePostRequest([
            'site_name' => 'My Forum',
            'base_url'  => 'http://example.com',
            'mail_host' => '',
            'mail_port' => '25',
            'mail_from' => '',
            // require_mod_approval checkbox omitted entirely, as a browser would when unchecked
        ]));

        $this->assertFalse($saved['require_mod_approval']);
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

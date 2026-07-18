<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Core\SchemaInstaller;
use Phorum\Http\Controllers\Admin\ModulesController;
use Phorum\Http\Request;
use Phorum\Mapper\SettingMapper;
use Phorum\Tests\Http\ControllerTestCase;
use Twig\Environment;

class ModulesControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): ModulesController
    {
        return new ModulesController(
            config:   $this->makeConfig(),
            twig:     $this->makeTwig(),
            settings: $deps['settings'] ?? $this->createMock(SettingMapper::class),
            schema:   $deps['schema']   ?? $this->createMock(SchemaInstaller::class),
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

    /**
     * Enabling a module must apply its schema (mods/{name}/mysql.sql, if any)
     * right away — the version-triggered self-heal in App::selfHealSchema()
     * only re-syncs when the core version has moved, so a module enabled on
     * an already-up-to-date site would otherwise never get its tables.
     */
    public function testIndexPostEnableAppliesSchema(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn([]);

        $schema = $this->createMock(SchemaInstaller::class);
        $schema->expects($this->once())->method('apply');

        $ctrl = $this->makeController(['settings' => $settings, 'schema' => $schema]);
        $ctrl->index($this->makePostRequest(['mod' => 'webhooks', 'action' => 'enable']));
    }

    public function testIndexPostEnableSurfacesErrorAndDoesNotSaveWhenSchemaApplyFails(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn([]);
        $settings->expects($this->never())->method('saveSetting');

        $schema = $this->createMock(SchemaInstaller::class);
        $schema->method('apply')->willThrowException(new \RuntimeException('DB is on fire'));

        $ctrl     = $this->makeController(['settings' => $settings, 'schema' => $schema]);
        $response = $ctrl->index($this->makePostRequest(['mod' => 'webhooks', 'action' => 'enable']));
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

    public function testIndexPostDisableDoesNotApplySchema(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(['bbcode' => 1]);

        $schema = $this->createMock(SchemaInstaller::class);
        $schema->expects($this->never())->method('apply');

        $ctrl = $this->makeController(['settings' => $settings, 'schema' => $schema]);
        $ctrl->index($this->makePostRequest(['mod' => 'bbcode', 'action' => 'disable']));
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

    /**
     * mods/webhooks/info.txt declares "configure: /admin/webhooks" — this
     * is what templates/admin/modules.html.twig uses to decide whether to
     * show a Configure button (only ever alongside mod.enabled being true,
     * decided in the template — this test just verifies the data reaches it).
     */
    public function testDiscoveredModulesIncludeConfigureUrlFromInfoTxt(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(['webhooks' => 1]);

        $captured = null;
        $twig = $this->createMock(Environment::class);
        $twig->method('getLoader')->willReturn($this->createMock(\Twig\Loader\LoaderInterface::class));
        $twig->method('render')->willReturnCallback(function ($template, $data) use (&$captured) {
            $captured = $data;
            return '<html>test</html>';
        });

        $ctrl = new ModulesController(config: $this->makeConfig(), twig: $twig, settings: $settings);
        $ctrl->index($this->makeGetRequest());

        $webhooksModule = null;
        foreach ($captured['modules'] as $mod) {
            if ($mod['name'] === 'webhooks') {
                $webhooksModule = $mod;
            }
        }

        $this->assertNotNull($webhooksModule, 'expected the webhooks module to be discovered');
        $this->assertSame('/admin/webhooks', $webhooksModule['info']['configure']);
        $this->assertTrue($webhooksModule['enabled']);
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

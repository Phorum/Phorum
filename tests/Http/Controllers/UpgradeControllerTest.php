<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Core\CsrfGuard;
use Phorum\Core\SchemaInstaller;
use Phorum\Core\SchemaPatcher;
use Phorum\Http\Controllers\UpgradeController;
use Phorum\Http\Request;
use Phorum\Mapper\SettingMapper;
use Phorum\Tests\Http\ControllerTestCase;

class UpgradeControllerTest extends ControllerTestCase
{
    private function makeController(array $deps = []): UpgradeController
    {
        return new UpgradeController(
            config:   $this->makeConfig(),
            twig:     $this->makeTwig(),
            schema:   $deps['schema']   ?? $this->createMock(SchemaInstaller::class),
            patcher:  $deps['patcher']  ?? $this->createMock(SchemaPatcher::class),
            settings: $deps['settings'] ?? $this->createMock(SettingMapper::class),
        );
    }

    public function testIndexReturns200(): void
    {
        $schema = $this->createMock(SchemaInstaller::class);
        $schema->method('pendingTables')->willReturn(['mod_log']);

        $patcher = $this->createMock(SchemaPatcher::class);
        $patcher->method('pendingPatchDescriptions')->willReturn(['add forum/user columns']);

        $ctrl     = $this->makeController(['schema' => $schema, 'patcher' => $patcher]);
        $response = $ctrl->index(new Request());
        $this->assertSame(200, $response->status);
    }

    public function testIndexRedirectsWhenAlreadyInstalled(): void
    {
        $schema = $this->createMock(SchemaInstaller::class);
        $schema->expects($this->never())->method('apply');

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->with('installed')->willReturn('1');

        $ctrl     = $this->makeController(['schema' => $schema, 'settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest());

        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['Location']);
    }

    public function testPostAppliesSchemaAndPatchesAndRedirects(): void
    {
        $schema = $this->createMock(SchemaInstaller::class);
        $schema->expects($this->once())->method('apply');

        $patcher = $this->createMock(SchemaPatcher::class);
        $patcher->expects($this->once())->method('apply');

        $saved    = [];
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('saveSetting')->willReturnCallback(function ($name, $value) use (&$saved) {
            $saved[$name] = $value;
        });

        $ctrl     = $this->makeController(['schema' => $schema, 'patcher' => $patcher, 'settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest());

        $this->assertSame(302, $response->status);
        $this->assertSame('/upgrade/complete', $response->headers['Location']);
        $this->assertArrayHasKey('schema_version', $saved);
        $this->assertSame('1', $saved['installed']);
    }

    public function testPostReturns403WithBadCsrf(): void
    {
        $schema = $this->createMock(SchemaInstaller::class);
        $schema->expects($this->never())->method('apply');

        $patcher = $this->createMock(SchemaPatcher::class);
        $patcher->expects($this->never())->method('apply');

        $ctrl    = $this->makeController(['schema' => $schema, 'patcher' => $patcher]);
        $badPost = new Request(
            post:   ['csrf_token' => 'bad'],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $response = $ctrl->index($badPost);
        $this->assertSame(403, $response->status);
    }

    public function testCompleteReturns200(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->complete(new Request());
        $this->assertSame(200, $response->status);
    }

    // -------------------------------------------------------------------------
    // ?force=1 reentry, for running patches shipped after the last release
    // -------------------------------------------------------------------------

    public function testForceReentryRedirectsToAdminLoginWhenNotAdmin(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->with('installed')->willReturn('1');

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makeGetRequest(['force' => '1']));

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    public function testForceReentryReturns200WhenAdmin(): void
    {
        $this->setAdminUser($this->makeUser(admin: true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->with('installed')->willReturn('1');

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makeGetRequest(['force' => '1']));

        $this->assertSame(200, $response->status);
    }

    public function testForceReentryAppliesPendingPatchesWithoutReSavingInstalled(): void
    {
        $this->setAdminUser($this->makeUser(admin: true));

        $schema = $this->createMock(SchemaInstaller::class);
        $schema->expects($this->once())->method('apply');

        $patcher = $this->createMock(SchemaPatcher::class);
        $patcher->expects($this->once())->method('apply');

        $saved    = [];
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->with('installed')->willReturn('1');
        $settings->method('saveSetting')->willReturnCallback(function ($name, $value) use (&$saved) {
            $saved[$name] = $value;
        });

        $ctrl     = $this->makeController([
            'schema'   => $schema,
            'patcher'  => $patcher,
            'settings' => $settings,
        ]);
        $request  = new Request(
            query:  ['force' => '1'],
            post:   [CsrfGuard::fieldName() => CsrfGuard::token()],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $response = $ctrl->index($request);

        $this->assertSame(302, $response->status);
        $this->assertSame('/upgrade/complete', $response->headers['Location']);
        $this->assertArrayHasKey('schema_version', $saved);
        $this->assertArrayNotHasKey('installed', $saved);
    }
}

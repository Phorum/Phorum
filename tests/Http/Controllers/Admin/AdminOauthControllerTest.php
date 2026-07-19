<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Http\Request;
use Phorum\Mapper\SettingMapper;
use Phorum\Mod\Oauth\Admin\OauthController;
use Phorum\Tests\Http\ControllerTestCase;

class AdminOauthControllerTest extends ControllerTestCase
{
    public static function setUpBeforeClass(): void
    {
        $base = dirname(__DIR__, 4) . '/mods/oauth';
        require_once $base . '/Admin/OauthController.php';
    }

    private function makeController(array $deps = []): OauthController
    {
        return new OauthController(
            config:   $this->makeConfig(),
            twig:     $this->makeTwig(),
            settings: $deps['settings'] ?? $this->createMock(SettingMapper::class),
        );
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function testIndexRedirectsWhenNotAdmin(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request());
        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/login', $response->headers['Location']);
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function testIndexReturns200WhenAdmin(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $ctrl     = $this->makeController();
        $response = $ctrl->index(new Request());
        $this->assertSame(200, $response->status);
    }

    public function testPostRequiresClientIdAndSecretToEnableGoogle(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(null);
        $settings->expects($this->never())->method('saveSetting');

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            'oauth_google_enabled' => '1',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testPostSavesSettingsWhenValid(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(null);
        $settings->expects($this->atLeastOnce())->method('saveSetting');

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            'oauth_google_enabled'        => '1',
            'oauth_google_client_id'      => 'gid',
            'oauth_google_client_secret'  => 'gsecret',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testPostKeepsExistingSecretWhenBlank(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $saved = [];
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturnCallback(
            fn(string $key) => $key === 'oauth_google_client_secret' ? 'existing-secret' : null
        );
        $settings->method('saveSetting')->willReturnCallback(function ($k, $v) use (&$saved) {
            $saved[$k] = $v;
        });

        $ctrl = $this->makeController(['settings' => $settings]);
        $ctrl->index($this->makePostRequest([
            'oauth_google_enabled'       => '1',
            'oauth_google_client_id'     => 'gid',
            'oauth_google_client_secret' => '',
        ]));

        $this->assertArrayNotHasKey('oauth_google_client_secret', $saved);
        $this->assertSame('gid', $saved['oauth_google_client_id']);
    }
}

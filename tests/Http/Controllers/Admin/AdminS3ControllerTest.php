<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers\Admin;

use Phorum\Http\Request;
use Phorum\Mapper\SettingMapper;
use Phorum\Mod\S3Storage\Admin\S3Controller;
use Phorum\Tests\Http\ControllerTestCase;

class AdminS3ControllerTest extends ControllerTestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 4) . '/mods/s3storage/Admin/S3Controller.php';
    }

    private function makeController(array $deps = []): S3Controller
    {
        return new S3Controller(
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
        $settings->expects($this->exactly(5))->method('saveSetting');

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            's3_bucket'     => 'my-bucket',
            's3_region'     => 'us-east-1',
            's3_access_key' => 'AKIA...',
            's3_secret_key' => 'topsecret',
            's3_key_prefix' => 'phorum',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testIndexPostValidationErrorForMissingBucket(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(null);
        $settings->expects($this->never())->method('saveSetting');

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            's3_bucket' => '',
            's3_region' => 'us-east-1',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testIndexPostValidationErrorForMissingRegion(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn(null);
        $settings->expects($this->never())->method('saveSetting');

        $ctrl     = $this->makeController(['settings' => $settings]);
        $response = $ctrl->index($this->makePostRequest([
            's3_bucket' => 'my-bucket',
            's3_region' => '',
        ]));
        $this->assertSame(200, $response->status);
    }

    public function testIndexPostBlankSecretKeepsExistingOne(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $saved = [];
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturnCallback(
            fn(string $name) => $name === 's3_secret_key' ? 'existing-secret' : null
        );
        $settings->method('saveSetting')->willReturnCallback(function ($name, $value) use (&$saved) {
            $saved[$name] = $value;
        });

        $ctrl = $this->makeController(['settings' => $settings]);
        $ctrl->index($this->makePostRequest([
            's3_bucket'     => 'my-bucket',
            's3_region'     => 'us-east-1',
            's3_access_key' => 'AKIA...',
            's3_secret_key' => '', // blank — should not overwrite
            's3_key_prefix' => '',
        ]));

        $this->assertArrayNotHasKey('s3_secret_key', $saved);
    }

    public function testIndexPostNonBlankSecretOverwritesExistingOne(): void
    {
        $this->setAdminUser($this->makeUser(1, true));

        $saved = [];
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturnCallback(
            fn(string $name) => $name === 's3_secret_key' ? 'existing-secret' : null
        );
        $settings->method('saveSetting')->willReturnCallback(function ($name, $value) use (&$saved) {
            $saved[$name] = $value;
        });

        $ctrl = $this->makeController(['settings' => $settings]);
        $ctrl->index($this->makePostRequest([
            's3_bucket'     => 'my-bucket',
            's3_region'     => 'us-east-1',
            's3_access_key' => 'AKIA...',
            's3_secret_key' => 'brand-new-secret',
            's3_key_prefix' => '',
        ]));

        $this->assertSame('brand-new-secret', $saved['s3_secret_key']);
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

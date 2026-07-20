<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Mapper\SettingMapper;
use Phorum\Mod\Cdn\CdnService;
use PHPUnit\Framework\TestCase;

class CdnServiceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/mods/cdn/CdnService.php';
    }

    private function makeSettings(?string $baseUrl): SettingMapper
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->with('cdn_base_url')->willReturn($baseUrl);
        return $settings;
    }

    public function testUrlForReturnsNullWhenNotConfigured(): void
    {
        $svc = new CdnService($this->makeSettings(null));
        $this->assertNull($svc->urlFor('/file/7/photo.jpg'));
    }

    public function testUrlForReturnsNullWhenConfiguredAsEmptyString(): void
    {
        $svc = new CdnService($this->makeSettings(''));
        $this->assertNull($svc->urlFor('/file/7/photo.jpg'));
    }

    public function testUrlForPrefixesConfiguredBase(): void
    {
        $svc = new CdnService($this->makeSettings('https://cdn.example.test'));
        $this->assertSame('https://cdn.example.test/file/7/photo.jpg', $svc->urlFor('/file/7/photo.jpg'));
    }

    public function testUrlForStripsTrailingSlashFromConfiguredBase(): void
    {
        $svc = new CdnService($this->makeSettings('https://cdn.example.test/'));
        $this->assertSame('https://cdn.example.test/avatar/42', $svc->urlFor('/avatar/42'));
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Mapper\SettingMapper;
use Phorum\Service\SiteStatusService;
use PHPUnit\Framework\TestCase;

class SiteStatusServiceTest extends TestCase
{
    public function testCurrentDefaultsToNormalWhenUnset(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->with('status')->willReturn(null);

        $service = new SiteStatusService($settings);
        $this->assertSame(SiteStatusService::NORMAL, $service->current());
    }

    public function testCurrentReturnsStoredValue(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->with('status')->willReturn(SiteStatusService::READ_ONLY);

        $service = new SiteStatusService($settings);
        $this->assertSame(SiteStatusService::READ_ONLY, $service->current());
    }

    public function testCurrentDefaultsToNormalForUnrecognizedValue(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->with('status')->willReturn('some-garbage-value');

        $service = new SiteStatusService($settings);
        $this->assertSame(SiteStatusService::NORMAL, $service->current());
    }

    public function testCurrentDefaultsToNormalOnMapperException(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willThrowException(new \RuntimeException('no table'));

        $service = new SiteStatusService($settings);
        $this->assertSame(SiteStatusService::NORMAL, $service->current());
    }

    public function testSetPersistsRecognizedValue(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->expects($this->once())->method('saveSetting')->with('status', SiteStatusService::ADMIN_ONLY);

        $service = new SiteStatusService($settings);
        $service->set(SiteStatusService::ADMIN_ONLY);
    }

    public function testSetIgnoresUnrecognizedValue(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->expects($this->never())->method('saveSetting');

        $service = new SiteStatusService($settings);
        $service->set('not-a-real-status');
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\SiteStatus;
use Phorum\Service\SiteStatusService;
use PHPUnit\Framework\TestCase;

class SiteStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        SiteStatus::clear();
    }

    public function testDefaultsToNormalBeforeInitialize(): void
    {
        $this->assertSame(SiteStatusService::NORMAL, SiteStatus::current());
        $this->assertFalse(SiteStatus::isReadOnly());
    }

    public function testInitializeCachesTheServicesCurrentValue(): void
    {
        $service = $this->createMock(SiteStatusService::class);
        $service->method('current')->willReturn(SiteStatusService::READ_ONLY);

        SiteStatus::initialize($service);

        $this->assertSame(SiteStatusService::READ_ONLY, SiteStatus::current());
        $this->assertTrue(SiteStatus::isReadOnly());
    }

    public function testIsReadOnlyIsFalseForOtherStatuses(): void
    {
        $service = $this->createMock(SiteStatusService::class);
        $service->method('current')->willReturn(SiteStatusService::ADMIN_ONLY);

        SiteStatus::initialize($service);

        $this->assertFalse(SiteStatus::isReadOnly());
    }

    public function testClearResetsToNormal(): void
    {
        $service = $this->createMock(SiteStatusService::class);
        $service->method('current')->willReturn(SiteStatusService::DISABLED);
        SiteStatus::initialize($service);

        SiteStatus::clear();

        $this->assertSame(SiteStatusService::NORMAL, SiteStatus::current());
    }
}

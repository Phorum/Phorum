<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\SiteSettings;
use Phorum\Mapper\SettingMapper;
use PHPUnit\Framework\TestCase;

class SiteSettingsTest extends TestCase
{
    protected function tearDown(): void
    {
        SiteSettings::clear();
    }

    public function testDefaultsToPhorumBeforeInitialize(): void
    {
        $this->assertSame('Phorum', SiteSettings::name());
    }

    public function testInitializeUsesDbValueWhenSet(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->with('site_name')->willReturn('My Forum');

        SiteSettings::initialize($settings, 'Configured Default');

        $this->assertSame('My Forum', SiteSettings::name());
    }

    public function testInitializeFallsBackToConfigDefaultWhenDbValueIsNull(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->with('site_name')->willReturn(null);

        SiteSettings::initialize($settings, 'Configured Default');

        $this->assertSame('Configured Default', SiteSettings::name());
    }

    public function testInitializeFallsBackToConfigDefaultWhenDbValueIsEmptyString(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->with('site_name')->willReturn('');

        SiteSettings::initialize($settings, 'Configured Default');

        $this->assertSame('Configured Default', SiteSettings::name());
    }

    public function testClearResetsToPhorum(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturn('My Forum');
        SiteSettings::initialize($settings, 'Configured Default');

        SiteSettings::clear();

        $this->assertSame('Phorum', SiteSettings::name());
    }
}

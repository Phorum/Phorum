<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use PHPUnit\Framework\TestCase;
use Phorum\Core\SchemaInstaller;
use Phorum\Core\SchemaMigrator;
use Phorum\Core\SchemaPatcher;
use Phorum\Core\Version;
use Phorum\Mapper\SettingMapper;

class SchemaMigratorTest extends TestCase
{
    public function testBringUpToDateAppliesInstallerThenPatcherThenRecordsVersion(): void
    {
        $calls = [];

        $installer = $this->createMock(SchemaInstaller::class);
        $installer->method('apply')->willReturnCallback(function () use (&$calls) {
            $calls[] = 'installer';
        });

        $patcher = $this->createMock(SchemaPatcher::class);
        $patcher->method('apply')->willReturnCallback(function () use (&$calls) {
            $calls[] = 'patcher';
        });

        $settings = $this->createMock(SettingMapper::class);
        $settings->expects($this->once())->method('saveSetting')
            ->with('schema_version', Version::CURRENT)
            ->willReturnCallback(function () use (&$calls) {
                $calls[] = 'save_version';
            });

        (new SchemaMigrator($installer, $patcher, $settings))->bringUpToDate();

        $this->assertSame(['installer', 'patcher', 'save_version'], $calls);
    }
}

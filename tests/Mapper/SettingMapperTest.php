<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\SettingMapper;

class SettingMapperTest extends MapperTestCase
{
    private function makeMapper(): SettingMapper
    {
        return new class extends SettingMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    // -------------------------------------------------------------------------
    // saveSetting / getSetting
    // -------------------------------------------------------------------------

    public function testSaveAndGetScalarSetting(): void
    {
        $mapper = $this->makeMapper();
        $mapper->saveSetting('site_name', 'My Forum');
        $this->assertSame('My Forum', $mapper->getSetting('site_name'));
    }

    public function testSaveSettingUpdatesExisting(): void
    {
        $mapper = $this->makeMapper();
        $mapper->saveSetting('version', '1.0');
        $mapper->saveSetting('version', '2.0');
        $this->assertSame('2.0', $mapper->getSetting('version'));
    }

    public function testSaveSerializedSetting(): void
    {
        $mapper = $this->makeMapper();
        $value  = ['key' => 'val', 'num' => 42];
        $mapper->saveSetting('config_array', $value);
        $result = $mapper->getSetting('config_array');
        $this->assertIsArray($result);
        $this->assertSame(42, $result['num']);
    }

    public function testGetSettingReturnsNullForMissing(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->getSetting('nonexistent'));
    }

    // -------------------------------------------------------------------------
    // getAll
    // -------------------------------------------------------------------------

    public function testGetAllReturnsKeyValueMap(): void
    {
        $mapper = $this->makeMapper();
        $mapper->saveSetting('foo', 'bar');
        $mapper->saveSetting('baz', 'qux');

        $all = $mapper->getAll();
        $this->assertSame('bar', $all['foo']);
        $this->assertSame('qux', $all['baz']);
    }

    public function testGetAllReturnsEmptyWhenNoSettings(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->getAll());
    }

    // -------------------------------------------------------------------------
    // saveAll
    // -------------------------------------------------------------------------

    public function testSaveAll(): void
    {
        $mapper = $this->makeMapper();
        $mapper->saveAll(['alpha' => 'a', 'beta' => 'b']);

        $this->assertSame('a', $mapper->getSetting('alpha'));
        $this->assertSame('b', $mapper->getSetting('beta'));
    }
}

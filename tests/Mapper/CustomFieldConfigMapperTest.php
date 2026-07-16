<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\CustomFieldConfigMapper;
use Phorum\Mapper\SettingMapper;
use Phorum\Model\CustomFieldConfig;

class CustomFieldConfigMapperTest extends MapperTestCase
{
    private function makeSettings(): SettingMapper
    {
        return new class extends SettingMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    private function makeMapper(): CustomFieldConfigMapper
    {
        return new CustomFieldConfigMapper($this->makeSettings());
    }

    private function seedConfig(int $id, array $override = []): void
    {
        $settings = $this->makeSettings();
        $fields   = $settings->getSetting('PROFILE_FIELDS') ?? [];
        $fields[$id] = array_merge([
            'name'          => 'bio',
            'length'        => 255,
            'html_disabled' => true,
            'show_in_admin' => false,
            'deleted'       => false,
        ], $override);
        $fields['num_fields'] = max($id, (int) ($fields['num_fields'] ?? 0));
        $settings->saveSetting('PROFILE_FIELDS', $fields);
    }

    // -------------------------------------------------------------------------
    // save / load / delete
    // -------------------------------------------------------------------------

    public function testSaveInsertAndLoad(): void
    {
        $mapper = $this->makeMapper();
        $c = new CustomFieldConfig();
        $c->name = 'website';
        $mapper->save($c);

        $this->assertGreaterThan(0, $c->id);
        $loaded = $mapper->load($c->id);
        $this->assertSame('website', $loaded->name);
    }

    public function testSaveAssignsIncrementingIds(): void
    {
        $mapper = $this->makeMapper();
        $a = new CustomFieldConfig();
        $a->name = 'first';
        $mapper->save($a);

        $b = new CustomFieldConfig();
        $b->name = 'second';
        $mapper->save($b);

        $this->assertSame($a->id + 1, $b->id);
    }

    public function testSaveUpdatesExistingConfig(): void
    {
        $mapper = $this->makeMapper();
        $c = new CustomFieldConfig();
        $c->name = 'bio';
        $mapper->save($c);

        $c->length = 500;
        $mapper->save($c);

        $loaded = $mapper->load($c->id);
        $this->assertSame(500, $loaded->length);
    }

    public function testDeleteRemovesConfig(): void
    {
        $this->seedConfig(1, ['name' => 'bio']);
        $mapper = $this->makeMapper();

        $this->assertTrue($mapper->delete(1));
        $this->assertNull($mapper->load(1));
    }

    public function testDeleteReturnsFalseForMissing(): void
    {
        $mapper = $this->makeMapper();
        $this->assertFalse($mapper->delete(999));
    }

    public function testLoadReturnsNullForMissing(): void
    {
        $mapper = $this->makeMapper();
        $this->assertNull($mapper->load(999));
    }

    // -------------------------------------------------------------------------
    // findAll
    // -------------------------------------------------------------------------

    public function testFindAllReturnsConfigsSortedByName(): void
    {
        $this->seedConfig(1, ['name' => 'zeta']);
        $this->seedConfig(2, ['name' => 'alpha']);

        $mapper  = $this->makeMapper();
        $results = $mapper->findAll();
        $this->assertCount(2, $results);
        $this->assertSame('alpha', $results[0]->name);
        $this->assertSame('zeta', $results[1]->name);
    }

    public function testFindAllExcludesDeleted(): void
    {
        $this->seedConfig(1, ['deleted' => false, 'name' => 'active']);
        $this->seedConfig(2, ['deleted' => true, 'name' => 'deleted_field']);

        $mapper  = $this->makeMapper();
        $results = $mapper->findAll();
        $this->assertCount(1, $results);
        $this->assertSame('active', $results[0]->name);
    }

    public function testFindAllIncludesDeletedWhenRequested(): void
    {
        $this->seedConfig(1, ['deleted' => false, 'name' => 'active']);
        $this->seedConfig(2, ['deleted' => true, 'name' => 'gone']);

        $mapper  = $this->makeMapper();
        $results = $mapper->findAll(includeDeleted: true);
        $this->assertCount(2, $results);
    }

    public function testFindAllReturnsEmptyWhenNoSettingSaved(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->findAll());
    }

    // -------------------------------------------------------------------------
    // findByName
    // -------------------------------------------------------------------------

    public function testFindByNameReturnsConfig(): void
    {
        $this->seedConfig(1, ['name' => 'bio']);
        $mapper = $this->makeMapper();
        $result = $mapper->findByName('bio');
        $this->assertNotNull($result);
        $this->assertSame('bio', $result->name);
    }

    public function testFindByNameReturnsNullForMissing(): void
    {
        $mapper = $this->makeMapper();
        $result = $mapper->findByName('nonexistent');
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Per-instance caching
    // -------------------------------------------------------------------------

    public function testLoadAllOnlyFetchesTheSettingOnceWithinAnInstance(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->expects($this->once())->method('getSetting')->willReturn([
            1 => ['name' => 'bio', 'length' => 255, 'html_disabled' => false, 'show_in_admin' => false, 'deleted' => false],
        ]);

        $mapper = new CustomFieldConfigMapper($settings);
        $mapper->findAll();
        $mapper->findAll();
        $mapper->load(1);
    }

    public function testFindAllUsesUpdatedCacheAfterSaveWithoutExtraQuery(): void
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->expects($this->once())->method('getSetting')->willReturn([]);
        $settings->method('getSettingRow')->willReturn(null);
        $settings->method('compareAndSwap')->willReturn(true);

        $mapper = new CustomFieldConfigMapper($settings);
        $this->assertCount(0, $mapper->findAll());

        $config       = new CustomFieldConfig();
        $config->name = 'bio';
        $mapper->save($config);

        $this->assertCount(1, $mapper->findAll());
    }

    // -------------------------------------------------------------------------
    // Concurrent-edit safety
    // -------------------------------------------------------------------------

    /**
     * If another admin's write lands between our read and our write (their
     * compareAndSwap() succeeds, ours would otherwise be based on stale
     * data), save() must reload the now-current state, re-apply our change
     * against it, and retry — instead of clobbering their change or losing
     * ours. This is the lost-update race the CAS retry in save() exists to
     * prevent: without it, our write would silently overwrite theirs.
     */
    public function testSaveRetriesAndPreservesConcurrentChangeAfterCasFailure(): void
    {
        $this->seedConfig(1, ['name' => 'location', 'length' => 50]);

        $settings = new class extends SettingMapper {
            public int $casAttempts = 0;
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
            public function compareAndSwap(string $name, ?string $expectedRawData, mixed $value): bool
            {
                $this->casAttempts++;
                if ($this->casAttempts === 1) {
                    // A concurrent writer lands a change here first, so our
                    // upcoming compare-and-swap (built from data read before
                    // this) must fail.
                    parent::compareAndSwap($name, $expectedRawData, [
                        1           => ['name' => 'location', 'length' => 50, 'html_disabled' => false, 'show_in_admin' => false, 'deleted' => false],
                        999         => ['name' => 'other', 'length' => 1, 'html_disabled' => false, 'show_in_admin' => false, 'deleted' => false],
                        'num_fields' => 999,
                    ]);
                    return false;
                }
                return parent::compareAndSwap($name, $expectedRawData, $value);
            }
        };

        $mapper = new CustomFieldConfigMapper($settings);
        $config = $mapper->load(1);
        $config->length = 777;
        $mapper->save($config);

        $this->assertSame(2, $settings->casAttempts, 'save() must retry once after the first CAS failure');
        $this->assertSame(777, $mapper->load(1)->length, 'our edit must land after the retry');
        $this->assertNotNull($mapper->load(999), "the concurrent writer's change must survive, not be clobbered");
    }
}

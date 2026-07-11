<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\CustomFieldConfigMapper;
use Phorum\Model\CustomFieldConfig;

class CustomFieldConfigMapperTest extends MapperTestCase
{
    private function makeMapper(): CustomFieldConfigMapper
    {
        return new class extends CustomFieldConfigMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    private function seedConfig(array $override = []): int
    {
        return $this->insert('phorum_custom_fields_config', array_merge([
            'field_type'    => CustomFieldConfig::FIELD_TYPE_USER,
            'name'          => 'bio',
            'length'        => 255,
            'html_disabled' => 1,
            'show_in_admin' => 0,
            'deleted'       => 0,
        ], $override));
    }

    // -------------------------------------------------------------------------
    // save / load / delete via parent
    // -------------------------------------------------------------------------

    public function testSaveInsertAndLoad(): void
    {
        $mapper = $this->makeMapper();
        $c = new CustomFieldConfig();
        $c->field_type = CustomFieldConfig::FIELD_TYPE_USER;
        $c->name       = 'website';
        $mapper->save($c);

        $this->assertGreaterThan(0, $c->id);
        $loaded = $mapper->load($c->id);
        $this->assertSame('website', $loaded->name);
    }

    // -------------------------------------------------------------------------
    // findByFieldType
    // -------------------------------------------------------------------------

    public function testFindByFieldTypeReturnsMatchingConfigs(): void
    {
        $this->seedConfig(['field_type' => CustomFieldConfig::FIELD_TYPE_USER,  'name' => 'bio']);
        $this->seedConfig(['field_type' => CustomFieldConfig::FIELD_TYPE_FORUM, 'name' => 'color']);

        $mapper  = $this->makeMapper();
        $results = $mapper->findByFieldType(CustomFieldConfig::FIELD_TYPE_USER);
        $this->assertCount(1, $results);
        $this->assertSame('bio', $results[0]->name);
    }

    public function testFindByFieldTypeExcludesDeleted(): void
    {
        $this->seedConfig(['deleted' => 0, 'name' => 'active']);
        $this->seedConfig(['deleted' => 1, 'name' => 'deleted_field']);

        $mapper  = $this->makeMapper();
        $results = $mapper->findByFieldType(CustomFieldConfig::FIELD_TYPE_USER);
        $this->assertCount(1, $results);
        $this->assertSame('active', $results[0]->name);
    }

    public function testFindByFieldTypeIncludesDeletedWhenRequested(): void
    {
        $this->seedConfig(['deleted' => 0, 'name' => 'active']);
        $this->seedConfig(['deleted' => 1, 'name' => 'gone']);

        $mapper  = $this->makeMapper();
        $results = $mapper->findByFieldType(CustomFieldConfig::FIELD_TYPE_USER, includeDeleted: true);
        $this->assertCount(2, $results);
    }

    // -------------------------------------------------------------------------
    // findByName
    // -------------------------------------------------------------------------

    public function testFindByNameReturnsConfig(): void
    {
        $this->seedConfig(['field_type' => CustomFieldConfig::FIELD_TYPE_USER, 'name' => 'bio']);
        $mapper = $this->makeMapper();
        $result = $mapper->findByName('bio', CustomFieldConfig::FIELD_TYPE_USER);
        $this->assertNotNull($result);
        $this->assertSame('bio', $result->name);
    }

    public function testFindByNameReturnsNullForMissing(): void
    {
        $mapper = $this->makeMapper();
        $result = $mapper->findByName('nonexistent', CustomFieldConfig::FIELD_TYPE_USER);
        $this->assertNull($result);
    }
}

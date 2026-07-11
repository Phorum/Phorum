<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\CustomFieldMapper;
use Phorum\Model\CustomFieldConfig;

class CustomFieldMapperTest extends MapperTestCase
{
    private function makeMapper(): CustomFieldMapper
    {
        return new class extends CustomFieldMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    private function seedField(int $relationId, int $fieldType, int $configId, string $data): void
    {
        self::$pdo->exec(
            "INSERT INTO phorum_custom_fields (relation_id, field_type, type, data)"
            . " VALUES ({$relationId}, {$fieldType}, {$configId}, '{$data}')"
        );
    }

    // -------------------------------------------------------------------------
    // loadForRelation
    // -------------------------------------------------------------------------

    public function testLoadForRelationReturnsFieldsKeyedByConfigId(): void
    {
        $this->seedField(1, CustomFieldConfig::FIELD_TYPE_USER, 10, 'hello');
        $this->seedField(1, CustomFieldConfig::FIELD_TYPE_USER, 20, 'world');
        $this->seedField(2, CustomFieldConfig::FIELD_TYPE_USER, 10, 'other');

        $mapper = $this->makeMapper();
        $result = $mapper->loadForRelation(1, CustomFieldConfig::FIELD_TYPE_USER);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
        $this->assertSame('hello', $result[10]->data);
        $this->assertSame('world', $result[20]->data);
        $this->assertArrayNotHasKey(10, $mapper->loadForRelation(2, CustomFieldConfig::FIELD_TYPE_FORUM));
    }

    public function testLoadForRelationReturnsEmptyWhenNone(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->loadForRelation(999, CustomFieldConfig::FIELD_TYPE_USER));
    }

    // -------------------------------------------------------------------------
    // loadForRelations
    // -------------------------------------------------------------------------

    public function testLoadForRelationsReturnsNestedResult(): void
    {
        $this->seedField(5, CustomFieldConfig::FIELD_TYPE_FORUM, 1, 'blue');
        $this->seedField(6, CustomFieldConfig::FIELD_TYPE_FORUM, 1, 'red');
        $this->seedField(5, CustomFieldConfig::FIELD_TYPE_FORUM, 2, 'round');

        $mapper = $this->makeMapper();
        $result = $mapper->loadForRelations([5, 6], CustomFieldConfig::FIELD_TYPE_FORUM);
        $this->assertArrayHasKey(5, $result);
        $this->assertArrayHasKey(6, $result);
        $this->assertSame('blue', $result[5][1]->data);
        $this->assertSame('round', $result[5][2]->data);
        $this->assertSame('red',  $result[6][1]->data);
    }

    public function testLoadForRelationsReturnsEmptyForEmptyInput(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->loadForRelations([], CustomFieldConfig::FIELD_TYPE_USER));
    }

    // -------------------------------------------------------------------------
    // deleteForRelation
    // -------------------------------------------------------------------------

    public function testDeleteForRelation(): void
    {
        $this->seedField(10, CustomFieldConfig::FIELD_TYPE_USER, 1, 'x');
        $this->seedField(10, CustomFieldConfig::FIELD_TYPE_USER, 2, 'y');
        $this->seedField(11, CustomFieldConfig::FIELD_TYPE_USER, 1, 'z');

        $mapper = $this->makeMapper();
        $mapper->deleteForRelation(10, CustomFieldConfig::FIELD_TYPE_USER);

        $this->assertSame([], $mapper->loadForRelation(10, CustomFieldConfig::FIELD_TYPE_USER));
        $this->assertCount(1, $mapper->loadForRelation(11, CustomFieldConfig::FIELD_TYPE_USER));
    }

    // -------------------------------------------------------------------------
    // deleteForConfig
    // -------------------------------------------------------------------------

    public function testDeleteForConfig(): void
    {
        $this->seedField(20, CustomFieldConfig::FIELD_TYPE_MESSAGE, 5, 'a');
        $this->seedField(21, CustomFieldConfig::FIELD_TYPE_MESSAGE, 5, 'b');
        $this->seedField(20, CustomFieldConfig::FIELD_TYPE_MESSAGE, 6, 'c');

        $mapper = $this->makeMapper();
        $mapper->deleteForConfig(5, CustomFieldConfig::FIELD_TYPE_MESSAGE);

        // config 5 removed from all relations; config 6 on relation 20 untouched
        $remaining20 = $mapper->loadForRelation(20, CustomFieldConfig::FIELD_TYPE_MESSAGE);
        $this->assertCount(1, $remaining20);
        $this->assertArrayHasKey(6, $remaining20);

        // relation 21 has nothing left
        $this->assertSame([], $mapper->loadForRelation(21, CustomFieldConfig::FIELD_TYPE_MESSAGE));
    }
}

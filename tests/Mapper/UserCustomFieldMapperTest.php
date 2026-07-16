<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\UserCustomFieldMapper;

class UserCustomFieldMapperTest extends MapperTestCase
{
    private function makeMapper(): UserCustomFieldMapper
    {
        return new class extends UserCustomFieldMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    private function seedField(int $userId, int $configId, string $data): void
    {
        self::$pdo->exec(
            "INSERT INTO phorum_user_custom_fields (user_id, type, data)"
            . " VALUES ({$userId}, {$configId}, '{$data}')"
        );
    }

    // -------------------------------------------------------------------------
    // loadForUser
    // -------------------------------------------------------------------------

    public function testLoadForUserReturnsFieldsKeyedByConfigId(): void
    {
        $this->seedField(1, 10, 'hello');
        $this->seedField(1, 20, 'world');
        $this->seedField(2, 10, 'other');

        $mapper = $this->makeMapper();
        $result = $mapper->loadForUser(1);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
        $this->assertSame('hello', $result[10]->data);
        $this->assertSame('world', $result[20]->data);
    }

    public function testLoadForUserReturnsEmptyWhenNone(): void
    {
        $mapper = $this->makeMapper();
        $this->assertSame([], $mapper->loadForUser(999));
    }

    // -------------------------------------------------------------------------
    // saveValue
    // -------------------------------------------------------------------------

    public function testSaveValueInsertsThenUpdates(): void
    {
        $mapper = $this->makeMapper();
        $mapper->saveValue(1, 10, 'first');
        $this->assertSame('first', $mapper->loadForUser(1)[10]->data);

        $mapper->saveValue(1, 10, 'second');
        $this->assertSame('second', $mapper->loadForUser(1)[10]->data);
    }

    /**
     * If the INSERT fails with a constraint-violation-shaped error that
     * *isn't* actually a duplicate of this row (e.g. a genuine FK
     * violation), the fallback UPDATE matches zero rows too — saveValue()
     * must surface the original error instead of silently doing nothing.
     */
    public function testSaveValueRethrowsWhenFallbackUpdateMatchesNoRow(): void
    {
        // PDOException::getCode() is final but its underlying $code property
        // is untyped and normally holds the driver's SQLSTATE string (e.g.
        // '23000') rather than an int — set it the same way here.
        $fakeException = new \PDOException('constraint violation');
        $codeProp       = new \ReflectionProperty($fakeException, 'code');
        $codeProp->setValue($fakeException, '23000');

        $mapper = new class($fakeException) extends UserCustomFieldMapper {
            public function __construct(private \Exception $fakeException)
            {
            }

            protected function crud(): CRUD
            {
                $real      = MapperTestCase::$crud;
                $exception = $this->fakeException;
                return new class($real, $exception) extends CRUD {
                    public function __construct(private CRUD $real, private \Exception $exception)
                    {
                    }

                    public function run(string $query, array $params = []): \DealNews\DB\PDOStatement
                    {
                        if (str_starts_with(trim($query), 'INSERT')) {
                            throw $this->exception;
                        }
                        return $this->real->run($query, $params);
                    }
                };
            }
        };

        $this->expectExceptionMessage('constraint violation');
        $mapper->saveValue(999, 999, 'value');
    }

    // -------------------------------------------------------------------------
    // deleteForUser
    // -------------------------------------------------------------------------

    public function testDeleteForUser(): void
    {
        $this->seedField(10, 1, 'x');
        $this->seedField(10, 2, 'y');
        $this->seedField(11, 1, 'z');

        $mapper = $this->makeMapper();
        $mapper->deleteForUser(10);

        $this->assertSame([], $mapper->loadForUser(10));
        $this->assertCount(1, $mapper->loadForUser(11));
    }

    // -------------------------------------------------------------------------
    // deleteForConfig
    // -------------------------------------------------------------------------

    public function testDeleteForConfig(): void
    {
        $this->seedField(20, 5, 'a');
        $this->seedField(21, 5, 'b');
        $this->seedField(20, 6, 'c');

        $mapper = $this->makeMapper();
        $mapper->deleteForConfig(5);

        // config 5 removed from all users; config 6 on user 20 untouched
        $remaining20 = $mapper->loadForUser(20);
        $this->assertCount(1, $remaining20);
        $this->assertArrayHasKey(6, $remaining20);

        // user 21 has nothing left
        $this->assertSame([], $mapper->loadForUser(21));
    }
}

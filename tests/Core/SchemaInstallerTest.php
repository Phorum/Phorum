<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use DealNews\DB\CRUD;
use DealNews\DB\PDO as DbPDO;
use PHPUnit\Framework\TestCase;
use Phorum\Core\SchemaInstaller;

/**
 * db/mysql.sql itself is MySQL-only syntax (ENGINE=InnoDB, etc.) that SQLite
 * can't parse, so these tests exercise SchemaInstaller's actual logic
 * (prefix substitution, statement splitting, existence probing) against a
 * small SQLite-safe fixture instead, via the schemaFile/crud() injection
 * points — the same override pattern MapperTestCase-based tests use.
 */
class SchemaInstallerTest extends TestCase
{
    private DbPDO $pdo;
    private CRUD  $crud;
    private string $fixture;

    protected function setUp(): void
    {
        $this->pdo = new DbPDO('sqlite::memory:');
        $this->pdo->connect();
        $this->crud = new CRUD($this->pdo);

        $this->fixture = tempnam(sys_get_temp_dir(), 'schema_fixture_') . '.sql';
        file_put_contents($this->fixture, <<<SQL
            -- an existing table (simulates one Phorum 6 already created)
            CREATE TABLE IF NOT EXISTS {PREFIX}_existing_table (
                id INTEGER NOT NULL
            );

            -- a new table Phorum 10 needs
            CREATE TABLE IF NOT EXISTS {PREFIX}_new_table (
                id INTEGER NOT NULL
            );
            SQL);

        // Pre-create the "existing" table, as if it came from Phorum 6.
        $this->pdo->exec('CREATE TABLE phorum_existing_table (id INTEGER NOT NULL)');
    }

    protected function tearDown(): void
    {
        @unlink($this->fixture);
    }

    private function makeInstaller(): SchemaInstaller
    {
        $crud = $this->crud;
        return new class($this->fixture, $crud) extends SchemaInstaller {
            private readonly CRUD $testCrud;

            public function __construct(?string $schemaFile, CRUD $testCrud)
            {
                parent::__construct($schemaFile);
                $this->testCrud = $testCrud;
            }

            protected function crud(): CRUD
            {
                return $this->testCrud;
            }
        };
    }

    public function testPendingTablesReturnsOnlyMissingTables(): void
    {
        $installer = $this->makeInstaller();
        $this->assertSame(['new_table'], $installer->pendingTables());
    }

    public function testApplyCreatesMissingTable(): void
    {
        $installer = $this->makeInstaller();
        $installer->apply();

        $rows = $this->crud->runFetch(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'phorum_new_table'",
            []
        );
        $this->assertNotEmpty($rows);
    }

    public function testApplyDoesNotErrorOnAlreadyExistingTable(): void
    {
        $installer = $this->makeInstaller();
        // Should not throw despite phorum_existing_table already being there.
        $installer->apply();
        $this->assertTrue(true);
    }

    public function testApplyIsIdempotent(): void
    {
        $installer = $this->makeInstaller();
        $installer->apply();
        $installer->apply();

        $this->assertSame([], $installer->pendingTables());
    }

    public function testPendingTablesEmptyAfterApply(): void
    {
        $installer = $this->makeInstaller();
        $installer->apply();
        $this->assertSame([], $installer->pendingTables());
    }
}

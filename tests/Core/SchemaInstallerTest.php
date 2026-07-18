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

    /** @var string[] Temp mods directories created by makeModsDirWithSchema(), removed in tearDown(). */
    private array $tempModsDirs = [];

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
        foreach ($this->tempModsDirs as $dir) {
            $this->removeDirRecursive($dir);
        }
        $this->tempModsDirs = [];
    }

    private function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirRecursive($path) : unlink($path);
        }
        rmdir($dir);
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

    /**
     * pendingTables() must check existence with one query for all tables,
     * not one existence probe per table (an N+1 that grows with every
     * table the schema adds).
     */
    public function testPendingTablesQueriesExistenceOnce(): void
    {
        $counting = new class($this->pdo) extends CRUD {
            public int $queryCount = 0;
            public function runFetch(string $query, array $params = []): array
            {
                $this->queryCount++;
                return parent::runFetch($query, $params);
            }
        };

        $installer = new class($this->fixture, $counting) extends SchemaInstaller {
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

        $installer->pendingTables();
        $this->assertSame(1, $counting->queryCount);
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

    // -------------------------------------------------------------------------
    // Module schema files (mods/{name}/{same basename as the core schema file})
    // -------------------------------------------------------------------------

    private function makeInstallerWithMods(string $modsDir): SchemaInstaller
    {
        $crud = $this->crud;
        return new class($this->fixture, $modsDir, $crud) extends SchemaInstaller {
            private readonly CRUD $testCrud;

            public function __construct(?string $schemaFile, string $modsDir, CRUD $testCrud)
            {
                parent::__construct($schemaFile, $modsDir);
                $this->testCrud = $testCrud;
            }

            protected function crud(): CRUD
            {
                return $this->testCrud;
            }
        };
    }

    /** Builds a temp mods/{name}/{basename-of-$this->fixture} tree and returns its mods dir. */
    private function makeModsDirWithSchema(string $moduleName, string $sql): string
    {
        $modsDir = sys_get_temp_dir() . '/schema_mods_' . uniqid();
        $dir     = $modsDir . '/' . $moduleName;
        mkdir($dir, recursive: true);
        file_put_contents($dir . '/' . basename($this->fixture), $sql);
        $this->tempModsDirs[] = $modsDir;
        return $modsDir;
    }

    public function testApplyCreatesTablesFromModuleSchemaFile(): void
    {
        $modsDir = $this->makeModsDirWithSchema('webhooks', <<<SQL
            CREATE TABLE IF NOT EXISTS {PREFIX}_module_table (
                id INTEGER NOT NULL
            );
            SQL);

        $installer = $this->makeInstallerWithMods($modsDir);
        $installer->apply();

        $rows = $this->crud->runFetch(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'phorum_module_table'",
            []
        );
        $this->assertNotEmpty($rows);
    }

    public function testPendingTablesIncludesModuleTables(): void
    {
        $modsDir = $this->makeModsDirWithSchema('webhooks', <<<SQL
            CREATE TABLE IF NOT EXISTS {PREFIX}_module_table (
                id INTEGER NOT NULL
            );
            SQL);

        $installer = $this->makeInstallerWithMods($modsDir);
        $this->assertContains('module_table', $installer->pendingTables());
    }

    public function testModuleSchemaFileIsPickedUpRegardlessOfModuleEnabledState(): void
    {
        // No "enabled modules" concept is consulted here at all — presence
        // of mods/{name}/{basename} on disk is the only signal, matching
        // ModulesController's own directory-based discovery.
        $modsDir = $this->makeModsDirWithSchema('some_disabled_module', <<<SQL
            CREATE TABLE IF NOT EXISTS {PREFIX}_module_table (
                id INTEGER NOT NULL
            );
            SQL);

        $installer = $this->makeInstallerWithMods($modsDir);
        $installer->apply();

        $rows = $this->crud->runFetch(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'phorum_module_table'",
            []
        );
        $this->assertNotEmpty($rows);
    }

    public function testIgnoresModuleDirectoriesWithoutAMatchingSchemaFile(): void
    {
        $modsDir = sys_get_temp_dir() . '/schema_mods_' . uniqid();
        mkdir($modsDir . '/some_module', recursive: true);
        $this->tempModsDirs[] = $modsDir;
        // No file matching basename($this->fixture) placed inside — nothing to pick up.

        $installer = $this->makeInstallerWithMods($modsDir);
        $this->assertSame(['new_table'], $installer->pendingTables());
    }

    public function testNonExistentModsDirDoesNotError(): void
    {
        $installer = $this->makeInstallerWithMods(sys_get_temp_dir() . '/does_not_exist_' . uniqid());
        $this->assertSame(['new_table'], $installer->pendingTables());
    }
}

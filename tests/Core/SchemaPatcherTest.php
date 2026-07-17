<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use DealNews\DB\CRUD;
use DealNews\DB\PDO as DbPDO;
use PHPUnit\Framework\TestCase;
use Phorum\Core\SchemaPatcher;
use Phorum\Mapper\SettingMapper;

class SchemaPatcherTest extends TestCase
{
    private DbPDO  $pdo;
    private CRUD   $crud;
    private string $patchDir;

    protected function setUp(): void
    {
        $this->pdo = new DbPDO('sqlite::memory:');
        $this->pdo->connect();
        $this->crud = new CRUD($this->pdo);

        $this->pdo->exec("CREATE TABLE phorum_settings (name TEXT PRIMARY KEY, type TEXT NOT NULL DEFAULT 'V', data TEXT NOT NULL DEFAULT '')");
        $this->pdo->exec('CREATE TABLE phorum_widgets (id INTEGER PRIMARY KEY)');

        $this->patchDir = tempnam(sys_get_temp_dir(), 'schema_patches_');
        unlink($this->patchDir);
        mkdir($this->patchDir);

        file_put_contents(
            $this->patchDir . '/0001_add_color.sql',
            "ALTER TABLE {PREFIX}_widgets ADD COLUMN color TEXT NOT NULL DEFAULT '';\n"
        );
        file_put_contents(
            $this->patchDir . '/0002_add_size.sql',
            "ALTER TABLE {PREFIX}_widgets ADD COLUMN size TEXT NOT NULL DEFAULT '';\n"
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->patchDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->patchDir);
    }

    private function makeSettings(): SettingMapper
    {
        $crud = $this->crud;
        return new class($crud) extends SettingMapper {
            private readonly CRUD $testCrud;

            public function __construct(CRUD $testCrud)
            {
                $this->testCrud = $testCrud;
            }

            protected function crud(): CRUD
            {
                return $this->testCrud;
            }
        };
    }

    private function makePatcher(?SettingMapper $settings = null): SchemaPatcher
    {
        $crud = $this->crud;
        return new class($this->patchDir, $settings ?? $this->makeSettings(), $crud) extends SchemaPatcher {
            private readonly CRUD $testCrud;

            public function __construct(string $patchDir, SettingMapper $settings, CRUD $testCrud)
            {
                parent::__construct($patchDir, $settings);
                $this->testCrud = $testCrud;
            }

            protected function crud(): CRUD
            {
                return $this->testCrud;
            }
        };
    }

    private function widgetHasColumn(string $column): bool
    {
        $rows = $this->crud->runFetch('PRAGMA table_info(phorum_widgets)', []);
        foreach ($rows ?: [] as $row) {
            if ($row['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // apply()
    // -------------------------------------------------------------------------

    public function testApplyRunsAllPendingPatchesInOrderAndRecordsLevel(): void
    {
        $settings = $this->makeSettings();
        $this->makePatcher($settings)->apply();

        $this->assertTrue($this->widgetHasColumn('color'));
        $this->assertTrue($this->widgetHasColumn('size'));
        $this->assertSame('2', (string) $settings->getSetting('schema_patch_level'));
    }

    public function testApplyIsIdempotentOnSecondCall(): void
    {
        $settings = $this->makeSettings();
        $patcher  = $this->makePatcher($settings);

        $patcher->apply();
        $patcher->apply(); // should not throw, nothing left to run

        $this->assertSame('2', (string) $settings->getSetting('schema_patch_level'));
    }

    public function testApplyWithNoPatchesDoesNothing(): void
    {
        foreach (glob($this->patchDir . '/*') ?: [] as $file) {
            unlink($file);
        }

        $settings = $this->makeSettings();
        $this->makePatcher($settings)->apply();

        $this->assertNull($settings->getSetting('schema_patch_level'));
        $this->assertFalse($this->widgetHasColumn('color'));
    }

    // -------------------------------------------------------------------------
    // apply() tolerating a partially-applied patch
    // -------------------------------------------------------------------------

    /**
     * Wraps the real CRUD, but throws a caller-supplied \PDOException instead
     * of running any statement containing $matchSubstring — simulates a
     * MySQL "already exists" DDL error without needing a real MySQL server
     * (SQLite's own duplicate-column error doesn't carry the same
     * errorInfo[1] driver code SchemaPatcher checks for).
     */
    private function makeFaultInjectingCrud(CRUD $real, string $matchSubstring, \PDOException $exception): CRUD
    {
        return new class($real, $matchSubstring, $exception) extends CRUD {
            public int $matchedCalls = 0;

            public function __construct(
                private readonly CRUD $real,
                private readonly string $matchSubstring,
                private readonly \PDOException $exception,
            ) {
            }

            public function run(string $query, array $params = []): \DealNews\DB\PDOStatement
            {
                if (str_contains($query, $this->matchSubstring)) {
                    $this->matchedCalls++;
                    throw $this->exception;
                }
                return $this->real->run($query, $params);
            }
        };
    }

    private function makeDuplicateColumnException(): \PDOException
    {
        $e             = new \PDOException("Duplicate column name 'color'");
        $e->errorInfo  = ['42S21', 1060, "Duplicate column name 'color'"];
        return $e;
    }

    public function testApplySkipsAlreadyAppliedColumnAndStillRecordsLevel(): void
    {
        $settings = $this->makeSettings();
        $crud     = $this->makeFaultInjectingCrud($this->crud, 'ADD COLUMN color', $this->makeDuplicateColumnException());

        $patcher = new class($this->patchDir, $settings, $crud) extends SchemaPatcher {
            private readonly CRUD $testCrud;
            public function __construct(string $patchDir, SettingMapper $settings, CRUD $testCrud)
            {
                parent::__construct($patchDir, $settings);
                $this->testCrud = $testCrud;
            }
            protected function crud(): CRUD
            {
                return $this->testCrud;
            }
        };

        $patcher->apply();

        // color's ADD COLUMN was "already applied" (skipped); size's still ran.
        $this->assertFalse($this->widgetHasColumn('color'));
        $this->assertTrue($this->widgetHasColumn('size'));
        $this->assertSame('2', (string) $settings->getSetting('schema_patch_level'));
        $this->assertSame(1, $crud->matchedCalls);
    }

    public function testApplyRethrowsErrorsThatArentAlreadyAppliedDdl(): void
    {
        $settings  = $this->makeSettings();
        $otherError = new \PDOException('syntax error near foo');
        $otherError->errorInfo = ['42000', 1064, 'syntax error near foo'];
        $crud      = $this->makeFaultInjectingCrud($this->crud, 'ADD COLUMN color', $otherError);

        $patcher = new class($this->patchDir, $settings, $crud) extends SchemaPatcher {
            private readonly CRUD $testCrud;
            public function __construct(string $patchDir, SettingMapper $settings, CRUD $testCrud)
            {
                parent::__construct($patchDir, $settings);
                $this->testCrud = $testCrud;
            }
            protected function crud(): CRUD
            {
                return $this->testCrud;
            }
        };

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('syntax error near foo');
        $patcher->apply();
    }

    // -------------------------------------------------------------------------
    // markAllApplied()
    // -------------------------------------------------------------------------

    public function testMarkAllAppliedRecordsHighestNumberWithoutRunningPatches(): void
    {
        $settings = $this->makeSettings();
        $this->makePatcher($settings)->markAllApplied();

        $this->assertSame('2', (string) $settings->getSetting('schema_patch_level'));
        $this->assertFalse($this->widgetHasColumn('color'));
        $this->assertFalse($this->widgetHasColumn('size'));
    }

    public function testApplyDoesNothingAfterMarkAllApplied(): void
    {
        $settings = $this->makeSettings();
        $patcher  = $this->makePatcher($settings);

        $patcher->markAllApplied();
        $patcher->apply(); // nothing pending — should not throw

        $this->assertFalse($this->widgetHasColumn('color'));
    }

    // -------------------------------------------------------------------------
    // pendingPatchDescriptions()
    // -------------------------------------------------------------------------

    public function testPendingPatchDescriptionsBeforeApply(): void
    {
        $patcher = $this->makePatcher();
        $this->assertSame(['add color', 'add size'], $patcher->pendingPatchDescriptions());
    }

    public function testPendingPatchDescriptionsEmptyAfterApply(): void
    {
        $settings = $this->makeSettings();
        $patcher  = $this->makePatcher($settings);
        $patcher->apply();

        $this->assertSame([], $patcher->pendingPatchDescriptions());
    }
}

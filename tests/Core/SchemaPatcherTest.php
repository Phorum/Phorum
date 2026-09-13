<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use DealNews\DB\CRUD;
use DealNews\DB\PDO as DbPDO;
use PHPUnit\Framework\TestCase;
use Phorum\Core\SchemaPatcher;
use Phorum\Mapper\SettingMapper;
use Phorum\Tests\Support\SpyLogger;

/**
 * Covers SchemaPatcher: applying pending patch files in order, recording the
 * schema_patch_level setting, tolerating already-applied DDL, and rethrowing
 * every other database error.
 *
 * Runs against a real in-memory SQLite database rather than a mocked CRUD, so
 * the ALTER statements genuinely execute. Two things are substituted in:
 * anonymous subclasses override the protected crud() seam to hand the patcher
 * that SQLite handle (and, for the failure cases, a fault-injecting wrapper
 * around it), and a spy logger is injected everywhere so skipped-statement
 * notices are captured and asserted on instead of being written to stderr by
 * the default ErrorLogLogger.
 */
class SchemaPatcherTest extends TestCase
{
    private DbPDO  $pdo;
    private CRUD   $crud;
    private string $patchDir;

    /**
     * Builds a fresh in-memory SQLite database with the settings and widgets
     * tables, plus a temporary patch directory holding two ALTER patches
     * (0001 adds a color column, 0002 adds size).
     */
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

    /** Removes the temporary patch directory created for the test. */
    protected function tearDown(): void
    {
        foreach (glob($this->patchDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->patchDir);
    }

    /** Builds a SettingMapper backed by the test's in-memory SQLite database. */
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

    /**
     * Builds a SchemaPatcher pointed at the test's patch directory and SQLite
     * database, with a spy logger so nothing leaks to stderr.
     */
    private function makePatcher(?SettingMapper $settings = null, ?SpyLogger $logger = null): SchemaPatcher
    {
        return $this->makePatcherWithCrud($this->crud, $settings ?? $this->makeSettings(), $logger);
    }

    /**
     * Builds a SchemaPatcher using an explicit CRUD, so a failure case can pass
     * a fault-injecting wrapper. Defaults to a fresh spy logger when none given.
     */
    private function makePatcherWithCrud(CRUD $crud, SettingMapper $settings, ?SpyLogger $logger = null): SchemaPatcher
    {
        return new class($this->patchDir, $settings, $logger ?? new SpyLogger(), $crud) extends SchemaPatcher {
            private readonly CRUD $testCrud;

            public function __construct(string $patchDir, SettingMapper $settings, SpyLogger $logger, CRUD $testCrud)
            {
                parent::__construct($patchDir, $settings, $logger);
                $this->testCrud = $testCrud;
            }

            protected function crud(): CRUD
            {
                return $this->testCrud;
            }
        };
    }

    /** True when phorum_widgets currently has the named column. */
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

    /**
     * Both pending patches run in ascending order and the highest applied
     * patch number is recorded in schema_patch_level.
     */
    public function testApplyRunsAllPendingPatchesInOrderAndRecordsLevel(): void
    {
        $settings = $this->makeSettings();
        $this->makePatcher($settings)->apply();

        $this->assertTrue($this->widgetHasColumn('color'));
        $this->assertTrue($this->widgetHasColumn('size'));
        $this->assertSame('2', (string) $settings->getSetting('schema_patch_level'));
    }

    /**
     * A second apply() finds nothing pending, so it neither throws nor moves
     * schema_patch_level.
     */
    public function testApplyIsIdempotentOnSecondCall(): void
    {
        $settings = $this->makeSettings();
        $patcher  = $this->makePatcher($settings);

        $patcher->apply();
        $patcher->apply(); // should not throw, nothing left to run

        $this->assertSame('2', (string) $settings->getSetting('schema_patch_level'));
    }

    /**
     * With an empty patch directory, apply() runs no SQL and never writes the
     * schema_patch_level setting.
     */
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

    /**
     * A \PDOException carrying MySQL's ER_DUP_FIELDNAME (1060) driver code —
     * the "column already exists" error SchemaPatcher treats as already done.
     */
    private function makeDuplicateColumnException(): \PDOException
    {
        $e             = new \PDOException("Duplicate column name 'color'");
        $e->errorInfo  = ['42S21', 1060, "Duplicate column name 'color'"];
        return $e;
    }

    /**
     * An "already exists" DDL error is swallowed so later patches still run,
     * schema_patch_level still advances, and the skip is logged once with the
     * patch number and the driver message.
     */
    public function testApplySkipsAlreadyAppliedColumnAndStillRecordsLevel(): void
    {
        $settings = $this->makeSettings();
        $logger   = new SpyLogger();
        $crud     = $this->makeFaultInjectingCrud($this->crud, 'ADD COLUMN color', $this->makeDuplicateColumnException());

        $this->makePatcherWithCrud($crud, $settings, $logger)->apply();

        // color's ADD COLUMN was "already applied" (skipped); size's still ran.
        $this->assertFalse($this->widgetHasColumn('color'));
        $this->assertTrue($this->widgetHasColumn('size'));
        $this->assertSame('2', (string) $settings->getSetting('schema_patch_level'));
        $this->assertSame(1, $crud->matchedCalls);

        // The skip is not silent: it is reported once, naming the patch and cause.
        $record = $logger->onlyRecord();
        $this->assertSame('warning', $record['level']);
        $this->assertStringContainsString('already applied, skipping', $record['message']);
        $this->assertSame(1, $record['context']['patch']);
        $this->assertSame("Duplicate column name 'color'", $record['context']['error']);
    }

    /**
     * A database error that is not an "already exists" DDL error (here a
     * syntax error) propagates instead of being swallowed.
     */
    public function testApplyRethrowsErrorsThatArentAlreadyAppliedDdl(): void
    {
        $settings  = $this->makeSettings();
        $otherError = new \PDOException('syntax error near foo');
        $otherError->errorInfo = ['42000', 1064, 'syntax error near foo'];
        $crud      = $this->makeFaultInjectingCrud($this->crud, 'ADD COLUMN color', $otherError);

        $patcher = $this->makePatcherWithCrud($crud, $settings);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('syntax error near foo');
        $patcher->apply();
    }

    // -------------------------------------------------------------------------
    // markAllApplied()
    // -------------------------------------------------------------------------

    /**
     * markAllApplied() records the highest patch number without executing any
     * patch SQL — the fresh-install path, where the base schema is current.
     */
    public function testMarkAllAppliedRecordsHighestNumberWithoutRunningPatches(): void
    {
        $settings = $this->makeSettings();
        $this->makePatcher($settings)->markAllApplied();

        $this->assertSame('2', (string) $settings->getSetting('schema_patch_level'));
        $this->assertFalse($this->widgetHasColumn('color'));
        $this->assertFalse($this->widgetHasColumn('size'));
    }

    /** After markAllApplied(), apply() finds nothing pending and runs no SQL. */
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

    /** Every unapplied patch is described by its de-numbered, de-underscored filename. */
    public function testPendingPatchDescriptionsBeforeApply(): void
    {
        $patcher = $this->makePatcher();
        $this->assertSame(['add color', 'add size'], $patcher->pendingPatchDescriptions());
    }

    /** Once every patch has been applied, no pending descriptions remain. */
    public function testPendingPatchDescriptionsEmptyAfterApply(): void
    {
        $settings = $this->makeSettings();
        $patcher  = $this->makePatcher($settings);
        $patcher->apply();

        $this->assertSame([], $patcher->pendingPatchDescriptions());
    }
}

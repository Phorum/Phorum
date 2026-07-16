<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use DealNews\DB\PDO as DbPDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for mapper tests.
 *
 * Creates a single in-memory SQLite database per test class and tears down
 * each table's rows between tests so cases are fully isolated.
 */
abstract class MapperTestCase extends TestCase
{
    public static DbPDO $pdo;
    public static CRUD  $crud;

    /** Tables to truncate before each test. Override to limit the scope. */
    protected static array $tables = [
        'phorum_forums',
        'phorum_messages',
        'phorum_settings',
        'phorum_subscribers',
        'phorum_user_permissions',
        'phorum_users',
        'phorum_user_newflags',
        'phorum_groups',
        'phorum_forum_group_xref',
        'phorum_user_group_xref',
        'phorum_files',
        'phorum_banlists',
        'phorum_search',
        'phorum_user_custom_fields',
        'phorum_pm_messages',
        'phorum_pm_folders',
        'phorum_pm_xref',
        'phorum_messages_edittrack',
        'phorum_mod_log',
        'phorum_reports',
    ];

    public static function setUpBeforeClass(): void
    {
        // DealNews\DB\CRUD requires DealNews\DB\PDO, not a native \PDO.
        // We pass the SQLite in-memory DSN and immediately connect so the
        // database object is shared for the lifetime of the test class.
        self::$pdo = new DbPDO('sqlite::memory:');
        self::$pdo->connect();
        self::$crud = new CRUD(self::$pdo);

        $schema = file_get_contents(dirname(__DIR__, 2) . '/db/sqlite_test.sql');
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
            self::$pdo->exec($stmt);
        }
    }

    protected function setUp(): void
    {
        foreach (static::$tables as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
            // Reset AUTOINCREMENT sequence so IDs are predictable
            self::$pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }
    }

    /**
     * Helper: insert a raw row and return the new rowid.
     */
    protected function insert(string $table, array $row): int
    {
        $cols        = implode(', ', array_keys($row));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($row)));
        $stmt        = self::$pdo->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})");
        $stmt->execute($row);
        return (int) self::$pdo->lastInsertId();
    }
}

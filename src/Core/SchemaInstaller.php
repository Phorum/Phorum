<?php
declare(strict_types=1);

namespace Phorum\Core;

use Phorum\Core\Concerns\HasCrud;
use Phorum\Core\Concerns\SplitsSqlStatements;

/**
 * Applies db/mysql.sql against the configured database.
 *
 * Every CREATE TABLE in that file uses IF NOT EXISTS, so this is safe to run
 * repeatedly and against an existing Phorum 6 database — it only ever adds
 * tables that are missing, never touches ones that already exist. Used by
 * both the fresh installer and the upgrade-from-Phorum-6 flow.
 */
class SchemaInstaller
{
    use HasCrud;
    use SplitsSqlStatements;

    private readonly string $schemaFile;

    public function __construct(?string $schemaFile = null)
    {
        $this->schemaFile = $schemaFile ?? ROOT_PATH . '/db/mysql.sql';
    }

    public function apply(): void
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $crud   = $this->crud();
        foreach ($this->splitStatements($this->schemaFile, $prefix) as $stmt) {
            $crud->run($stmt, []);
        }
    }

    /**
     * Table base names (without prefix) from the schema file that don't
     * exist in the database yet. For display only — apply() doesn't need
     * this, since the schema file is self-idempotent regardless.
     *
     * @return string[]
     */
    public function pendingTables(): array
    {
        $sql = (string) file_get_contents($this->schemaFile);
        preg_match_all('/CREATE TABLE IF NOT EXISTS \{PREFIX\}_(\w+)/', $sql, $matches);

        $prefix   = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $existing = $this->existingTableNames($prefix);

        $pending = [];
        foreach ($matches[1] as $baseName) {
            if (!in_array($prefix . '_' . $baseName, $existing, true)) {
                $pending[] = $baseName;
            }
        }

        return $pending;
    }

    /**
     * All currently-existing table names starting with $prefix, in one
     * query rather than one existence probe per table.
     *
     * @return string[]
     */
    private function existingTableNames(string $prefix): array
    {
        $crud   = $this->crud();
        $driver = $crud->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $like   = $prefix . '\_%';

        $sql = $driver === 'sqlite'
            ? "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE :pattern ESCAPE '\\'"
            : "SELECT table_name AS name FROM information_schema.tables"
              . " WHERE table_schema = DATABASE() AND table_name LIKE :pattern ESCAPE '\\\\'";

        $rows = $crud->runFetch($sql, [':pattern' => $like]);
        return array_column($rows, 'name');
    }
}

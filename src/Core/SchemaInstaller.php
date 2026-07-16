<?php
declare(strict_types=1);

namespace Phorum\Core;

use DealNews\DB\CRUD;
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
    use SplitsSqlStatements;

    private ?CRUD $crud = null;

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

        $prefix  = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $crud    = $this->crud();
        $pending = [];

        foreach ($matches[1] as $baseName) {
            try {
                $crud->runFetch("SELECT 1 FROM {$prefix}_{$baseName} LIMIT 1", []);
            } catch (\Throwable) {
                $pending[] = $baseName;
            }
        }

        return $pending;
    }

    protected function crud(): CRUD
    {
        if ($this->crud === null) {
            $db         = defined('PHORUM_DB') ? PHORUM_DB : 'phorum';
            $this->crud = CRUD::factory($db);
        }
        return $this->crud;
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Core;

use Phorum\Core\Concerns\HasCrud;
use Phorum\Core\Concerns\SplitsSqlStatements;

/**
 * Applies db/mysql.sql, plus every enabled or not-yet-enabled module's own
 * mods/{name}/mysql.sql if present, against the configured database.
 *
 * Every CREATE TABLE in these files uses IF NOT EXISTS, so this is safe to
 * run repeatedly and against an existing Phorum 6 database — it only ever
 * adds tables that are missing, never touches ones that already exist. Used
 * by both the fresh installer and the upgrade-from-Phorum-6 flow.
 *
 * Module schema files are picked up regardless of whether the module is
 * currently enabled (mirroring how ModulesController discovers modules by
 * directory presence, not enabled state): this runs as part of the same
 * version-triggered schema sync that already creates every core table, so a
 * module's table is ready the moment it's enabled rather than depending on
 * a second, separate trigger.
 */
class SchemaInstaller
{
    use HasCrud;
    use SplitsSqlStatements;

    private readonly string $schemaFile;
    private readonly string $modsDir;

    public function __construct(?string $schemaFile = null, ?string $modsDir = null)
    {
        $this->schemaFile = $schemaFile ?? ROOT_PATH . '/db/mysql.sql';
        $this->modsDir    = $modsDir    ?? (defined('ROOT_PATH') ? ROOT_PATH . '/mods' : '');
    }

    public function apply(): void
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $crud   = $this->crud();
        foreach ($this->allSchemaFiles() as $file) {
            foreach ($this->splitStatements($file, $prefix) as $stmt) {
                $crud->run($stmt, []);
            }
        }
    }

    /**
     * Table base names (without prefix) from the schema files that don't
     * exist in the database yet. For display only — apply() doesn't need
     * this, since the schema files are self-idempotent regardless.
     *
     * @return string[]
     */
    public function pendingTables(): array
    {
        $prefix   = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $existing = $this->existingTableNames($prefix);

        $pending = [];
        foreach ($this->allSchemaFiles() as $file) {
            $sql = (string) file_get_contents($file);
            preg_match_all('/CREATE TABLE IF NOT EXISTS \{PREFIX\}_(\w+)/', $sql, $matches);
            foreach ($matches[1] as $baseName) {
                if (!in_array($prefix . '_' . $baseName, $existing, true)) {
                    $pending[] = $baseName;
                }
            }
        }

        return $pending;
    }

    /**
     * The core schema file plus every mods/{name}/{same basename} found —
     * e.g. mods/webhooks/mysql.sql alongside db/mysql.sql.
     *
     * @return string[]
     */
    private function allSchemaFiles(): array
    {
        $files    = [$this->schemaFile];
        $fileName = basename($this->schemaFile);

        if ($this->modsDir !== '' && is_dir($this->modsDir)) {
            foreach (glob($this->modsDir . '/*/' . $fileName) ?: [] as $file) {
                $files[] = $file;
            }
        }

        return $files;
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

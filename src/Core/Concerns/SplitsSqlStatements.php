<?php
declare(strict_types=1);

namespace Phorum\Core\Concerns;

/**
 * Shared by SchemaInstaller and SchemaPatcher: load a SQL file, substitute
 * the table prefix, and split it into individual executable statements,
 * skipping blank/comment-only blocks.
 */
trait SplitsSqlStatements
{
    /**
     * @return string[]
     */
    protected function splitStatements(string $file, string $prefix): array
    {
        $sql = (string) file_get_contents($file);
        $sql = str_replace('{PREFIX}', $prefix, $sql);

        $statements = [];
        foreach (preg_split('/;\s*\n/', $sql) ?: [] as $stmt) {
            $stmt     = trim($stmt);
            $stripped = preg_replace('/^\s*--[^\n]*\n?/m', '', $stmt);
            if (trim((string) $stripped) === '') {
                continue;
            }
            $statements[] = $stmt;
        }

        return $statements;
    }
}

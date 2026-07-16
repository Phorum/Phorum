<?php
declare(strict_types=1);

namespace Phorum\Core;

use Phorum\Core\Concerns\HasCrud;
use Phorum\Core\Concerns\SplitsSqlStatements;
use Phorum\Mapper\SettingMapper;

/**
 * Applies numbered ALTER-only patch files under db/patches/ against the
 * configured database — for changes to *existing* tables (new columns,
 * default changes) that SchemaInstaller's `CREATE TABLE IF NOT EXISTS`
 * can't reach.
 *
 * Unlike SchemaInstaller, ALTER TABLE isn't naturally idempotent, so applied
 * patches are tracked via the 'schema_patch_level' setting (the highest
 * patch number applied so far) — mirroring Phorum 6's own internal_patchlevel.
 * Patch numbers are a plain incrementing sequence, deliberately decoupled
 * from Phorum 10's release version (Phorum 6 itself does the same: its own
 * schema/patch files are date-stamped independently of its release string).
 */
class SchemaPatcher
{
    use HasCrud;
    use SplitsSqlStatements;

    private const SETTING_KEY = 'schema_patch_level';

    private readonly string $patchDir;
    private readonly SettingMapper $settings;

    public function __construct(?string $patchDir = null, ?SettingMapper $settings = null)
    {
        $this->patchDir = $patchDir ?? ROOT_PATH . '/db/patches';
        $this->settings = $settings ?? new SettingMapper();
    }

    /** Run every not-yet-applied patch in order, recording progress after each. */
    public function apply(): void
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $crud   = $this->crud();

        foreach ($this->pendingPatches() as $number => $file) {
            foreach ($this->splitStatements($file, $prefix) as $stmt) {
                $crud->run($stmt, []);
            }
            $this->settings->saveSetting(self::SETTING_KEY, $number);
        }
    }

    /**
     * Record every known patch as already applied, without running anything.
     * Used right after a fresh install, where the base schema already has
     * every current column — running the patches too would fail with
     * "duplicate column".
     */
    public function markAllApplied(): void
    {
        $numbers = array_keys($this->allPatches());
        if (empty($numbers)) {
            return;
        }
        $this->settings->saveSetting(self::SETTING_KEY, max($numbers));
    }

    /**
     * Short human-readable descriptions of not-yet-applied patches, for
     * display on the upgrade confirmation page.
     *
     * @return string[]
     */
    public function pendingPatchDescriptions(): array
    {
        return array_values(array_map(
            fn(string $file) => $this->describe($file),
            $this->pendingPatches()
        ));
    }

    /** @return array<int, string> patch number => file path, ascending, not yet applied */
    private function pendingPatches(): array
    {
        $applied = (int) ($this->settings->getSetting(self::SETTING_KEY) ?? 0);

        $pending = array_filter(
            $this->allPatches(),
            fn(int $number) => $number > $applied,
            ARRAY_FILTER_USE_KEY
        );
        ksort($pending);

        return $pending;
    }

    /** @return array<int, string> patch number => file path, for every patch file found */
    private function allPatches(): array
    {
        $patches = [];
        foreach (glob($this->patchDir . '/*.sql') ?: [] as $file) {
            if (preg_match('/^(\d+)_/', basename($file), $m)) {
                $patches[(int) $m[1]] = $file;
            }
        }
        return $patches;
    }

    private function describe(string $file): string
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        return str_replace('_', ' ', preg_replace('/^\d+_/', '', $name));
    }
}

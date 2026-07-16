<?php
declare(strict_types=1);

namespace Phorum\Core;

use Phorum\Mapper\SettingMapper;

/**
 * Applies SchemaInstaller + SchemaPatcher and records the resulting version —
 * the sequence shared by App::selfHealSchema() (silent, on already-installed
 * sites) and UpgradeController (explicit, user-initiated). Not used by
 * InstallController: a fresh install marks patches applied without running
 * them (the base schema already has every current column) and bootstraps
 * several other settings beyond schema_version, so its sequence is
 * genuinely different rather than a copy of this one.
 */
class SchemaMigrator
{
    public function __construct(
        private readonly SchemaInstaller $installer = new SchemaInstaller(),
        private readonly SchemaPatcher   $patcher    = new SchemaPatcher(),
        private readonly SettingMapper   $settings   = new SettingMapper(),
    ) {}

    /** Add any missing tables/columns and record the current version. */
    public function bringUpToDate(): void
    {
        $this->installer->apply();
        $this->patcher->apply();
        $this->settings->saveSetting('schema_version', Version::CURRENT);
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\AdminAuth;
use Phorum\Core\Config;
use Phorum\Core\SchemaInstaller;
use Phorum\Core\SchemaMigrator;
use Phorum\Core\SchemaPatcher;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\SettingMapper;
use Twig\Environment;

/**
 * Bridges an existing Phorum 6 database into Phorum 10: adds whatever new,
 * purely-additive tables Phorum 10 needs (see SchemaInstaller), plus any new
 * columns on tables Phorum 6 already has (see SchemaPatcher), without
 * touching anything Phorum 6 already created. Reached only when App detects
 * a database with Phorum 6's own 'internal_version' setting but no Phorum 10
 * 'installed' setting — see App::bootState().
 *
 * Same trust tier as InstallController: unauthenticated, since Phorum 10's
 * AdminAuth can't be assumed to work until this step has run.
 *
 * On an already-installed site, this same flow doubles as the manual trigger
 * for patches shipped after the last release (self-heal normally only runs
 * once `schema_version` no longer matches `Version::CURRENT` — see
 * App::selfHealSchema() — so a mid-release patch file otherwise sits unused
 * until the next version bump). That reentry is only reachable via
 * `?force=1` and requires an authenticated admin, since AdminAuth is fully
 * usable by that point.
 */
class UpgradeController extends Controller
{
    private readonly SchemaInstaller $schema;
    private readonly SchemaPatcher   $patcher;
    private readonly SettingMapper   $settings;

    public function __construct(
        Config             $config,
        Environment         $twig,
        ?SchemaInstaller    $schema   = null,
        ?SchemaPatcher      $patcher  = null,
        ?SettingMapper      $settings = null,
    ) {
        parent::__construct($config, $twig);
        $this->schema   = $schema   ?? new SchemaInstaller();
        $this->patcher  = $patcher  ?? new SchemaPatcher();
        $this->settings = $settings ?? new SettingMapper();
    }

    public function index(Request $request): Response
    {
        $installed = $this->checkInstalled();
        $force     = ($request->query['force'] ?? null) === '1';

        if ($installed && !$force) {
            return $this->redirect('/');
        }

        if ($installed && AdminAuth::user() === null) {
            return $this->redirect('/admin/login');
        }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            (new SchemaMigrator($this->schema, $this->patcher, $this->settings))->bringUpToDate();
            if (!$installed) {
                $this->settings->saveSetting('installed', '1');
            }

            return $this->redirect('/upgrade/complete');
        }

        return $this->respond($this->twig->render('upgrade/index.html.twig', [
            'pending_tables'  => $this->schema->pendingTables(),
            'pending_patches' => $this->patcher->pendingPatchDescriptions(),
            'force'           => $force,
        ]));
    }

    public function complete(Request $request): Response
    {
        return $this->respond($this->twig->render('upgrade/complete.html.twig', []));
    }

    /**
     * True once the site has been through this upgrade (or the fresh
     * installer). Mirrors InstallController::checkInstalled() — gates the
     * fresh-bridge flow off on an already-installed site; the `?force=1`
     * reentry path (gated separately by AdminAuth) is the only way back in.
     */
    private function checkInstalled(): bool
    {
        return !empty($this->settings->getSetting('installed'));
    }
}

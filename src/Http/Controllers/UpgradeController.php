<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Config;
use Phorum\Core\SchemaInstaller;
use Phorum\Core\SchemaPatcher;
use Phorum\Core\Version;
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
        if ($this->checkInstalled()) {
            return $this->redirect('/');
        }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            $this->schema->apply();
            $this->patcher->apply();
            $this->settings->saveSetting('schema_version', Version::CURRENT);
            $this->settings->saveSetting('installed', '1');

            return $this->redirect('/upgrade/complete');
        }

        return $this->respond($this->twig->render('upgrade/index.html.twig', [
            'pending_tables'  => $this->schema->pendingTables(),
            'pending_patches' => $this->patcher->pendingPatchDescriptions(),
        ]));
    }

    public function complete(Request $request): Response
    {
        return $this->respond($this->twig->render('upgrade/complete.html.twig', []));
    }

    /**
     * True once the site has been through this upgrade (or the fresh
     * installer). Mirrors InstallController::checkInstalled() — prevents
     * this otherwise-unauthenticated, schema-mutating endpoint from staying
     * reachable on an already-installed site.
     */
    private function checkInstalled(): bool
    {
        return !empty($this->settings->getSetting('installed'));
    }
}

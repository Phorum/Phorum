<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\Config;
use Phorum\Core\SchemaInstaller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\SettingMapper;
use Twig\Environment;

class ModulesController extends AdminController
{
    private readonly SettingMapper  $settings;
    private readonly SchemaInstaller $schema;

    public function __construct(
        Config           $config,
        Environment      $twig,
        ?SettingMapper   $settings = null,
        ?SchemaInstaller $schema  = null,
    ) {
        parent::__construct($config, $twig);
        $this->settings = $settings ?? new SettingMapper();
        $this->schema   = $schema   ?? new SchemaInstaller();
    }

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $enabled = (array) ($this->settings->getSetting('mods') ?? []);
        $success = '';
        $errors  = [];

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $modName = trim($request->post['mod'] ?? '');
            $action  = $request->post['action'] ?? '';

            if ($modName !== '' && in_array($action, ['enable', 'disable'], strict: true)) {
                if ($action === 'enable') {
                    // Ensure the module's own tables (mods/{name}/mysql.sql, if
                    // any) exist before marking it enabled — the version-triggered
                    // self-heal in App::selfHealSchema() only re-syncs schema when
                    // the core version has moved, so a module enabled on an
                    // already-up-to-date site would otherwise never get this.
                    try {
                        $this->schema->apply();
                        $enabled[$modName] = 1;
                        $this->settings->saveSetting('mods', $enabled);
                        $success = "Module \"{$modName}\" enabled. Changes take effect on next page load.";
                    } catch (\Throwable $e) {
                        $errors[] = "Could not set up \"{$modName}\"'s database tables: {$e->getMessage()}";
                    }
                } else {
                    $enabled[$modName] = 0;
                    $this->settings->saveSetting('mods', $enabled);
                    $success = "Module \"{$modName}\" disabled. Changes take effect on next page load.";
                }
            }
        }

        return $this->respond($this->renderAdmin('admin/modules.html.twig', [
            'modules' => $this->discoverModules($enabled),
            'success' => $success,
            'errors'  => $errors,
        ]));
    }

    // -------------------------------------------------------------------------

    /** Scan the mods/ directory and annotate each with enabled/disabled state. */
    private function discoverModules(array $enabled): array
    {
        $modsDir = defined('ROOT_PATH') ? ROOT_PATH . '/mods' : '';
        $modules = [];

        if ($modsDir === '' || !is_dir($modsDir)) {
            return $modules;
        }

        foreach (new \DirectoryIterator($modsDir) as $entry) {
            if (!$entry->isDir() || $entry->isDot()) {
                continue;
            }

            $name    = $entry->getFilename();
            $mainFile = $modsDir . "/{$name}/{$name}.php";
            if (!file_exists($mainFile)) {
                continue;
            }

            $modules[$name] = [
                'name'    => $name,
                'enabled' => !empty($enabled[$name]),
                'info'    => $this->readModuleInfo($modsDir . "/{$name}"),
            ];
        }

        ksort($modules);
        return array_values($modules);
    }

    /** Read the first few lines of info.txt (or inline PHP comment) for display. */
    private function readModuleInfo(string $dir): array
    {
        $info = ['title' => '', 'desc' => '', 'configure' => ''];

        $infoFile = $dir . '/info.txt';
        if (file_exists($infoFile)) {
            foreach (file($infoFile) ?: [] as $line) {
                if (preg_match('/^title:\s*(.+)$/i', $line, $m)) {
                    $info['title'] = trim($m[1]);
                }
                if (preg_match('/^desc:\s*(.+)$/i', $line, $m)) {
                    $info['desc'] = trim($m[1]);
                }
                // A module-provided admin page, e.g. "configure: /admin/webhooks".
                // Only a same-site absolute path is accepted — anything else is ignored.
                if (preg_match('/^configure:\s*(\/\S*)$/i', $line, $m)) {
                    $info['configure'] = trim($m[1]);
                }
            }
        }

        return $info;
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\Config;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\SettingMapper;
use Twig\Environment;

class ModulesController extends AdminController
{
    private readonly SettingMapper $settings;

    public function __construct(
        Config         $config,
        Environment    $twig,
        ?SettingMapper $settings = null,
    ) {
        parent::__construct($config, $twig);
        $this->settings = $settings ?? new SettingMapper();
    }

    public function index(Request $request): Response
    {
        if ($r = $this->requireAdmin()) { return $r; }

        $enabled = (array) ($this->settings->getSetting('mods') ?? []);
        $success = '';

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $modName = trim($request->post['mod'] ?? '');
            $action  = $request->post['action'] ?? '';

            if ($modName !== '' && in_array($action, ['enable', 'disable'], strict: true)) {
                if ($action === 'enable') {
                    $enabled[$modName] = 1;
                } else {
                    $enabled[$modName] = 0;
                }
                $this->settings->saveSetting('mods', $enabled);
                $success = "Module \"{$modName}\" {$action}d. Changes take effect on next page load.";
            }
        }

        return $this->respond($this->renderAdmin('admin/modules.html.twig', [
            'modules' => $this->discoverModules($enabled),
            'success' => $success,
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
        $info = ['title' => '', 'desc' => ''];

        $infoFile = $dir . '/info.txt';
        if (file_exists($infoFile)) {
            foreach (file($infoFile) ?: [] as $line) {
                if (preg_match('/^title:\s*(.+)$/i', $line, $m)) {
                    $info['title'] = trim($m[1]);
                }
                if (preg_match('/^desc:\s*(.+)$/i', $line, $m)) {
                    $info['desc'] = trim($m[1]);
                }
            }
        }

        return $info;
    }
}

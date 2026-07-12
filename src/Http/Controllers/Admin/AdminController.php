<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\AdminAuth;
use Phorum\Core\Version;
use Phorum\Http\Controller;
use Phorum\Http\Response;
use Phorum\Model\User;

abstract class AdminController extends Controller
{
    /**
     * Check admin auth; redirect to login if not authenticated.
     * Returns null on success (caller may proceed); returns a redirect Response on failure.
     * Usage: if ($r = $this->requireAdmin()) { return $r; }
     */
    protected function requireAdmin(): ?Response
    {
        if (AdminAuth::user() === null) {
            return $this->redirect('/admin/login');
        }
        return null;
    }

    /**
     * Return [directory_name => display_name] for every theme that has a
     * config.php and is not marked hidden. Includes a leading blank entry so
     * selects can represent "use site default".
     *
     * @return array<string,string>
     */
    protected function loadThemes(bool $withDefault = false): array
    {
        $themes = [];
        $dir    = ROOT_PATH . '/themes';

        if (is_dir($dir)) {
            foreach (new \DirectoryIterator($dir) as $entry) {
                if (!$entry->isDir() || $entry->isDot()) {
                    continue;
                }
                $configFile = $entry->getPathname() . '/config.php';
                if (!file_exists($configFile)) {
                    continue;
                }
                $config = require $configFile;
                if (!is_array($config) || !empty($config['hidden'])) {
                    continue;
                }
                $themes[$entry->getFilename()] = $config['name'] ?? $entry->getFilename();
            }
            asort($themes);
        }

        if ($withDefault) {
            $themes = ['' => '— Site default —'] + $themes;
        }

        return $themes;
    }

    /** Render an admin template with the standard admin base data merged in. */
    protected function renderAdmin(string $template, array $data = []): string
    {
        $addonSections = phorum_api_hook('addon', []);
        return $this->render($template, array_merge([
            'admin_user'     => AdminAuth::user(),
            'addon_sections' => is_array($addonSections) ? $addonSections : [],
            'phorum_version' => Version::CURRENT,
        ], $data));
    }
}

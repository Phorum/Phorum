<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use DealNews\DB\CRUD;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\SettingMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\User;

class InstallController extends Controller
{
    public function index(Request $request): Response
    {
        if ($this->checkInstalled()) {
            return $this->redirect('/');
        }

        $requirements = $this->requirements();
        $canInstall   = array_reduce($requirements, fn(bool $ok, array $r) => $ok && $r['ok'], true);
        $errors       = [];
        $values       = [
            'site_name'      => 'Phorum',
            'admin_username' => '',
            'admin_email'    => '',
        ];

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $values = [
                'site_name'      => trim($request->post['site_name']      ?? ''),
                'admin_username' => trim($request->post['admin_username'] ?? ''),
                'admin_email'    => trim($request->post['admin_email']    ?? ''),
            ];
            $adminPassword  = $request->post['admin_password']  ?? '';
            $adminPassword2 = $request->post['admin_password2'] ?? '';

            if ($values['site_name'] === '') {
                $errors[] = 'Site name is required.';
            }
            if ($values['admin_username'] === '') {
                $errors[] = 'Admin username is required.';
            } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $values['admin_username'])) {
                $errors[] = 'Username must be 3–50 characters (letters, numbers, _ . - only).';
            }
            if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'A valid admin email address is required.';
            }
            if (strlen($adminPassword) < 8) {
                $errors[] = 'Admin password must be at least 8 characters.';
            } elseif ($adminPassword !== $adminPassword2) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors) && $canInstall) {
                try {
                    $this->runInstall($values['site_name'], $values['admin_username'], $values['admin_email'], $adminPassword);
                    return $this->redirect('/install/complete');
                } catch (\Throwable $e) {
                    $errors[] = 'Installation failed: ' . $e->getMessage();
                }
            }
        }

        return $this->respond($this->twig->render('install/index.html.twig', [
            'requirements' => $requirements,
            'can_install'  => $canInstall,
            'errors'       => $errors,
            'values'       => $values,
        ]));
    }

    public function complete(Request $request): Response
    {
        return $this->respond($this->twig->render('install/complete.html.twig', []));
    }

    // -------------------------------------------------------------------------

    private function checkInstalled(): bool
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $db     = defined('PHORUM_DB')        ? PHORUM_DB        : 'phorum';
        try {
            $rows = CRUD::factory($db)->runFetch(
                "SELECT data FROM {$prefix}_settings WHERE name = 'installed'",
                []
            );
            return !empty($rows) && !empty($rows[0]['data']);
        } catch (\Throwable) {
            return false;
        }
    }

    private function requirements(): array
    {
        $dbOk      = false;
        $dbMessage = 'cannot connect';
        try {
            $db   = defined('PHORUM_DB') ? PHORUM_DB : 'phorum';
            CRUD::factory($db)->runFetch('SELECT 1', []);
            $dbOk      = true;
            $dbMessage = 'connected';
        } catch (\Throwable $e) {
            $msgs = [];
            for ($t = $e; $t !== null; $t = $t->getPrevious()) {
                $msgs[] = get_class($t) . ': ' . $t->getMessage()
                        . ' (in ' . $t->getFile() . ':' . $t->getLine() . ')';
            }
            $dbMessage = implode("\n", $msgs);
        }

        return [
            [
                'name'  => 'PHP 8.3+',
                'ok'    => PHP_VERSION_ID >= 80300,
                'value' => PHP_VERSION,
            ],
            [
                'name'  => 'PDO extension',
                'ok'    => extension_loaded('pdo'),
                'value' => extension_loaded('pdo') ? 'enabled' : 'missing',
            ],
            [
                'name'  => 'PDO MySQL driver',
                'ok'    => extension_loaded('pdo_mysql'),
                'value' => extension_loaded('pdo_mysql') ? 'enabled' : 'missing',
            ],
            [
                'name'  => 'JSON extension',
                'ok'    => extension_loaded('json'),
                'value' => extension_loaded('json') ? 'enabled' : 'missing',
            ],
            [
                'name'  => 'mbstring extension',
                'ok'    => extension_loaded('mbstring'),
                'value' => extension_loaded('mbstring') ? 'enabled' : 'missing',
            ],
            [
                'name'  => 'Database connection',
                'ok'    => $dbOk,
                'value' => $dbMessage,
            ],
        ];
    }

    private function runInstall(string $siteName, string $adminUsername, string $adminEmail, string $adminPassword): void
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $db     = defined('PHORUM_DB')        ? PHORUM_DB        : 'phorum';
        $crud   = CRUD::factory($db);

        // Load schema, substitute prefix, execute each statement
        $sql        = (string) file_get_contents(ROOT_PATH . '/db/mysql.sql');
        $sql        = str_replace('{PREFIX}', $prefix, $sql);
        $statements = preg_split('/;\s*\n/', $sql) ?: [];

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            // Skip blank lines and comment-only blocks
            $stripped = preg_replace('/^\s*--[^\n]*\n?/m', '', $stmt);
            if (trim((string) $stripped) === '') {
                continue;
            }
            $crud->run($stmt, []);
        }

        // Create admin user
        $user                  = new User();
        $user->username        = $adminUsername;
        $user->display_name    = $adminUsername;
        $user->real_name       = '';
        $user->email           = $adminEmail;
        $user->password        = password_hash($adminPassword, PASSWORD_BCRYPT);
        $user->active          = 1;
        $user->admin           = 1;
        $user->date_added      = time();
        $user->date_last_active = time();
        $user->settings_data   = '';
        $user->signature       = '';
        (new UserMapper())->save($user);

        // Persist settings
        $settings = new SettingMapper();
        $settings->saveSetting('installed', '1');
        $settings->saveSetting('site_name', $siteName);
        $settings->saveSetting('mods', ['bbcode' => 1]);
    }
}

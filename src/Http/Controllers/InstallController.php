<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use DealNews\DB\CRUD;
use Phorum\Core\Lang;
use Phorum\Core\SchemaInstaller;
use Phorum\Core\SchemaPatcher;
use Phorum\Core\Version;
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
                $errors[] = Lang::get('install.error_site_name_required');
            }
            if ($values['admin_username'] === '') {
                $errors[] = Lang::get('install.error_username_required');
            } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $values['admin_username'])) {
                $errors[] = Lang::get('install.error_username_format');
            }
            if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = Lang::get('install.error_email_required');
            }
            if (strlen($adminPassword) < 8) {
                $errors[] = Lang::get('install.error_password_min_length');
            } elseif ($adminPassword !== $adminPassword2) {
                $errors[] = Lang::get('install.error_passwords_mismatch');
            }

            if (empty($errors) && $canInstall) {
                try {
                    $this->runInstall($values['site_name'], $values['admin_username'], $values['admin_email'], $adminPassword);
                    return $this->redirect('/install/complete');
                } catch (\Throwable $e) {
                    $errors[] = Lang::get('install.error_failed', ['message' => $e->getMessage()]);
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
        $settings = new SettingMapper();

        // Defense in depth: App already routes an existing Phorum 6 database
        // (identified by its own 'internal_version' setting) to /upgrade
        // instead of here, but refuse anyway in case this is ever reached
        // directly — this flow creates a new admin user and would otherwise
        // silently duplicate one on top of real Phorum 6 data.
        try {
            if ($settings->getSetting('internal_version') !== null) {
                throw new \RuntimeException(
                    'This database was created by Phorum 6. Go to /upgrade instead of /install.'
                );
            }
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
            // Settings table doesn't exist yet — genuinely fresh database.
        }

        (new SchemaInstaller())->apply();
        // The base schema above already has every current column, so the
        // patches that bring older databases up to date would just fail
        // with "duplicate column" here — mark them applied without running.
        (new SchemaPatcher())->markAllApplied();

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
        $settings->saveSetting('installed', '1');
        $settings->saveSetting('schema_version', Version::CURRENT);
        $settings->saveSetting('site_name', $siteName);
        $settings->saveSetting('mods', ['bbcode' => 1]);
    }
}

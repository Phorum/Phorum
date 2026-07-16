<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\Config;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\SettingMapper;
use Twig\Environment;

class SettingsController extends AdminController
{
    /** Keys we expose in the admin UI, with labels and input type hints. */
    private const FIELDS = [
        'site_name'      => ['label' => 'Site Name',         'type' => 'text'],
        'base_url'       => ['label' => 'Base URL',           'type' => 'text'],
        'mail_host'      => ['label' => 'SMTP Host',          'type' => 'text'],
        'mail_port'      => ['label' => 'SMTP Port',          'type' => 'number'],
        'mail_from'      => ['label' => 'Mail From Address',  'type' => 'email'],
        'flood_interval' => ['label' => 'Minimum Seconds Between Posts (0 = disabled)', 'type' => 'number'],
        'edit_time_limit' => ['label' => 'Edit Time Limit (minutes, 0 = unlimited)', 'type' => 'number'],
    ];

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

        $stored  = $this->settings->getAll();
        $success = '';
        $errors  = [];
        $themes  = $this->loadThemes();   // inherited from AdminController
        $locales = $this->loadLocales();

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $toSave = [];
            foreach (self::FIELDS as $key => $meta) {
                $val = trim($request->post[$key] ?? '');
                if ($meta['type'] === 'number') {
                    $val = (int) $val;
                }
                $toSave[$key] = $val;
            }

            // Theme and language are selects — validate against available options
            $selectedTheme = trim($request->post['template'] ?? '');
            if ($selectedTheme !== '' && array_key_exists($selectedTheme, $themes)) {
                $toSave['template'] = $selectedTheme;
            }

            $selectedLocale = trim($request->post['language'] ?? '');
            if ($selectedLocale !== '' && array_key_exists($selectedLocale, $locales)) {
                $toSave['language'] = $selectedLocale;
            }

            $this->settings->saveAll($toSave);
            $stored  = array_merge($stored, $toSave);
            $success = 'Settings saved.';
        }

        // Seed from phorum.php config if not yet in the DB
        foreach ([...array_keys(self::FIELDS), 'template', 'language'] as $key) {
            if (!array_key_exists($key, $stored)) {
                $stored[$key] = $this->config->get($key, '');
            }
        }

        return $this->respond($this->renderAdmin('admin/settings.html.twig', [
            'fields'  => self::FIELDS,
            'stored'  => $stored,
            'themes'  => $themes,
            'locales' => $locales,
            'success' => $success,
            'errors'  => $errors,
        ]));
    }



    /**
     * Scan lang/ directory and return [locale_code => display_name] for all
     * available locale files. Display name comes from the '_name' key in each
     * file; falls back to the locale code if not present.
     */
    private function loadLocales(): array
    {
        $locales = ['en' => 'English (en)'];
        $dir     = ROOT_PATH . '/lang';

        if (!is_dir($dir)) {
            return $locales;
        }

        foreach (new \DirectoryIterator($dir) as $entry) {
            if (!$entry->isFile() || $entry->getExtension() !== 'php') {
                continue;
            }
            $code = $entry->getBasename('.php');
            if ($code === 'en') {
                continue;
            }
            $strings = require $entry->getPathname();
            $name    = is_array($strings) ? ($strings['_name'] ?? $code) : $code;
            $locales[$code] = $name . ' (' . $code . ')';
        }

        asort($locales);
        return $locales;
    }
}

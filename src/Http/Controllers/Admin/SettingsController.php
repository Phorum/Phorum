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
    /**
     * Keys we expose in the admin UI, with labels and input type hints.
     * Two categories deliberately NOT here:
     *  - Outbound-mail settings (mail_host/mail_port/mail_from/mail_username/
     *    mail_password/mail_encryption) — SMTP credentials are a secret on
     *    the same footing as the DB password in etc/config.ini.
     *  - base_url — tightly coupled to base_path (URL prefix for subfolder
     *    installs), which is read at request-dispatch time in App::run()
     *    before any DB connection exists. Keeping both in etc/phorum.php
     *    only avoids the two ever drifting out of sync.
     * site_name has no such constraint, so it's genuinely DB-backed (see
     * Phorum\Core\SiteSettings) — this FIELDS entry is real, unlike before.
     */
    private const FIELDS = [
        'site_name'      => ['label' => 'Site Name',         'type' => 'text'],
        'flood_interval' => [
            'label' => 'Minimum Seconds Between Posts',
            'type'  => 'number',
            'hint'  => '0 = disabled',
        ],
        'edit_time_limit' => [
            'label' => 'Edit Time Limit (minutes)',
            'type'  => 'number',
            'hint'  => '0 = unlimited',
        ],
        'min_account_age_days' => [
            'label' => 'Minimum Account Age for Auto-Approval (days)',
            'type'  => 'number',
            'hint'  => '0 = disabled',
        ],
        'karma_threshold_percent' => [
            'label' => 'Karma Threshold %',
            'type'  => 'number',
            'hint'  => 'Hold future posts once this share of a user\'s messages are moderator-deleted. 0 = disabled.',
        ],
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

            $toSave['enable_rss']          = !empty($request->post['enable_rss']);
            $toSave['file_uploads']        = !empty($request->post['file_uploads']);
            $toSave['require_mod_approval'] = !empty($request->post['require_mod_approval']);

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

        // enable_rss/file_uploads have no phorum.php config counterpart — DB-only, default enabled.
        $stored['enable_rss']   = $stored['enable_rss']   ?? true;
        $stored['file_uploads'] = $stored['file_uploads'] ?? true;
        // Opt-in, unlike the two above — registration shouldn't gain a new
        // moderator-approval gate for existing sites until an admin asks for it.
        $stored['require_mod_approval'] = $stored['require_mod_approval'] ?? false;

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

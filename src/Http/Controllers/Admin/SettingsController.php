<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\Config;
use Phorum\Core\Lang;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\SettingMapper;
use Phorum\Service\LoginThrottleService;
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
        'login_throttle_window' => [
            'label' => 'Login Rate-Limit Window (seconds)',
            'type'  => 'number',
            'hint'  => 'How long failed logins are counted for. Blank/0 uses the default of 900.',
        ],
        'login_max_per_ip' => [
            'label' => 'Max Failed Logins per IP per Window',
            'type'  => 'number',
            'hint'  => 'Blocks password spraying from one address. 0 = disabled.',
        ],
        'login_max_per_account' => [
            'label' => 'Max Failed Logins per Account per Window',
            'type'  => 'number',
            'hint'  => 'Blocks guessing at one account. Kept looser than the per-IP limit so it '
                     . 'cannot be used to lock someone out. 0 = disabled.',
        ],
        'login_max_resets' => [
            'label' => 'Max Password-Reset Emails per IP per Window',
            'type'  => 'number',
            'hint'  => 'Also covers resend-confirmation. 0 = disabled.',
        ],
        'karma_threshold_percent' => [
            'label' => 'Karma Threshold %',
            'type'  => 'number',
            'hint'  => 'Hold future posts once this share of a user\'s messages are moderator-deleted. 0 = disabled.',
        ],
    ];

    /**
     * Fallback values for the rate-limit fields, mirroring
     * LoginThrottleService's own defaults. Used only to populate the form —
     * the service still falls back to the same numbers on its own if these
     * settings are absent.
     */
    private const THROTTLE_DEFAULTS = [
        'login_throttle_window' => LoginThrottleService::DEFAULT_WINDOW,
        'login_max_per_ip'      => LoginThrottleService::DEFAULT_MAX_PER_IP,
        'login_max_per_account' => LoginThrottleService::DEFAULT_MAX_PER_ACCOUNT,
        'login_max_resets'      => LoginThrottleService::DEFAULT_MAX_RESETS,
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
        $locales = Lang::availableLocales();

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

        // Show the rate-limit defaults as real numbers rather than blanks.
        // Every number field here saves as (int), and 0 means "disabled" — so
        // a blank box would turn the login throttle off the first time an
        // admin saved this page for an unrelated reason.
        foreach (self::THROTTLE_DEFAULTS as $key => $default) {
            if (($stored[$key] ?? '') === '') {
                $stored[$key] = $default;
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
}

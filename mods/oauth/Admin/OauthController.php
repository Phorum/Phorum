<?php
declare(strict_types=1);

namespace Phorum\Mod\Oauth\Admin;

use Phorum\Core\Config;
use Phorum\Http\Controllers\Admin\AdminController;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\SettingMapper;
use Twig\Environment;

/** Admin settings page for Google/GitHub OAuth login. Routed via a fully-qualified action in mods/oauth/routes.php. */
class OauthController extends AdminController
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

        $errors  = [];
        $success = '';

        $googleEnabled   = (bool) ($this->settings->getSetting('oauth_google_enabled') ?? false);
        $googleClientId  = (string) ($this->settings->getSetting('oauth_google_client_id') ?? '');
        $googleHasSecret = (string) ($this->settings->getSetting('oauth_google_client_secret') ?? '') !== '';

        $githubEnabled   = (bool) ($this->settings->getSetting('oauth_github_enabled') ?? false);
        $githubClientId  = (string) ($this->settings->getSetting('oauth_github_client_id') ?? '');
        $githubHasSecret = (string) ($this->settings->getSetting('oauth_github_client_secret') ?? '') !== '';

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            $googleEnabled  = !empty($request->post['oauth_google_enabled']);
            $googleClientId = trim($request->post['oauth_google_client_id'] ?? '');
            $googleNewSecret = trim($request->post['oauth_google_client_secret'] ?? '');

            $githubEnabled  = !empty($request->post['oauth_github_enabled']);
            $githubClientId = trim($request->post['oauth_github_client_id'] ?? '');
            $githubNewSecret = trim($request->post['oauth_github_client_secret'] ?? '');

            if ($googleEnabled && ($googleClientId === '' || ($googleNewSecret === '' && !$googleHasSecret))) {
                $errors[] = 'Google requires both a client ID and a client secret to be enabled.';
            }
            if ($githubEnabled && ($githubClientId === '' || ($githubNewSecret === '' && !$githubHasSecret))) {
                $errors[] = 'GitHub requires both a client ID and a client secret to be enabled.';
            }

            if (empty($errors)) {
                $this->settings->saveSetting('oauth_google_enabled', $googleEnabled ? 1 : 0);
                $this->settings->saveSetting('oauth_google_client_id', $googleClientId);
                if ($googleNewSecret !== '') {
                    $this->settings->saveSetting('oauth_google_client_secret', $googleNewSecret);
                    $googleHasSecret = true;
                }

                $this->settings->saveSetting('oauth_github_enabled', $githubEnabled ? 1 : 0);
                $this->settings->saveSetting('oauth_github_client_id', $githubClientId);
                if ($githubNewSecret !== '') {
                    $this->settings->saveSetting('oauth_github_client_secret', $githubNewSecret);
                    $githubHasSecret = true;
                }

                $success = 'Settings saved.';
            }
        }

        $base = rtrim((string) $this->config->get('base_url', ''), '/')
              . (string) $this->config->get('base_path', '');

        return $this->respond($this->renderAdmin('admin/mods/oauth/index.html.twig', [
            'google_enabled'    => $googleEnabled,
            'google_client_id'  => $googleClientId,
            'google_has_secret' => $googleHasSecret,
            'google_callback'   => $base . '/auth/oauth/google/callback',
            'github_enabled'    => $githubEnabled,
            'github_client_id'  => $githubClientId,
            'github_has_secret' => $githubHasSecret,
            'github_callback'   => $base . '/auth/oauth/github/callback',
            'errors'            => $errors,
            'success'           => $success,
        ]));
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Mod\Oauth;

use Phorum\Core\Config;
use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\SettingMapper;

/**
 * Registers the auth_login_buttons hook that templates/auth/login.html.twig
 * calls via the hook() Twig function. Kept separate from oauth.php (the
 * module's boot file) so tests can call register() directly with an
 * injected HookDispatcher/SettingMapper, mirroring mods/webhooks/WebhookHooks.php.
 *
 * The hook's $data payload is an array ({redirect: '...'}), not a bare
 * string — HookDispatcher::dispatch() returns $data unchanged when no
 * listener is registered, and the hook() Twig function only echoes string
 * results, so a bare string payload would otherwise leak the raw redirect
 * path into the page whenever this module is absent or disabled.
 */
class OauthHooks
{
    private const LABELS = ['google' => 'Google', 'github' => 'GitHub'];

    public static function register(
        Config          $config,
        ?HookDispatcher $hooks    = null,
        ?SettingMapper  $settings = null,
    ): void {
        $hooks    ??= HookDispatcher::getInstance();
        $settings ??= new SettingMapper();
        $service    = new OauthService($config, $settings);

        $hooks->register('auth_login_buttons', static function (mixed $data) use ($service, $config): string {
            $redirect = is_array($data) ? (string) ($data['redirect'] ?? '/') : '/';
            $basePath = (string) $config->get('base_path', '');
            $html     = '';

            foreach (self::LABELS as $slug => $label) {
                if (!$service->isConfigured($slug)) {
                    continue;
                }
                $href  = $basePath . '/auth/oauth/' . $slug . '?redirect=' . rawurlencode($redirect);
                $html .= '<a class="oauth-login-button oauth-login-' . $slug . '" href="'
                       . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">Continue with '
                       . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
            }

            return $html;
        });
    }
}

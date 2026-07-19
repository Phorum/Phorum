<?php
declare(strict_types=1);

namespace Phorum\Mod\Oauth\Controllers;

use Phorum\Core\Config;
use Phorum\Core\RedirectGuard;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\UserMapper;
use Phorum\Mod\Oauth\OauthEmailNotVerifiedException;
use Phorum\Mod\Oauth\OauthService;
use Phorum\Service\AuthService;
use Twig\Environment;

/** Public OAuth login flow: /auth/oauth/{provider} and /auth/oauth/{provider}/callback. */
class OauthController extends Controller
{
    private readonly OauthService $oauth;
    private readonly AuthService  $authService;

    public function __construct(
        Config        $config,
        Environment   $twig,
        ?OauthService $oauth       = null,
        ?AuthService  $authService = null,
    ) {
        parent::__construct($config, $twig);
        $this->oauth       = $oauth       ?? new OauthService($config);
        $this->authService = $authService ?? new AuthService(new UserMapper(), (bool) $config->get('session_secure', false), $config);
    }

    /** Redirect the browser to the provider's consent screen. */
    public function start(Request $request): Response
    {
        $provider = (string) ($request->tokens['provider'] ?? '');
        if (!in_array($provider, OauthService::PROVIDERS, true)) {
            return $this->notFound();
        }

        if (!$this->oauth->isConfigured($provider)) {
            return $this->redirect('/login?oauth_error=not_configured');
        }

        ['url' => $url, 'state' => $state] = $this->oauth->authorizationUrl($provider);

        $this->startSession();
        $_SESSION['oauth_state']    = $state;
        $_SESSION['oauth_provider'] = $provider;
        $_SESSION['oauth_redirect'] = RedirectGuard::sanitizePath($request->query['redirect'] ?? '/');

        return $this->redirect($url);
    }

    /** Handle the provider's redirect back after the user grants/denies consent. */
    public function callback(Request $request): Response
    {
        $provider = (string) ($request->tokens['provider'] ?? '');
        if (!in_array($provider, OauthService::PROVIDERS, true)) {
            return $this->notFound();
        }

        $this->startSession();

        if (!empty($request->query['error'])) {
            $this->clearSessionState();
            return $this->redirect('/login?oauth_error=provider_error');
        }

        $state          = (string) ($request->query['state'] ?? '');
        $storedState    = (string) ($_SESSION['oauth_state'] ?? '');
        $storedProvider = (string) ($_SESSION['oauth_provider'] ?? '');
        $code           = (string) ($request->query['code'] ?? '');

        if ($code === '' || $storedState === '' || !hash_equals($storedState, $state) || $storedProvider !== $provider) {
            $this->clearSessionState();
            return $this->redirect('/login?oauth_error=state_mismatch');
        }

        $redirect = RedirectGuard::sanitizePath($_SESSION['oauth_redirect'] ?? '/');
        $this->clearSessionState();

        try {
            $token = $this->oauth->exchangeCode($provider, $code);
        } catch (\Throwable) {
            return $this->redirect('/login?oauth_error=token_exchange_failed');
        }

        try {
            $user = $this->oauth->resolveUser($provider, $token);
        } catch (OauthEmailNotVerifiedException) {
            return $this->redirect('/login?oauth_error=email_not_verified');
        } catch (\Throwable) {
            return $this->redirect('/login?oauth_error=login_failed');
        }

        if (!$user->active) {
            return $this->redirect('/login?oauth_error=account_inactive');
        }

        // No password re-entry to fall back on, so OAuth logins default to
        // "remember me" behavior.
        $this->authService->loginUser($user, remember: true);

        return $this->redirect($redirect);
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    private function clearSessionState(): void
    {
        unset($_SESSION['oauth_state'], $_SESSION['oauth_provider'], $_SESSION['oauth_redirect']);
    }
}

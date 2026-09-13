<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\CsrfGuard;
use Phorum\Core\Lang;
use Phorum\Core\RedirectGuard;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\BanMapper;
use Phorum\Mapper\LoginAttemptMapper;
use Phorum\Mapper\SettingMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\AuthService;
use Phorum\Service\BanService;
use Phorum\Service\LoginThrottleService;
use Twig\Environment;

class AuthController extends Controller
{
    /**
     * Session key holding the password-reset token between the emailed link
     * and the form submission, so it never has to travel in a URL again.
     */
    private const RESET_TOKEN_KEY = 'phorum_reset_token';

    private readonly AuthService  $authService;
    private readonly BanService   $banService;
    private readonly UserMapper   $users;
    private readonly SettingMapper $settings;
    private readonly LoginThrottleService $throttle;

    public function __construct(
        Config         $config,
        Environment    $twig,
        ?AuthService   $authService = null,
        ?BanService    $banService  = null,
        ?UserMapper    $users       = null,
        ?SettingMapper $settings    = null,
        ?LoginThrottleService $throttle = null,
    ) {
        parent::__construct($config, $twig);
        $this->authService = $authService ?? new AuthService(
            users:         new UserMapper(),
            secureCookies: (bool) $config->get('session_secure', false),
            config:        $config,
        );
        $this->banService  = $banService  ?? new BanService(new BanMapper());
        $this->users       = $users       ?? new UserMapper();
        $this->settings    = $settings    ?? new SettingMapper();
        $this->throttle    = $throttle    ?? new LoginThrottleService(new LoginAttemptMapper(), $this->settings);
    }

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    public function login(Request $request): Response
    {
        if (Auth::isLoggedIn()) {
            return $this->redirect('/');
        }

        $error = null;

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $username = trim($request->post['username'] ?? '');
            $password = $request->post['password'] ?? '';
            $remember = !empty($request->post['remember']);

            $wait = $this->throttle->loginRetryAfter($username, $request->server);

            if ($wait > 0) {
                // Same message whether or not the account exists, so the
                // throttle can't be used to probe for valid usernames.
                $error = Lang::get('auth.error_throttled', ['seconds' => (string) $wait]);
            } elseif ($username === '' || $password === '') {
                $error = Lang::get('auth.error_missing_credentials');
            } else {
                $user = $this->authService->login($username, $password, $remember);
                if ($user === null) {
                    $this->throttle->recordFailedLogin($username, $request->server);
                    $error = Lang::get('auth.error_invalid_credentials');
                } else {
                    $this->throttle->clearAccount($username);
                    $redirect = RedirectGuard::sanitizePath($request->post['redirect'] ?? '/');
                    if ($user->force_password_change) {
                        return $this->redirect(RedirectGuard::changePasswordUrl($redirect));
                    }
                    return $this->redirect($redirect);
                }
            }
        }

        $oauthError = $this->oauthErrorMessage((string) ($request->query['oauth_error'] ?? ''));

        return $this->respond($this->render('auth/login.html.twig', [
            'errors'    => array_values(array_filter([$error, $oauthError])),
            'redirect'  => $request->query['redirect'] ?? '/',
            'confirmed' => ($request->query['confirmed'] ?? '') === '1',
        ]));
    }

    /**
     * Translate a known ?oauth_error= code (set by mods/oauth's controller
     * on redirect) into a login-page error message. Unknown/absent codes
     * are silently ignored rather than surfaced, since this query param is
     * attacker-controlled.
     */
    private function oauthErrorMessage(string $code): ?string
    {
        $known = [
            'provider_error', 'state_mismatch', 'token_exchange_failed',
            'email_not_verified', 'login_failed', 'account_inactive', 'not_configured',
            'account_exists',
        ];

        return in_array($code, $known, true) ? Lang::get('oauth.error_' . $code) : null;
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    /**
     * GET  /logout — confirmation form.
     * POST /logout — log out (CSRF-protected).
     *
     * Logging out on GET let any site force it with a top-level navigation —
     * `window.location`, a meta refresh, a 302 — which also destroyed the
     * year-long remember-me cookie. SameSite=Lax already stopped the silent
     * `<img src="/logout">` variant, since that's a subresource request and
     * never carries the cookie, but not a navigation.
     *
     * A GET shows a confirmation form rather than refusing outright, so
     * existing bookmarks and links to /logout keep working — the same shape
     * SubscriptionController::follow() uses for email quick-action links.
     */
    public function logout(Request $request): Response
    {
        if (!$request->isPost()) {
            return $this->respond($this->render('auth/logout_confirm.html.twig', []));
        }

        if ($r = $this->checkCsrf($request)) { return $r; }

        $user = Auth::user();
        if ($user !== null) {
            $this->authService->logout($user);
        }
        return $this->redirect('/');
    }

    // -------------------------------------------------------------------------
    // Register
    // -------------------------------------------------------------------------

    public function register(Request $request): Response
    {
        if (Auth::isLoggedIn()) {
            return $this->redirect('/');
        }

        $error = null;

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $username  = trim($request->post['username'] ?? '');
            $email     = trim($request->post['email'] ?? '');
            $password  = $request->post['password'] ?? '';
            $password2 = $request->post['password2'] ?? '';

            $error = $this->validateRegistration($username, $email, $password, $password2);

            if ($error === null) {
                if (
                    $this->banService->checkIp(forumId: 0) ||
                    $this->banService->checkEmail($email, forumId: 0) ||
                    $this->banService->checkUsername($username, forumId: 0)
                ) {
                    $error = Lang::get('auth.error_registration_blocked');
                }
            }

            if ($error === null) {
                $requireConfirmation = (bool) $this->config->get('require_confirmation', false);
                $requireModApproval  = (bool) ($this->settings->getSetting('require_mod_approval') ?? false);
                $baseUrl             = (string) $this->config->get('base_url', '');
                $this->authService->register(
                    $username, $email, $password, $requireConfirmation, $requireModApproval, $baseUrl
                );

                if ($requireConfirmation) {
                    return $this->respond($this->render('auth/confirm_pending.html.twig', [
                        'email' => $email,
                    ]));
                }

                if ($requireModApproval) {
                    return $this->respond($this->render('auth/pending_approval.html.twig', []));
                }

                $this->authService->login($username, $password);
                return $this->redirect('/');
            }
        }

        return $this->respond($this->render('auth/register.html.twig', [
            'errors' => $error !== null ? [$error] : [],
        ]));
    }

    // -------------------------------------------------------------------------
    // Email confirmation
    // -------------------------------------------------------------------------

    public function confirmEmail(Request $request): Response
    {
        $token = trim($request->query['token'] ?? '');
        $user  = $this->authService->confirmEmail($token);

        if ($user !== null && $user->active === UserMapper::ACTIVE) {
            // Confirming no longer logs the visitor in (see
            // AuthService::confirmEmail), so send them to the login form.
            return $this->redirect('/login?confirmed=1');
        }

        if ($user !== null) {
            // Email confirmed, but the account still needs moderator approval.
            return $this->respond($this->render('auth/pending_approval.html.twig', []));
        }

        return $this->respond($this->render('auth/confirm_email.html.twig', [
            'invalid' => true,
        ]));
    }

    public function resendConfirmation(Request $request): Response
    {
        $sent  = false;
        $error = null;

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            $email   = trim($request->post['email'] ?? '');
            $baseUrl = (string) $this->config->get('base_url', '');
            $wait    = $this->throttle->resetRetryAfter($request->server);

            if ($wait > 0) {
                // Shares the reset bucket — same inbox-flooding shape.
                $error = Lang::get('auth.error_throttled', ['seconds' => (string) $wait]);
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = Lang::get('auth.error_invalid_email');
            } else {
                $this->throttle->recordResetRequest($request->server);
                $this->authService->resendConfirmation($email, $baseUrl);
                $sent = true;
            }
        }

        return $this->respond($this->render('auth/resend_confirmation.html.twig', [
            'sent'   => $sent,
            'errors' => $error !== null ? [$error] : [],
        ]));
    }

    // -------------------------------------------------------------------------
    // Forgot password
    // -------------------------------------------------------------------------

    public function forgotPassword(Request $request): Response
    {
        if (Auth::isLoggedIn()) {
            return $this->redirect('/');
        }

        $sent  = false;
        $error = null;

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            $email = trim($request->post['email'] ?? '');
            $wait  = $this->throttle->resetRetryAfter($request->server);

            if ($wait > 0) {
                // Unauthenticated and it sends mail to an address the caller
                // chooses, so left open this is an inbox-flooding tool.
                $error = Lang::get('auth.error_throttled', ['seconds' => (string) $wait]);
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = Lang::get('auth.error_invalid_email');
            } else {
                $baseUrl = (string) $this->config->get('base_url', '');
                $this->throttle->recordResetRequest($request->server);
                $this->authService->requestPasswordReset($email, $baseUrl);
                $sent = true;
            }
        }

        return $this->respond($this->render('auth/forgot_password.html.twig', [
            'sent'   => $sent,
            'errors' => $error !== null ? [$error] : [],
        ]));
    }

    // -------------------------------------------------------------------------
    // Reset password
    // -------------------------------------------------------------------------

    public function resetPassword(Request $request): Response
    {
        if (Auth::isLoggedIn()) {
            return $this->redirect('/');
        }

        $service = $this->authService;

        // A token arriving in the query string is moved into the session and
        // the visitor is redirected to a clean URL. The link still reaches the
        // access log once when it is clicked, but after this it stops being a
        // working URL sitting in browser history, and the form submission below
        // no longer carries the token and so isn't logged with it a second time.
        $urlToken = trim($request->query['token'] ?? '');
        if ($urlToken !== '') {
            CsrfGuard::ensureSession();
            $_SESSION[self::RESET_TOKEN_KEY] = $urlToken;
            return $this->redirect('/reset-password');
        }

        CsrfGuard::ensureSession();
        $token = (string) ($_SESSION[self::RESET_TOKEN_KEY] ?? '');
        $user  = $token !== '' ? $service->validateResetToken($token) : null;
        $error = null;

        if ($user === null) {
            unset($_SESSION[self::RESET_TOKEN_KEY]);
            return $this->respond($this->render('auth/reset_password.html.twig', [
                'invalid' => true,
                'errors'  => [],
            ]));
        }

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }

            $password  = $request->post['password'] ?? '';
            $password2 = $request->post['password2'] ?? '';

            if (strlen($password) < 6) {
                $error = Lang::get('auth.error_password_min_length');
            } elseif ($password !== $password2) {
                $error = Lang::get('auth.error_passwords_mismatch');
            } else {
                $service->resetPassword($user, $password);
                unset($_SESSION[self::RESET_TOKEN_KEY]);
                return $this->redirect('/');
            }
        }

        return $this->respond($this->render('auth/reset_password.html.twig', [
            'invalid' => false,
            'errors'  => $error !== null ? [$error] : [],
        ]));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function validateRegistration(
        string $username,
        string $email,
        string $password,
        string $password2
    ): ?string {
        if ($username === '') {
            return Lang::get('auth.error_username_required');
        }
        if (strlen($username) < 2 || strlen($username) > 50) {
            return Lang::get('auth.error_username_length');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Lang::get('auth.error_email_required');
        }
        if (strlen($password) < 6) {
            return Lang::get('auth.error_password_min_length');
        }
        if ($password !== $password2) {
            return Lang::get('auth.error_passwords_mismatch');
        }

        // Check username not already taken
        $existing = $this->users->findByUsername($username);
        if ($existing !== null) {
            return Lang::get('auth.error_username_taken');
        }

        // And the address. UserController::settings() has always enforced this
        // when changing an email but registration never did, so two accounts
        // could share one — which makes findByEmail()'s "first row wins"
        // lookup pick between them arbitrarily, including where OAuth uses it
        // to decide which account an identity belongs to.
        if ($this->users->findByEmail($email) !== null) {
            return Lang::get('auth.error_email_taken');
        }

        return null;
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\Lang;
use Phorum\Core\RedirectGuard;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\BanMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\AuthService;
use Phorum\Service\BanService;
use Twig\Environment;

class AuthController extends Controller
{
    private readonly AuthService $authService;
    private readonly BanService  $banService;
    private readonly UserMapper  $users;

    public function __construct(
        Config       $config,
        Environment  $twig,
        ?AuthService $authService = null,
        ?BanService  $banService  = null,
        ?UserMapper  $users       = null,
    ) {
        parent::__construct($config, $twig);
        $this->authService = $authService ?? new AuthService(
            users:         new UserMapper(),
            secureCookies: (bool) $config->get('session_secure', false),
            config:        $config,
        );
        $this->banService  = $banService  ?? new BanService(new BanMapper());
        $this->users       = $users       ?? new UserMapper();
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

            if ($username === '' || $password === '') {
                $error = Lang::get('auth.error_missing_credentials');
            } else {
                $user = $this->authService->login($username, $password, $remember);
                if ($user === null) {
                    $error = Lang::get('auth.error_invalid_credentials');
                } else {
                    $redirect = RedirectGuard::sanitizePath($request->post['redirect'] ?? '/');
                    if ($user->force_password_change) {
                        return $this->redirect(RedirectGuard::changePasswordUrl($redirect));
                    }
                    return $this->redirect($redirect);
                }
            }
        }

        return $this->respond($this->render('auth/login.html.twig', [
            'errors'   => $error !== null ? [$error] : [],
            'redirect' => $request->query['redirect'] ?? '/',
        ]));
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    public function logout(Request $request): Response
    {
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
                $baseUrl             = (string) $this->config->get('base_url', '');
                $this->authService->register($username, $email, $password, $requireConfirmation, $baseUrl);

                if ($requireConfirmation) {
                    return $this->respond($this->render('auth/confirm_pending.html.twig', [
                        'email' => $email,
                    ]));
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

        if ($user !== null) {
            return $this->redirect('/');
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

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = Lang::get('auth.error_invalid_email');
            } else {
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

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = Lang::get('auth.error_invalid_email');
            } else {
                $baseUrl = (string) $this->config->get('base_url', '');
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

        $token   = trim($request->query['token'] ?? '');
        $service = $this->authService;
        $user    = $service->validateResetToken($token);
        $error   = null;

        if ($user === null) {
            return $this->respond($this->render('auth/reset_password.html.twig', [
                'invalid' => true,
                'token'   => '',
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
                return $this->redirect('/');
            }
        }

        return $this->respond($this->render('auth/reset_password.html.twig', [
            'invalid' => false,
            'token'   => $token,
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

        return null;
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\AdminAuth;
use Phorum\Core\AdminSecret;
use Phorum\Core\Config;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\LoginAttemptMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\LoginThrottleService;
use Twig\Environment;

class LoginController extends AdminController
{
    private readonly UserMapper $users;
    private readonly LoginThrottleService $throttle;

    public function __construct(
        Config                $config,
        Environment           $twig,
        ?UserMapper           $users    = null,
        ?LoginThrottleService $throttle = null,
    ) {
        parent::__construct($config, $twig);
        $this->users    = $users    ?? new UserMapper();
        // Shares LoginThrottleService (and its buckets) with the front-end
        // login: this is a second password-checking endpoint for the same
        // accounts, so throttling one and not the other throttles neither.
        $this->throttle = $throttle ?? new LoginThrottleService(new LoginAttemptMapper());
    }

    public function login(Request $request): Response
    {
        // Already authenticated — go straight to dashboard
        if (AdminAuth::user() !== null) {
            return $this->redirect('/admin');
        }

        // Without a usable admin_secret the session cookie can't be signed
        // safely, so refuse to log anyone in and say why — reaching AdminAuth
        // would otherwise throw and surface as an unexplained 500.
        $secretProblem = AdminSecret::problem($this->config);
        if ($secretProblem !== null) {
            return $this->respond($this->renderAdmin('admin/login.html.twig', [
                'error' => $secretProblem,
            ]), 503);
        }

        $error = '';

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $username = trim($request->post['username'] ?? '');
            $password = $request->post['password']       ?? '';

            $wait = $this->throttle->loginRetryAfter($username, $request->server);
            if ($wait > 0) {
                return $this->respond($this->renderAdmin('admin/login.html.twig', [
                    'error' => 'Too many attempts. Please wait ' . $wait . ' second(s) and try again.',
                ]), 429);
            }

            $user  = $this->users->findByUsername($username);

            $valid = $user !== null
                && $user->active === 1
                && $user->admin
                && password_verify($password, $user->password);

            // Legacy MD5 fallback (same as AuthService)
            if (!$valid && $user !== null && $user->admin && $user->active === 1) {
                if (hash_equals($user->password, md5($password))) {
                    $valid = true;
                    $user->password = password_hash($password, PASSWORD_BCRYPT);
                    $this->users->save($user);
                }
            }

            if ($valid && $user !== null) {
                $this->throttle->clearAccount($username);
                AdminAuth::login($user, $this->config);
                return $this->redirect('/admin');
            }

            $this->throttle->recordFailedLogin($username, $request->server);
            $error = 'Invalid credentials or insufficient privileges.';
        }

        return $this->respond($this->renderAdmin('admin/login.html.twig', ['error' => $error]));
    }

    public function logout(Request $request): Response
    {
        if (!$request->isPost()) {
            return $this->redirect('/admin');
        }
        if ($r = $this->checkCsrf($request)) { return $r; }
        AdminAuth::logout($this->config);
        return $this->redirect('/admin/login');
    }
}

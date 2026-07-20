<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers\Admin;

use Phorum\Core\AdminAuth;
use Phorum\Core\Config;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\UserMapper;
use Twig\Environment;

class LoginController extends AdminController
{
    private readonly UserMapper $users;

    public function __construct(
        Config      $config,
        Environment $twig,
        ?UserMapper $users = null,
    ) {
        parent::__construct($config, $twig);
        $this->users = $users ?? new UserMapper();
    }

    public function login(Request $request): Response
    {
        // Already authenticated — go straight to dashboard
        if (AdminAuth::user() !== null) {
            return $this->redirect('/admin');
        }

        $error = '';

        if ($request->isPost()) {
            if ($r = $this->checkCsrf($request)) { return $r; }
            $username = trim($request->post['username'] ?? '');
            $password = $request->post['password']       ?? '';

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
                AdminAuth::login($user, $this->config);
                return $this->redirect('/admin');
            }

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

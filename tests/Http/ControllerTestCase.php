<?php
declare(strict_types=1);

namespace Phorum\Tests\Http;

use PHPUnit\Framework\TestCase;
use Phorum\Core\AdminAuth;
use Phorum\Core\Auth;
use Phorum\Core\Config;
use Phorum\Core\CsrfGuard;
use Phorum\Core\Impersonation;
use Phorum\Core\Lang;
use Phorum\Hook\HookDispatcher;
use Phorum\Http\Request;
use Phorum\Model\Forum;
use Phorum\Model\Message;
use Phorum\Model\User;
use Twig\Environment;

abstract class ControllerTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 2));
        }
        require_once ROOT_PATH . '/src/Hook/functions.php';
        HookDispatcher::reset();
        Lang::load('en');
        Auth::clear();
        $this->resetAdminAuth();
        $this->resetImpersonation();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
        Auth::clear();
        $this->resetAdminAuth();
        $this->resetImpersonation();
        $_SESSION = [];
    }

    protected function makeConfig(array $values = []): Config
    {
        $defaults = [
            'site_name'            => 'TestForum',
            'template'             => 'emerald',
            'session_secure'       => false,
            'base_url'             => 'http://localhost',
            'require_confirmation' => false,
            'track_edits'          => false,
            'admin_secret'         => 'test-secret',
        ];
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $values[$key] ?? $defaults[$key] ?? $default
        );
        return $config;
    }

    protected function makeTwig(string $renderReturn = '<html>test</html>'): Environment
    {
        $twig = $this->createMock(Environment::class);
        // Non-FilesystemLoader causes activateTheme() to short-circuit — no ROOT_PATH needed.
        $twig->method('getLoader')->willReturn(
            $this->createMock(\Twig\Loader\LoaderInterface::class)
        );
        $twig->method('render')->willReturn($renderReturn);
        return $twig;
    }

    protected function makeUser(int $id = 1, bool $admin = false): User
    {
        $user               = new User();
        $user->user_id      = $id;
        $user->username     = 'user' . $id;
        $user->display_name = 'User ' . $id;
        $user->email        = "user{$id}@example.com";
        $user->active       = 1;
        $user->admin        = $admin ? 1 : 0;
        $user->password     = password_hash('secret', PASSWORD_BCRYPT);
        return $user;
    }

    protected function makeForum(int $id = 1, array $override = []): Forum
    {
        $forum                   = new Forum();
        $forum->forum_id         = $id;
        $forum->name             = 'Test Forum';
        $forum->folder_flag      = 0;
        $forum->active           = 1;
        $forum->thread_count     = 0;
        $forum->list_length_flat = 25;
        $forum->template         = '';
        foreach ($override as $k => $v) {
            $forum->$k = $v;
        }
        return $forum;
    }

    protected function makeMessage(int $id = 1, int $forumId = 1, int $threadId = 0): Message
    {
        $msg              = new Message();
        $msg->message_id  = $id;
        $msg->forum_id    = $forumId;
        $msg->thread      = $threadId ?: $id;
        $msg->parent_id   = 0;
        $msg->subject     = 'Test Subject';
        $msg->body        = 'Test body.';
        $msg->author      = 'user1';
        $msg->user_id     = 1;
        $msg->status      = 2;
        return $msg;
    }

    /**
     * Build a POST Request with a valid CSRF token included.
     * Use for testing action methods that call checkCsrf().
     */
    protected function makePostRequest(array $post = [], array $tokens = [], array $server = []): Request
    {
        $token = CsrfGuard::token();
        return new Request(
            post:   array_merge([CsrfGuard::fieldName() => $token], $post),
            server: array_merge(['REQUEST_METHOD' => 'POST'], $server),
            tokens: $tokens,
        );
    }

    protected function makeGetRequest(array $query = [], array $tokens = [], array $server = []): Request
    {
        return new Request(
            query:  $query,
            server: array_merge(['REQUEST_METHOD' => 'GET'], $server),
            tokens: $tokens,
        );
    }

    /**
     * Set the active admin user via reflection to avoid setcookie() side effects.
     */
    protected function setAdminUser(User $user): void
    {
        $ref = new \ReflectionProperty(AdminAuth::class, 'admin');
        $ref->setValue(null, $user);
    }

    /**
     * Reset AdminAuth static state via reflection to avoid setcookie() side effects.
     */
    protected function resetAdminAuth(): void
    {
        $ref = new \ReflectionProperty(AdminAuth::class, 'admin');
        $ref->setValue(null, null);
    }

    /**
     * Reset Impersonation static state via reflection to avoid setcookie() side effects.
     */
    protected function resetImpersonation(): void
    {
        $ref = new \ReflectionProperty(Impersonation::class, 'admin');
        $ref->setValue(null, null);
        unset($_COOKIE['phorum_impersonate']);
    }
}

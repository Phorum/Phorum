<?php
declare(strict_types=1);

namespace Phorum\Core;

use PageMill\Router\Router;
use Phorum\Core\AdminAuth;
use Phorum\Core\Impersonation;
use Phorum\Core\Lang;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\SettingMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Twig\PhorumExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class App
{
    private Router      $router;
    private Environment $twig;

    public function __construct(private readonly Config $config)
    {
        $this->initTwig();    // registers Markdown format hook
        $this->initModules(); // registers BBCode format hook (and future modules)
        $this->initRouter();
    }

    public function run(): void
    {
        $uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $basePath = (string) $this->config->get('base_path', '');
        if ($basePath !== '' && str_starts_with((string) $uri, $basePath)) {
            $uri = substr((string) $uri, strlen($basePath)) ?: '/';
        }

        $installed = $this->isInstalled();

        if (!str_starts_with((string) $uri, '/install') && !$installed) {
            header('Location: ' . $basePath . '/install');
            return;
        }

        // Auth tables only exist after installation
        if ($installed) {
            Auth::initialize(new UserMapper());
            AdminAuth::initialize($this->config);
            Impersonation::initialize($this->config);
            $this->initLang();
            phorum_api_hook('common_post_user', Auth::user());
        } else {
            Lang::load((string) $this->config->get('language', 'en'));
        }

        $route = $this->router->match((string) $uri);

        if (empty($route)) {
            $this->respond($this->twig->render('error/404.html.twig', [
                'site_name' => $this->config->get('site_name', 'Phorum'),
                'user'      => $installed ? Auth::user() : null,
            ]), 404);
            return;
        }

        phorum_api_hook('common_pre', $route);

        $hasForum = !empty($route['tokens']['forum_id']);
        if (!$hasForum) {
            phorum_api_hook('common_no_forum', $route);
        }

        phorum_api_hook('common', $route);

        $this->dispatch($route);
    }

    private function dispatch(array $route): void
    {
        [$class, $method] = $this->resolveAction($route['action']);

        if (!class_exists($class)) {
            $this->respond($this->twig->render('error/404.html.twig'), 404);
            return;
        }

        $request    = new Request(
            query:  $_GET    ?? [],
            post:   $_POST   ?? [],
            server: $_SERVER ?? [],
            tokens: $route['tokens'] ?? [],
            files:  $_FILES  ?? [],
        );
        $controller = new $class($this->config, $this->twig);
        $response   = $controller->$method($request);
        if ($response instanceof Response) {
            $this->sendResponse($response);
        }
    }

    private function sendResponse(Response $response): void
    {
        http_response_code($response->status);
        foreach ($response->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        if ($response->body !== '') {
            echo $response->body;
        }
    }

    private function resolveAction(string $action): array
    {
        [$controller, $method] = str_contains($action, '@')
            ? explode('@', $action, 2)
            : [$action, 'index'];

        return ["Phorum\\Http\\Controllers\\{$controller}", $method];
    }

    private function initRouter(): void
    {
        $routes       = require ROOT_PATH . '/etc/routes.php';
        $this->router = new Router($routes);
    }

    private function initTwig(): void
    {
        $loader     = new FilesystemLoader(ROOT_PATH . '/templates');
        $this->twig = new Environment($loader, [
            'cache' => $this->config->get('twig_cache', false),
            'debug' => $this->config->get('debug', false),
        ]);
        // PhorumExtension constructor registers the Markdown format hook handler
        $this->twig->addExtension(new PhorumExtension($this->config));
    }

    private function initModules(): void
    {
        $enabled = ['bbcode']; // default if settings table is unavailable

        try {
            $mods = (new SettingMapper())->getSetting('mods');
            if (is_array($mods)) {
                $enabled = array_keys(array_filter($mods));
            }
        } catch (\Throwable) {
            // Settings table not yet created (fresh install) — fall back to default
        }

        foreach ($enabled as $name) {
            if (!preg_match('/^[a-z0-9_-]+$/i', (string) $name)) {
                continue;
            }
            $file = ROOT_PATH . '/mods/' . $name . '/' . $name . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    private function initLang(): void
    {
        $locale = (string) $this->config->get('language', 'en');
        try {
            $setting = (new SettingMapper())->getSetting('language');
            if ($setting !== null) {
                $locale = (string) $setting;
            }
        } catch (\Throwable) {
        }
        Lang::load($locale);
    }

    private function isInstalled(): bool
    {
        try {
            $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
            $db     = defined('PHORUM_DB')        ? PHORUM_DB        : 'phorum';
            $rows   = \DealNews\DB\CRUD::factory($db)->runFetch(
                "SELECT data FROM {$prefix}_settings WHERE name = 'installed'",
                []
            );
            return !empty($rows) && !empty($rows[0]['data']);
        } catch (\Throwable) {
            return false;
        }
    }

    private function respond(string $html, int $status = 200): void
    {
        http_response_code($status);
        echo $html;
    }
}

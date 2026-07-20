<?php
declare(strict_types=1);

namespace Phorum\Core;

use PageMill\Router\Router;
use Phorum\Core\AdminAuth;
use Phorum\Core\Impersonation;
use Phorum\Core\Lang;
use Phorum\Core\RedirectGuard;
use Phorum\Core\SchemaMigrator;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\SettingMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Service\SiteStatusService;
use Phorum\Twig\PhorumExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class App
{
    private Router      $router;
    private Environment $twig;

    /** Routes contributed by enabled modules' own routes.php, merged in by initRouter(). */
    private array $moduleRoutes = [];

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

        $state     = $this->bootState();
        $installed = $state === 'installed';

        if ($state === 'fresh' && !str_starts_with((string) $uri, '/install')) {
            header('Location: ' . $basePath . '/install');
            return;
        }

        if ($state === 'needs_upgrade' && !str_starts_with((string) $uri, '/upgrade')) {
            header('Location: ' . $basePath . '/upgrade');
            return;
        }

        // Auth tables only exist after installation
        if ($installed) {
            $this->selfHealSchema();
            Auth::initialize(new UserMapper());
            AdminAuth::initialize($this->config);
            Impersonation::initialize($this->config);
            SiteStatus::initialize(new SiteStatusService());
            FeedStatus::initialize(new SettingMapper());
            SiteSettings::initialize(new SettingMapper(), (string) $this->config->get('site_name', 'Phorum'));
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

        if ($installed) {
            foreach ($this->routeGates((string) $uri) as $gate) {
                if ($gate($route)) {
                    return;
                }
            }
        }

        phorum_api_hook('common_pre', $route);

        $hasForum = !empty($route['tokens']['forum_id']);
        if (!$hasForum) {
            phorum_api_hook('common_no_forum', $route);
        }

        phorum_api_hook('common', $route);

        $this->dispatch($route);
    }

    /**
     * Route gates run in order for every installed-site request, right after
     * routing and before dispatch. Each gate independently decides whether
     * to exempt a given route (their exemption rules genuinely differ — see
     * each method's own docblock) and whether to block the request, having
     * already sent its own response. Adding a new gate is one entry here
     * instead of a new private method plus a new call site in run().
     *
     * @return array<callable(array $route): bool>
     */
    private function routeGates(string $uri): array
    {
        return [
            fn(array $route) => $this->blockedBySiteStatus($route),
            fn(array $route) => $this->blockedByForcePasswordChange($route, $uri),
        ];
    }

    /**
     * Site-wide status gate, ported from Phorum 6's $PHORUM['status'].
     * The admin panel and theme assets are always exempt (an admin must
     * always be able to reach /admin to flip the status back). Returns true
     * (and has already sent a response) if the request should stop here.
     */
    private function blockedBySiteStatus(array $route): bool
    {
        $action = (string) ($route['action'] ?? '');
        if (str_starts_with($action, 'Admin\\') || str_starts_with($action, 'ThemeController@')) {
            return false;
        }

        $status = SiteStatus::current();

        if ($status === SiteStatusService::DISABLED) {
            $this->respondSiteStatus('error.disabled_title', 'error.disabled_message', 503);
            return true;
        }

        if ($status === SiteStatusService::ADMIN_ONLY && !Auth::isAdmin()) {
            $this->respondSiteStatus('error.admin_only_title', 'error.admin_only_message', 503);
            return true;
        }

        if ($status === SiteStatusService::READ_ONLY
            && strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        ) {
            $this->respondSiteStatus('error.read_only_title', 'error.read_only_message', 403);
            return true;
        }

        return false;
    }

    /**
     * Force a logged-in user with force_password_change set to change their
     * password before doing anything else — mirrors Phorum 6's own
     * every-page-load enforcement. Login/logout, the change-password page
     * itself, and theme assets are exempt.
     *
     * Deliberately NOT exempt: Admin\* routes. Unlike blockedBySiteStatus()
     * (where an admin must always reach /admin to flip site status back),
     * force_password_change is a requirement on that specific account —
     * there's no equivalent reason for a flagged admin to bypass it, and an
     * admin who is also flagged could otherwise use the whole admin panel
     * indefinitely without ever changing their password.
     *
     * $uri must already have base_path stripped (as run() does before
     * calling this) — Controller::redirect()/the header below both add
     * base_path back on, so starting from the stripped path avoids doubling it.
     */
    private function blockedByForcePasswordChange(array $route, string $uri): bool
    {
        $action = (string) ($route['action'] ?? '');
        if (str_starts_with($action, 'ThemeController@')
            || str_starts_with($action, 'AuthController@')
            || $action === 'UserController@forcePasswordChange'
        ) {
            return false;
        }

        $user = Auth::user();
        if ($user === null || !$user->force_password_change) {
            return false;
        }

        $query    = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $current  = $uri . ($query !== '' ? '?' . $query : '');
        $basePath = (string) $this->config->get('base_path', '');
        header('Location: ' . $basePath . RedirectGuard::changePasswordUrl($current));
        return true;
    }

    private function respondSiteStatus(string $titleKey, string $messageKey, int $status): void
    {
        // Only reached via routeGates(), which only runs when $installed —
        // SiteSettings is already initialized by that point (see run()).
        $this->respond($this->twig->render('error/site_status.html.twig', [
            'site_name' => SiteSettings::name(),
            'theme'     => (string) $this->config->get('template', 'emerald'),
            'user'      => Auth::user(),
            'title'     => Lang::get($titleKey),
            'message'   => Lang::get($messageKey),
        ]), $status);
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

    /**
     * Resolve a route's 'action' string to a [class, method] pair. A leading
     * backslash marks a fully-qualified class name used as-is — the only way
     * a module (not PSR-4 autoloaded under Phorum\Http\Controllers\) can
     * supply its own admin controller, e.g. '\Phorum\Mod\Webhooks\Admin\WebhooksController@index'.
     * Everything else keeps the normal Phorum\Http\Controllers\ prefix.
     */
    private function resolveAction(string $action): array
    {
        [$controller, $method] = str_contains($action, '@')
            ? explode('@', $action, 2)
            : [$action, 'index'];

        $class = str_starts_with($controller, '\\')
            ? ltrim($controller, '\\')
            : "Phorum\\Http\\Controllers\\{$controller}";

        return [$class, $method];
    }

    private function initRouter(): void
    {
        $routes       = require ROOT_PATH . '/etc/routes.php';
        $this->router = new Router(array_merge($routes, $this->moduleRoutes));
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

            // Optional: a module can ship its own routes.php returning the
            // same route-array shape as etc/routes.php — merged in below by
            // initRouter(), so a module never needs a core routes.php edit.
            $routesFile = ROOT_PATH . '/mods/' . $name . '/routes.php';
            if (file_exists($routesFile)) {
                $moduleRoutes = require $routesFile;
                if (is_array($moduleRoutes)) {
                    $this->moduleRoutes = array_merge($this->moduleRoutes, $moduleRoutes);
                }
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

    /**
     * Determine what state the database is in:
     *   - 'installed'      a fully set up Phorum 10 database.
     *   - 'needs_upgrade'  an existing Phorum 6 database (has its own
     *                      'internal_version' setting — present in every
     *                      Phorum 6 database, fresh or upgraded) that has
     *                      never been through the Phorum 10 upgrade flow.
     *   - 'fresh'          no settings table, or a settings table with
     *                      neither marker — a brand new database.
     */
    private function bootState(): string
    {
        $prefix = defined('PHORUM_DB_PREFIX') ? PHORUM_DB_PREFIX : 'phorum';
        $db     = defined('PHORUM_DB')        ? PHORUM_DB        : 'phorum';

        try {
            $rows = \DealNews\DB\CRUD::factory($db)->runFetch(
                "SELECT name, data FROM {$prefix}_settings WHERE name IN ('installed', 'internal_version')",
                []
            );
        } catch (\Throwable) {
            return 'fresh';
        }

        foreach ($rows ?: [] as $row) {
            if ($row['name'] === 'installed' && !empty($row['data'])) {
                return 'installed';
            }
        }
        foreach ($rows ?: [] as $row) {
            if ($row['name'] === 'internal_version') {
                return 'needs_upgrade';
            }
        }
        return 'fresh';
    }

    /**
     * For already-installed Phorum 10 sites: if a newer release added tables
     * or columns since this database last caught up, apply both — safe and
     * idempotent (see SchemaInstaller/SchemaPatcher) — and record the new
     * version. Wrapped so a failure here never breaks the request; it just
     * retries on the next hit.
     */
    private function selfHealSchema(): void
    {
        try {
            $settings = new SettingMapper();
            if ($settings->getSetting('schema_version') === Version::CURRENT) {
                return;
            }
            (new SchemaMigrator(settings: $settings))->bringUpToDate();
        } catch (\Throwable) {
        }
    }

    private function respond(string $html, int $status = 200): void
    {
        http_response_code($status);
        echo $html;
    }
}

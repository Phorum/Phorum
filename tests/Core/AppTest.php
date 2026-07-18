<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use PHPUnit\Framework\TestCase;
use Phorum\Core\App;
use Phorum\Core\Config;
use Phorum\Hook\HookDispatcher;

class AppTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 2));
        }
        require_once ROOT_PATH . '/src/Hook/functions.php';
        HookDispatcher::reset();
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    /**
     * PageMill\Router\Router::match() returns an empty array — never null —
     * when nothing matches. run() used to check `$route === null`, which can
     * never be true, so an unmatched URL fell through to dispatch() with an
     * empty $route and fataled on $route['action']. It should render a 404.
     */
    public function testRunRendersNotFoundForUnmatchedRouteInsteadOfFatalError(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => match ($key) {
                'base_path' => '',
                'site_name' => 'Test Phorum',
                default     => $default,
            }
        );

        $_SERVER['REQUEST_URI'] = '/install/this-does-not-exist';

        $app = new App($config);

        ob_start();
        $app->run();
        $output = ob_get_clean();

        $this->assertSame(404, http_response_code());
        $this->assertStringContainsString('error-page', $output);
    }

    /**
     * resolveAction() prefixes a normal action with Phorum\Http\Controllers\,
     * but a leading backslash marks a fully-qualified class name to use
     * as-is — the only way a module (not PSR-4 autoloaded under that
     * namespace) can supply its own admin controller, e.g. the webhooks
     * module's '\Phorum\Mod\Webhooks\Admin\WebhooksController@index'.
     */
    public function testResolveActionPrefixesNormalActionsWithControllersNamespace(): void
    {
        $app = $this->makeAppForResolveAction();

        [$class, $method] = $this->invokeResolveAction($app, 'Admin\BanController@index');

        $this->assertSame('Phorum\Http\Controllers\Admin\BanController', $class);
        $this->assertSame('index', $method);
    }

    public function testResolveActionUsesFullyQualifiedActionAsIs(): void
    {
        $app = $this->makeAppForResolveAction();

        [$class, $method] = $this->invokeResolveAction(
            $app,
            '\Phorum\Mod\Webhooks\Admin\WebhooksController@index'
        );

        $this->assertSame('Phorum\Mod\Webhooks\Admin\WebhooksController', $class);
        $this->assertSame('index', $method);
    }

    public function testResolveActionDefaultsToIndexMethodWhenNoneGiven(): void
    {
        $app = $this->makeAppForResolveAction();

        [$class, $method] = $this->invokeResolveAction($app, 'Admin\BanController');

        $this->assertSame('Phorum\Http\Controllers\Admin\BanController', $class);
        $this->assertSame('index', $method);
    }

    /**
     * A module contributes routes via its own mods/{name}/routes.php,
     * returning the same array shape as etc/routes.php — initModules()
     * merges it into $moduleRoutes, and initRouter() merges that into the
     * Router alongside the core route table, with no core routes.php edit
     * required. Verified here by injecting a route directly into the
     * private $moduleRoutes property (module enablement itself goes through
     * a real DB call this test suite doesn't stand up) and re-running
     * initRouter() to confirm it ends up in the constructed Router.
     */
    public function testModuleRoutesAreMergedIntoTheRouter(): void
    {
        $app = $this->makeAppForResolveAction();

        $moduleRoute = [
            'type'    => 'exact',
            'pattern' => '/admin/webhooks',
            'action'  => '\Phorum\Mod\Webhooks\Admin\WebhooksController@index',
        ];

        $routesProp = new \ReflectionProperty(App::class, 'moduleRoutes');
        $routesProp->setValue($app, [$moduleRoute]);

        $ref = new \ReflectionMethod(App::class, 'initRouter');
        $ref->invoke($app);

        $router     = (new \ReflectionProperty(App::class, 'router'))->getValue($app);
        $allRoutes  = $router->getRoutes();

        $this->assertContains($moduleRoute, $allRoutes);
        // The core route table is still present alongside it.
        $this->assertGreaterThan(1, count($allRoutes));
    }

    /** mods/webhooks/routes.php itself: a plain data file, no bootstrap side effects. */
    public function testWebhooksModuleRoutesFileReturnsExpectedRoutes(): void
    {
        $routes = require dirname(__DIR__, 2) . '/mods/webhooks/routes.php';

        $this->assertIsArray($routes);
        $patterns = array_column($routes, 'pattern');
        $this->assertContains('/admin/webhooks', $patterns);
        $this->assertContains('/admin/webhooks/create', $patterns);

        foreach ($routes as $route) {
            $this->assertStringStartsWith(
                '\Phorum\Mod\Webhooks\Admin\WebhooksController@',
                $route['action']
            );
        }
    }

    private function makeAppForResolveAction(): App
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => match ($key) {
                'base_path' => '',
                'site_name' => 'Test Phorum',
                default     => $default,
            }
        );
        return new App($config);
    }

    private function invokeResolveAction(App $app, string $action): array
    {
        $ref = new \ReflectionMethod(App::class, 'resolveAction');
        return $ref->invoke($app, $action);
    }
}

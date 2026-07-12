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
}

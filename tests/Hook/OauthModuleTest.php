<?php
declare(strict_types=1);

namespace Phorum\Tests\Hook;

use Phorum\Core\Config;
use Phorum\Hook\HookDispatcher;
use Phorum\Mapper\SettingMapper;
use Phorum\Mod\Oauth\OauthHooks;
use PHPUnit\Framework\TestCase;

/**
 * Tests the auth_login_buttons hook that templates/auth/login.html.twig
 * calls via the hook() Twig function — using the real OauthHooks::register()
 * wiring, following the pattern established by WebhooksModuleTest.
 */
class OauthModuleTest extends TestCase
{
    private static bool $moduleLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$moduleLoaded) {
            $base = dirname(__DIR__, 2) . '/mods/oauth';
            require_once $base . '/OauthEmailNotVerifiedException.php';
            require_once $base . '/OauthIdentity.php';
            require_once $base . '/OauthIdentityMapper.php';
            require_once $base . '/OauthService.php';
            require_once $base . '/OauthHooks.php';
            self::$moduleLoaded = true;
        }
    }

    protected function tearDown(): void
    {
        HookDispatcher::reset();
    }

    private function makeConfig(): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(fn(string $k, mixed $d = null) => match ($k) {
            'base_url'  => 'http://localhost',
            'base_path' => '',
            default     => $d,
        });
        return $config;
    }

    private function makeSettings(array $values): SettingMapper
    {
        $settings = $this->createMock(SettingMapper::class);
        $settings->method('getSetting')->willReturnCallback(fn(string $k) => $values[$k] ?? null);
        return $settings;
    }

    private function registerWith(array $settingsValues): HookDispatcher
    {
        HookDispatcher::reset();
        $hooks = HookDispatcher::getInstance();
        OauthHooks::register($this->makeConfig(), $hooks, $this->makeSettings($settingsValues));
        return $hooks;
    }

    public function testAuthLoginButtonsHookIsRegistered(): void
    {
        $hooks = $this->registerWith([]);
        $this->assertTrue($hooks->hasHook('auth_login_buttons'));
    }

    public function testReturnsEmptyStringWhenNeitherProviderConfigured(): void
    {
        $hooks  = $this->registerWith([]);
        $result = $hooks->dispatch('auth_login_buttons', ['redirect' => '/']);
        $this->assertSame('', $result);
    }

    public function testReturnsGoogleButtonWhenGoogleConfigured(): void
    {
        $hooks  = $this->registerWith([
            'oauth_google_enabled'       => 1,
            'oauth_google_client_id'     => 'id',
            'oauth_google_client_secret' => 'secret',
        ]);
        $result = $hooks->dispatch('auth_login_buttons', ['redirect' => '/forum/1']);

        $this->assertStringContainsString('oauth-login-google', $result);
        $this->assertStringContainsString('/auth/oauth/google?redirect=', $result);
        $this->assertStringNotContainsString('oauth-login-github', $result);
    }

    public function testReturnsBothButtonsWhenBothConfigured(): void
    {
        $hooks  = $this->registerWith([
            'oauth_google_enabled'       => 1,
            'oauth_google_client_id'     => 'id',
            'oauth_google_client_secret' => 'secret',
            'oauth_github_enabled'       => 1,
            'oauth_github_client_id'     => 'id',
            'oauth_github_client_secret' => 'secret',
        ]);
        $result = $hooks->dispatch('auth_login_buttons', ['redirect' => '/']);

        $this->assertStringContainsString('oauth-login-google', $result);
        $this->assertStringContainsString('oauth-login-github', $result);
    }

    public function testEncodesRedirectParamInButtonHref(): void
    {
        $hooks  = $this->registerWith([
            'oauth_google_enabled'       => 1,
            'oauth_google_client_id'     => 'id',
            'oauth_google_client_secret' => 'secret',
        ]);
        $result = $hooks->dispatch('auth_login_buttons', ['redirect' => '/forum/1?x=y']);

        $this->assertStringContainsString(rawurlencode('/forum/1?x=y'), $result);
    }

    public function testUnclaimedDispatchWithNoModuleReturnsInputUnchanged(): void
    {
        // Sanity check on the hook() Twig function's safety guard: with no
        // handler registered at all, dispatch() returns $data unchanged. As
        // long as callers pass an array (not a bare string) as $data, this
        // can never leak into the rendered page — hook()'s
        // is_string($result) ? $result : '' guard coerces it to ''.
        HookDispatcher::reset();
        $result = HookDispatcher::getInstance()->dispatch('auth_login_buttons', ['redirect' => '/should/not/leak']);
        $this->assertIsArray($result);
    }
}

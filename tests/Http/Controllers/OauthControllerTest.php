<?php
declare(strict_types=1);

namespace Phorum\Tests\Http\Controllers;

use Phorum\Http\Request;
use Phorum\Mod\Oauth\Controllers\OauthController;
use Phorum\Mod\Oauth\OauthEmailNotVerifiedException;
use Phorum\Mod\Oauth\OauthService;
use Phorum\Model\User;
use Phorum\Service\AuthService;
use Phorum\Tests\Http\ControllerTestCase;

class OauthControllerTest extends ControllerTestCase
{
    public static function setUpBeforeClass(): void
    {
        $base = dirname(__DIR__, 3) . '/mods/oauth';
        require_once $base . '/OauthEmailNotVerifiedException.php';
        require_once $base . '/OauthIdentity.php';
        require_once $base . '/OauthIdentityMapper.php';
        require_once $base . '/OauthService.php';
        require_once $base . '/Controllers/OauthController.php';
    }

    private function makeController(array $deps = []): OauthController
    {
        return new OauthController(
            config:      $this->makeConfig(),
            twig:        $this->makeTwig(),
            oauth:       $deps['oauth']       ?? $this->createMock(OauthService::class),
            authService: $deps['authService'] ?? $this->createMock(AuthService::class),
        );
    }

    // -------------------------------------------------------------------------
    // start
    // -------------------------------------------------------------------------

    public function testStartReturns404ForUnknownProvider(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->start(new Request(tokens: ['provider' => 'facebook']));
        $this->assertSame(404, $response->status);
    }

    public function testStartRedirectsToLoginWhenProviderNotConfigured(): void
    {
        $oauth = $this->createMock(OauthService::class);
        $oauth->method('isConfigured')->willReturn(false);

        $ctrl     = $this->makeController(['oauth' => $oauth]);
        $response = $ctrl->start(new Request(tokens: ['provider' => 'google']));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('oauth_error=not_configured', $response->headers['Location']);
    }

    public function testStartRedirectsToAuthorizationUrlAndStoresStateInSession(): void
    {
        $oauth = $this->createMock(OauthService::class);
        $oauth->method('isConfigured')->willReturn(true);
        $oauth->method('authorizationUrl')->with('google')->willReturn([
            'url'   => 'https://accounts.google.com/authorize?foo=bar',
            'state' => 'random-state-value',
        ]);

        $ctrl     = $this->makeController(['oauth' => $oauth]);
        $response = $ctrl->start(new Request(
            tokens: ['provider' => 'google'],
            query:  ['redirect' => '/forum/1'],
        ));

        $this->assertSame(302, $response->status);
        $this->assertSame('https://accounts.google.com/authorize?foo=bar', $response->headers['Location']);
        $this->assertSame('random-state-value', $_SESSION['oauth_state']);
        $this->assertSame('google', $_SESSION['oauth_provider']);
        $this->assertSame('/forum/1', $_SESSION['oauth_redirect']);
    }

    public function testStartSanitizesRedirectAgainstOpenRedirect(): void
    {
        $oauth = $this->createMock(OauthService::class);
        $oauth->method('isConfigured')->willReturn(true);
        $oauth->method('authorizationUrl')->willReturn(['url' => 'https://example.test/authorize', 'state' => 's']);

        $ctrl = $this->makeController(['oauth' => $oauth]);
        $ctrl->start(new Request(tokens: ['provider' => 'google'], query: ['redirect' => '//evil.com']));

        $this->assertSame('/', $_SESSION['oauth_redirect']);
    }

    // -------------------------------------------------------------------------
    // callback
    // -------------------------------------------------------------------------

    public function testCallbackReturns404ForUnknownProvider(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->callback(new Request(tokens: ['provider' => 'facebook']));
        $this->assertSame(404, $response->status);
    }

    public function testCallbackRedirectsToLoginOnProviderErrorParam(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->callback(new Request(
            tokens: ['provider' => 'google'],
            query:  ['error' => 'access_denied'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('oauth_error=provider_error', $response->headers['Location']);
    }

    public function testCallbackRedirectsToLoginOnStateMismatch(): void
    {
        $_SESSION['oauth_state']    = 'expected-state';
        $_SESSION['oauth_provider'] = 'google';

        $ctrl     = $this->makeController();
        $response = $ctrl->callback(new Request(
            tokens: ['provider' => 'google'],
            query:  ['state' => 'wrong-state', 'code' => 'abc'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('oauth_error=state_mismatch', $response->headers['Location']);
    }

    public function testCallbackRedirectsToLoginWhenNoStateStashed(): void
    {
        $ctrl     = $this->makeController();
        $response = $ctrl->callback(new Request(
            tokens: ['provider' => 'google'],
            query:  ['state' => 'anything', 'code' => 'abc'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('oauth_error=state_mismatch', $response->headers['Location']);
    }

    public function testCallbackRedirectsToLoginOnTokenExchangeFailure(): void
    {
        $_SESSION['oauth_state']    = 'state1';
        $_SESSION['oauth_provider'] = 'google';
        $_SESSION['oauth_redirect'] = '/';

        $oauth = $this->createMock(OauthService::class);
        $oauth->method('exchangeCode')->willThrowException(new \RuntimeException('token exchange failed'));

        $ctrl     = $this->makeController(['oauth' => $oauth]);
        $response = $ctrl->callback(new Request(
            tokens: ['provider' => 'google'],
            query:  ['state' => 'state1', 'code' => 'abc'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('oauth_error=token_exchange_failed', $response->headers['Location']);
    }

    public function testCallbackRedirectsToLoginOnUnverifiedEmail(): void
    {
        $_SESSION['oauth_state']    = 'state1';
        $_SESSION['oauth_provider'] = 'google';
        $_SESSION['oauth_redirect'] = '/';

        $oauth = $this->createMock(OauthService::class);
        $oauth->method('resolveUser')->willThrowException(new OauthEmailNotVerifiedException('google'));

        $ctrl     = $this->makeController(['oauth' => $oauth]);
        $response = $ctrl->callback(new Request(
            tokens: ['provider' => 'google'],
            query:  ['state' => 'state1', 'code' => 'abc'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('oauth_error=email_not_verified', $response->headers['Location']);
    }

    public function testCallbackRedirectsToLoginOnGenericResolveFailure(): void
    {
        $_SESSION['oauth_state']    = 'state1';
        $_SESSION['oauth_provider'] = 'google';
        $_SESSION['oauth_redirect'] = '/';

        $oauth = $this->createMock(OauthService::class);
        $oauth->method('resolveUser')->willThrowException(new \RuntimeException('boom'));

        $ctrl     = $this->makeController(['oauth' => $oauth]);
        $response = $ctrl->callback(new Request(
            tokens: ['provider' => 'google'],
            query:  ['state' => 'state1', 'code' => 'abc'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('oauth_error=login_failed', $response->headers['Location']);
    }

    public function testCallbackRedirectsToLoginWhenResolvedUserIsInactive(): void
    {
        $_SESSION['oauth_state']    = 'state1';
        $_SESSION['oauth_provider'] = 'google';
        $_SESSION['oauth_redirect'] = '/';

        $user         = new User();
        $user->active = 0;

        $oauth = $this->createMock(OauthService::class);
        $oauth->method('resolveUser')->willReturn($user);

        $ctrl     = $this->makeController(['oauth' => $oauth]);
        $response = $ctrl->callback(new Request(
            tokens: ['provider' => 'google'],
            query:  ['state' => 'state1', 'code' => 'abc'],
        ));
        $this->assertSame(302, $response->status);
        $this->assertStringContainsString('oauth_error=account_inactive', $response->headers['Location']);
    }

    public function testCallbackLogsInAndRedirectsOnSuccess(): void
    {
        $_SESSION['oauth_state']    = 'state1';
        $_SESSION['oauth_provider'] = 'google';
        $_SESSION['oauth_redirect'] = '/forum/1';

        $user         = new User();
        $user->active = 1;

        $oauth = $this->createMock(OauthService::class);
        $oauth->method('exchangeCode')->willReturn(new \League\OAuth2\Client\Token\AccessToken(['access_token' => 'x']));
        $oauth->method('resolveUser')->willReturn($user);

        $auth = $this->createMock(AuthService::class);
        $auth->expects($this->once())->method('loginUser')->with($user, true);

        $ctrl     = $this->makeController(['oauth' => $oauth, 'authService' => $auth]);
        $response = $ctrl->callback(new Request(
            tokens: ['provider' => 'google'],
            query:  ['state' => 'state1', 'code' => 'abc'],
        ));

        $this->assertSame(302, $response->status);
        $this->assertSame('/forum/1', $response->headers['Location']);
    }

    public function testCallbackClearsSessionStateAfterUse(): void
    {
        $_SESSION['oauth_state']    = 'state1';
        $_SESSION['oauth_provider'] = 'google';
        $_SESSION['oauth_redirect'] = '/forum/1';

        $user         = new User();
        $user->active = 1;

        $oauth = $this->createMock(OauthService::class);
        $oauth->method('exchangeCode')->willReturn(new \League\OAuth2\Client\Token\AccessToken(['access_token' => 'x']));
        $oauth->method('resolveUser')->willReturn($user);

        $ctrl = $this->makeController(['oauth' => $oauth]);
        $ctrl->callback(new Request(
            tokens: ['provider' => 'google'],
            query:  ['state' => 'state1', 'code' => 'abc'],
        ));

        $this->assertArrayNotHasKey('oauth_state', $_SESSION);
        $this->assertArrayNotHasKey('oauth_provider', $_SESSION);
        $this->assertArrayNotHasKey('oauth_redirect', $_SESSION);
    }
}

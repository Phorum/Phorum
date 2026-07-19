<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use League\OAuth2\Client\Token\AccessToken;
use Phorum\Core\Config;
use Phorum\Mapper\SettingMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Mod\Oauth\OauthEmailNotVerifiedException;
use Phorum\Mod\Oauth\OauthIdentity;
use Phorum\Mod\Oauth\OauthIdentityMapper;
use Phorum\Mod\Oauth\OauthService;
use Phorum\Model\User;
use PHPUnit\Framework\TestCase;

class OauthServiceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/src/Hook/functions.php';
        $base = dirname(__DIR__, 2) . '/mods/oauth';
        require_once $base . '/OauthEmailNotVerifiedException.php';
        require_once $base . '/OauthIdentity.php';
        require_once $base . '/OauthIdentityMapper.php';
        require_once $base . '/OauthService.php';
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

    private function enabledSettings(string $provider): array
    {
        return [
            "oauth_{$provider}_enabled"       => 1,
            "oauth_{$provider}_client_id"     => 'client-id',
            "oauth_{$provider}_client_secret" => 'client-secret',
        ];
    }

    /** Guzzle client backed by MockHandler serving $responses in order. */
    private function makeHttp(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }

    private function makeService(
        array                 $settingsValues,
        array                 $mockResponses,
        ?UserMapper           $users      = null,
        ?OauthIdentityMapper  $identities = null,
    ): OauthService {
        return new OauthService(
            config:     $this->makeConfig(),
            settings:   $this->makeSettings($settingsValues),
            users:      $users      ?? $this->createMock(UserMapper::class),
            identities: $identities ?? $this->createMock(OauthIdentityMapper::class),
            http:       $this->makeHttp($mockResponses),
        );
    }

    private function makeUser(int $id = 1, string $email = 'alice@example.com'): User
    {
        $user          = new User();
        $user->user_id = $id;
        $user->email   = $email;
        $user->active  = 1;
        return $user;
    }

    private function googleUserinfoResponse(array $override = []): GuzzleResponse
    {
        $data = array_merge([
            'sub'            => 'g-123',
            'email'          => 'alice@example.com',
            'email_verified' => true,
            'name'           => 'Alice Smith',
        ], $override);
        return new GuzzleResponse(200, [], json_encode($data));
    }

    private function githubUserResponse(array $override = []): GuzzleResponse
    {
        $data = array_merge([
            'id'    => 456,
            'login' => 'bobby',
            'name'  => 'Bob Jones',
            // Non-empty so league's own Github::fetchResourceOwnerDetails()
            // doesn't make its own extra /user/emails call — our profile
            // extraction ignores this value and fetches emails itself anyway.
            'email' => 'fallback@example.com',
        ], $override);
        return new GuzzleResponse(200, [], json_encode($data));
    }

    private function githubEmailsResponse(array $emails): GuzzleResponse
    {
        return new GuzzleResponse(200, [], json_encode($emails));
    }

    // -------------------------------------------------------------------------
    // isConfigured
    // -------------------------------------------------------------------------

    public function testIsConfiguredFalseForUnknownProvider(): void
    {
        $service = $this->makeService([], []);
        $this->assertFalse($service->isConfigured('bogus'));
    }

    public function testIsConfiguredFalseWhenDisabled(): void
    {
        $service = $this->makeService([
            'oauth_google_enabled'       => 0,
            'oauth_google_client_id'     => 'id',
            'oauth_google_client_secret' => 'secret',
        ], []);
        $this->assertFalse($service->isConfigured('google'));
    }

    public function testIsConfiguredFalseWhenMissingClientSecret(): void
    {
        $service = $this->makeService([
            'oauth_google_enabled'   => 1,
            'oauth_google_client_id' => 'id',
        ], []);
        $this->assertFalse($service->isConfigured('google'));
    }

    public function testIsConfiguredTrueWhenEnabledWithCredentials(): void
    {
        $service = $this->makeService($this->enabledSettings('google'), []);
        $this->assertTrue($service->isConfigured('google'));
    }

    // -------------------------------------------------------------------------
    // resolveUser — existing identity
    // -------------------------------------------------------------------------

    public function testResolveUserReturnsExistingUserWhenIdentityAlreadyLinked(): void
    {
        $existing = $this->makeUser(9);
        $identity = new OauthIdentity();
        $identity->user_id = 9;

        $identities = $this->createMock(OauthIdentityMapper::class);
        $identities->method('findByProviderAndId')->with('google', 'g-123')->willReturn($identity);
        $identities->expects($this->never())->method('save');

        $users = $this->createMock(UserMapper::class);
        $users->method('load')->with(9)->willReturn($existing);
        $users->expects($this->never())->method('findByEmail');
        $users->expects($this->never())->method('save');

        $service = $this->makeService(
            $this->enabledSettings('google'),
            [$this->googleUserinfoResponse()],
            $users,
            $identities,
        );

        $token  = new AccessToken(['access_token' => 'tok']);
        $result = $service->resolveUser('google', $token);

        $this->assertSame($existing, $result);
    }

    // -------------------------------------------------------------------------
    // resolveUser — link by verified email
    // -------------------------------------------------------------------------

    public function testResolveUserLinksToExistingAccountByVerifiedEmailGoogle(): void
    {
        $existing = $this->makeUser(9, 'alice@example.com');

        $identities = $this->createMock(OauthIdentityMapper::class);
        $identities->method('findByProviderAndId')->willReturn(null);
        $identities->expects($this->once())->method('save')->with($this->callback(
            fn(OauthIdentity $i) => $i->provider === 'google' && $i->provider_user_id === 'g-123' && $i->user_id === 9
        ));

        $users = $this->createMock(UserMapper::class);
        $users->method('findByEmail')->with('alice@example.com')->willReturn($existing);
        $users->expects($this->never())->method('save');

        $service = $this->makeService(
            $this->enabledSettings('google'),
            [$this->googleUserinfoResponse()],
            $users,
            $identities,
        );

        $result = $service->resolveUser('google', new AccessToken(['access_token' => 'tok']));
        $this->assertSame($existing, $result);
    }

    public function testResolveUserLinksToExistingAccountByVerifiedEmailGithub(): void
    {
        $existing = $this->makeUser(9, 'bob@example.com');

        $identities = $this->createMock(OauthIdentityMapper::class);
        $identities->method('findByProviderAndId')->willReturn(null);
        $identities->expects($this->once())->method('save')->with($this->callback(
            fn(OauthIdentity $i) => $i->provider === 'github' && $i->provider_user_id === '456' && $i->user_id === 9
        ));

        $users = $this->createMock(UserMapper::class);
        $users->method('findByEmail')->with('bob@example.com')->willReturn($existing);
        $users->expects($this->never())->method('save');

        $service = $this->makeService(
            $this->enabledSettings('github'),
            [
                $this->githubUserResponse(),
                $this->githubEmailsResponse([
                    ['email' => 'old@example.com', 'primary' => false, 'verified' => true],
                    ['email' => 'bob@example.com', 'primary' => true, 'verified' => true],
                ]),
            ],
            $users,
            $identities,
        );

        $result = $service->resolveUser('github', new AccessToken(['access_token' => 'tok']));
        $this->assertSame($existing, $result);
    }

    // -------------------------------------------------------------------------
    // resolveUser — auto-register
    // -------------------------------------------------------------------------

    public function testResolveUserAutoRegistersNewAccountGoogle(): void
    {
        $identities = $this->createMock(OauthIdentityMapper::class);
        $identities->method('findByProviderAndId')->willReturn(null);
        $identities->expects($this->once())->method('save');

        $saved = null;
        $users = $this->createMock(UserMapper::class);
        $users->method('findByEmail')->willReturn(null);
        $users->method('findByUsername')->willReturn(null);
        $users->expects($this->once())->method('save')->willReturnCallback(function (User $u) use (&$saved) {
            $u->user_id = 42;
            $saved      = $u;
            return $u;
        });

        $service = $this->makeService(
            $this->enabledSettings('google'),
            [$this->googleUserinfoResponse()],
            $users,
            $identities,
        );

        $result = $service->resolveUser('google', new AccessToken(['access_token' => 'tok']));

        $this->assertSame($saved, $result);
        $this->assertSame(1, $saved->active);
        $this->assertSame('alice@example.com', $saved->email);
        $this->assertNotEmpty($saved->username);
        $this->assertTrue(password_get_info($saved->password)['algoName'] !== 'unknown');
        $this->assertFalse(password_verify('', $saved->password));
    }

    public function testResolveUserAutoRegistersNewAccountGithub(): void
    {
        $identities = $this->createMock(OauthIdentityMapper::class);
        $identities->method('findByProviderAndId')->willReturn(null);
        $identities->expects($this->once())->method('save');

        $saved = null;
        $users = $this->createMock(UserMapper::class);
        $users->method('findByEmail')->willReturn(null);
        $users->method('findByUsername')->willReturn(null);
        $users->expects($this->once())->method('save')->willReturnCallback(function (User $u) use (&$saved) {
            $u->user_id = 43;
            $saved      = $u;
            return $u;
        });

        $service = $this->makeService(
            $this->enabledSettings('github'),
            [
                $this->githubUserResponse(),
                $this->githubEmailsResponse([
                    ['email' => 'bob@example.com', 'primary' => true, 'verified' => true],
                ]),
            ],
            $users,
            $identities,
        );

        $result = $service->resolveUser('github', new AccessToken(['access_token' => 'tok']));

        $this->assertSame($saved, $result);
        $this->assertSame(1, $saved->active);
        $this->assertSame('bob@example.com', $saved->email);
    }

    // -------------------------------------------------------------------------
    // resolveUser — unverified email
    // -------------------------------------------------------------------------

    public function testResolveUserThrowsWhenGoogleEmailNotVerified(): void
    {
        $service = $this->makeService(
            $this->enabledSettings('google'),
            [$this->googleUserinfoResponse(['email_verified' => false])],
        );

        $this->expectException(OauthEmailNotVerifiedException::class);
        $service->resolveUser('google', new AccessToken(['access_token' => 'tok']));
    }

    public function testGithubProfileTreatsNoVerifiedEmailAsUnverified(): void
    {
        $service = $this->makeService(
            $this->enabledSettings('github'),
            [
                $this->githubUserResponse(),
                $this->githubEmailsResponse([
                    ['email' => 'a@example.com', 'primary' => true, 'verified' => false],
                    ['email' => 'b@example.com', 'primary' => false, 'verified' => false],
                ]),
            ],
        );

        $this->expectException(OauthEmailNotVerifiedException::class);
        $service->resolveUser('github', new AccessToken(['access_token' => 'tok']));
    }

    // -------------------------------------------------------------------------
    // GitHub verified-primary-email selection
    // -------------------------------------------------------------------------

    public function testGithubProfileFetchesVerifiedPrimaryEmailWhenPrimaryEmailIsNull(): void
    {
        // A null email on /user (private-email GitHub setting) makes league's
        // own Github::fetchResourceOwnerDetails() fall back to its own naive
        // first-entry /user/emails call first (response discarded — we never
        // read $owner->getEmail()); our own explicit follow-up call is what
        // actually determines the profile email, picking the primary+verified
        // entry regardless of array order.
        $existing = $this->makeUser(9, 'primary@example.com');

        $users = $this->createMock(UserMapper::class);
        $users->method('findByEmail')->with('primary@example.com')->willReturn($existing);

        $service = $this->makeService(
            $this->enabledSettings('github'),
            [
                $this->githubUserResponse(['email' => null]),
                $this->githubEmailsResponse([]), // league's own internal fallback call — discarded
                $this->githubEmailsResponse([
                    ['email' => 'other@example.com', 'primary' => false, 'verified' => true],
                    ['email' => 'primary@example.com', 'primary' => true, 'verified' => true],
                ]),
            ],
            $users,
        );

        $result = $service->resolveUser('github', new AccessToken(['access_token' => 'tok']));
        $this->assertSame($existing, $result);
    }

    // -------------------------------------------------------------------------
    // exchangeCode
    // -------------------------------------------------------------------------

    public function testExchangeCodeReturnsAccessToken(): void
    {
        $service = $this->makeService(
            $this->enabledSettings('google'),
            [new GuzzleResponse(200, [], json_encode(['access_token' => 'abc123', 'expires_in' => 3600]))],
        );

        $token = $service->exchangeCode('google', 'auth-code');
        $this->assertSame('abc123', $token->getToken());
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Mod\Oauth;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Phorum\Core\Config;
use Phorum\Mapper\SettingMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\User;

/**
 * OAuth2 login for Google/GitHub: authorization URL + state, code exchange,
 * and account resolution (existing linked identity -> existing account by
 * verified email -> auto-register). Uses league/oauth2-client under the
 * hood; the Guzzle client is injected the same way mods/webhooks/
 * WebhookDispatcher injects one, so it can be swapped for a MockHandler-
 * backed client in tests without any real network calls.
 */
class OauthService
{
    public const PROVIDERS = ['google', 'github'];

    private readonly SettingMapper       $settings;
    private readonly UserMapper          $users;
    private readonly OauthIdentityMapper $identities;
    private readonly ClientInterface     $http;

    public function __construct(
        private readonly Config      $config,
        ?SettingMapper               $settings   = null,
        ?UserMapper                  $users      = null,
        ?OauthIdentityMapper         $identities = null,
        ?ClientInterface             $http       = null,
    ) {
        $this->settings   = $settings   ?? new SettingMapper();
        $this->users      = $users      ?? new UserMapper();
        $this->identities = $identities ?? new OauthIdentityMapper();
        $this->http       = $http       ?? new Client(['timeout' => 10, 'connect_timeout' => 3]);
    }

    /** True when the provider is enabled and has both a client id and secret configured. */
    public function isConfigured(string $provider): bool
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            return false;
        }

        if (empty($this->settings->getSetting("oauth_{$provider}_enabled"))) {
            return false;
        }

        $clientId     = (string) ($this->settings->getSetting("oauth_{$provider}_client_id") ?? '');
        $clientSecret = (string) ($this->settings->getSetting("oauth_{$provider}_client_secret") ?? '');

        return $clientId !== '' && $clientSecret !== '';
    }

    /** Build the league provider instance for $provider from stored admin settings. */
    public function providerFor(string $provider): AbstractProvider
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new \InvalidArgumentException("Unknown OAuth provider: {$provider}");
        }

        $options = [
            'clientId'     => (string) ($this->settings->getSetting("oauth_{$provider}_client_id") ?? ''),
            'clientSecret' => (string) ($this->settings->getSetting("oauth_{$provider}_client_secret") ?? ''),
            'redirectUri'  => $this->callbackUrl($provider),
        ];
        $collaborators = ['httpClient' => $this->http];

        return $provider === 'google'
            ? new Google($options, $collaborators)
            : new Github($options, $collaborators);
    }

    /**
     * The URL to redirect the browser to, plus the state value the caller
     * must stash (e.g. in $_SESSION) and verify against the callback's
     * `state` query param.
     *
     * @return array{url:string,state:string}
     */
    public function authorizationUrl(string $provider): array
    {
        $providerInstance = $this->providerFor($provider);
        $url              = $providerInstance->getAuthorizationUrl();

        return ['url' => $url, 'state' => $providerInstance->getState()];
    }

    /** Exchange the callback's authorization code for an access token. Throws on failure. */
    public function exchangeCode(string $provider, string $code): AccessToken
    {
        return $this->providerFor($provider)->getAccessToken('authorization_code', ['code' => $code]);
    }

    /**
     * Resolve (find-or-create) the local User for this provider + token.
     * Order: existing oauth_identities row -> existing users.email match
     * (only if the provider confirms the email is verified) -> auto-register
     * a new User. Persists a new identity link on the "found by email" and
     * "auto-register" paths so the next login goes straight through
     * findByProviderAndId().
     *
     * @throws OauthEmailNotVerifiedException when the provider has no
     *         verified email to link or register against.
     */
    public function resolveUser(string $provider, AccessToken $token): User
    {
        $providerInstance = $this->providerFor($provider);
        $owner            = $providerInstance->getResourceOwner($token);

        $profile = $provider === 'google'
            ? $this->googleProfile($owner)
            : $this->githubProfile($providerInstance, $token, $owner);

        $identity = $this->identities->findByProviderAndId($provider, $profile['id']);
        if ($identity !== null) {
            $user = $this->users->load($identity->user_id);
            if ($user !== null) {
                return $user;
            }
            // Identity row is orphaned (the linked user was deleted) — fall
            // through and re-link/create as if this were a first-time login.
        }

        if (!$profile['email_verified'] || $profile['email'] === '') {
            throw new OauthEmailNotVerifiedException($provider);
        }

        $user = $this->users->findByEmail($profile['email']);
        if ($user === null) {
            $user = $this->registerFromProfile($profile);
        }

        $this->linkIdentity($provider, $profile, $user);

        return $user;
    }

    /** @return array{id:string,email:string,email_verified:bool,name:string} */
    private function googleProfile(GoogleUser $owner): array
    {
        $data = $owner->toArray();

        return [
            'id'             => (string) $owner->getId(),
            'email'          => (string) ($data['email'] ?? ''),
            'email_verified' => (bool) ($data['email_verified'] ?? false),
            'name'           => (string) ($data['name'] ?? ''),
        ];
    }

    /**
     * GitHub quirk: league's own email fallback (Github::fetchResourceOwnerDetails())
     * takes the FIRST /user/emails entry, which is neither guaranteed to be
     * verified nor primary. This makes its own authenticated call (via the
     * same provider/httpClient, so it's mockable in tests) and picks the
     * primary+verified entry, falling back to any verified entry.
     *
     * @return array{id:string,email:string,email_verified:bool,name:string}
     */
    private function githubProfile(Github $provider, AccessToken $token, GithubResourceOwner $owner): array
    {
        $name = (string) ($owner->getName() ?: $owner->getNickname() ?: '');

        $emailsUrl = rtrim($provider->apiDomain, '/') . '/user/emails';
        $request   = $provider->getAuthenticatedRequest('GET', $emailsUrl, $token);
        $emails    = $provider->getParsedResponse($request);

        $verifiedEmail = '';
        if (is_array($emails)) {
            foreach ($emails as $entry) {
                if (!empty($entry['primary']) && !empty($entry['verified'])) {
                    $verifiedEmail = (string) $entry['email'];
                    break;
                }
            }
            if ($verifiedEmail === '') {
                foreach ($emails as $entry) {
                    if (!empty($entry['verified'])) {
                        $verifiedEmail = (string) $entry['email'];
                        break;
                    }
                }
            }
        }

        return [
            'id'             => (string) $owner->getId(),
            'email'          => $verifiedEmail,
            'email_verified' => $verifiedEmail !== '',
            'name'           => $name,
        ];
    }

    /** @param array{id:string,email:string,email_verified:bool,name:string} $profile */
    private function registerFromProfile(array $profile): User
    {
        $username = $this->uniqueUsernameFrom($profile['name'] !== '' ? $profile['name'] : $profile['email']);

        $user                   = new User();
        $user->username         = $username;
        $user->display_name     = $profile['name'] !== '' ? $profile['name'] : $username;
        $user->email            = $profile['email'];
        // Unusable password — a random secret never surfaced anywhere, so
        // the password-login form can never authenticate this account.
        $user->password         = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
        // Provider already verified the email, so this account is active
        // immediately regardless of the site's require_confirmation setting.
        $user->active           = 1;
        $user->date_added       = time();
        $user->date_last_active = time();

        phorum_api_hook('before_register', ['username' => $user->username, 'email' => $user->email]);
        $this->users->save($user);
        phorum_api_hook('after_register', $user);

        return $user;
    }

    /** Slugify $seed and append a numeric suffix until the username is free. */
    private function uniqueUsernameFrom(string $seed): string
    {
        $base = strtolower((string) preg_replace('/[^a-z0-9_-]+/i', '', str_replace(' ', '-', trim($seed))));
        if ($base === '') {
            $base = 'user';
        }
        $base = substr($base, 0, 50);

        $username = $base;
        $suffix   = 2;
        while ($this->users->findByUsername($username) !== null) {
            $username = substr($base, 0, 50 - strlen((string) $suffix) - 1) . '-' . $suffix;
            $suffix++;
        }

        return $username;
    }

    /** @param array{id:string,email:string,email_verified:bool,name:string} $profile */
    private function linkIdentity(string $provider, array $profile, User $user): void
    {
        $identity                   = new OauthIdentity();
        $identity->user_id          = $user->user_id;
        $identity->provider         = $provider;
        $identity->provider_user_id = $profile['id'];
        $identity->email            = $profile['email'];
        $identity->date_added       = time();

        $this->identities->save($identity);
    }

    /**
     * The callback URL registered with the provider. Must include both
     * base_url and base_path (unlike other email-link builders in this
     * codebase that only use base_url) since the router matches routes
     * after base_path is stripped from the incoming URI — omitting it
     * breaks OAuth entirely on subfolder installs.
     */
    private function callbackUrl(string $provider): string
    {
        $base = rtrim((string) $this->config->get('base_url', ''), '/')
              . (string) $this->config->get('base_path', '');

        return $base . '/auth/oauth/' . $provider . '/callback';
    }
}

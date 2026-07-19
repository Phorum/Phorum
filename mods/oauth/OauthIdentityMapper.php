<?php
declare(strict_types=1);

namespace Phorum\Mod\Oauth;

use Phorum\Mapper\AbstractPhorumMapper;

class OauthIdentityMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = OauthIdentity::class;
    public const PRIMARY_KEY  = 'oauth_identity_id';
    public const TABLE_BASE   = 'oauth_identities';

    public const MAPPING = [
        'oauth_identity_id' => ['read_only' => true],
        'user_id'           => [],
        'provider'          => [],
        'provider_user_id'  => [],
        'email'             => [],
        'date_added'        => [],
    ];

    /** The linked identity row for a provider + provider-side user id, or null if never linked. */
    public function findByProviderAndId(string $provider, string $providerUserId): ?OauthIdentity
    {
        $rows = $this->find(['provider' => $provider, 'provider_user_id' => $providerUserId], limit: 1);
        return $rows[0] ?? null;
    }

    /** Every provider identity linked to a local user account. */
    public function findByUserId(int $userId): array
    {
        return $this->find(['user_id' => $userId]) ?? [];
    }
}

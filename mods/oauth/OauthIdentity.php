<?php
declare(strict_types=1);

namespace Phorum\Mod\Oauth;

/**
 * Links a third-party OAuth provider identity (Google/GitHub) to a local
 * user account. The users table is frozen for Phorum 6 schema
 * compatibility, so this lives in its own additive table rather than as
 * new columns on users.
 */
class OauthIdentity
{
    public int    $oauth_identity_id = 0;
    public int    $user_id           = 0;
    public string $provider          = '';
    public string $provider_user_id  = '';
    public string $email             = '';
    public int    $date_added        = 0;
}

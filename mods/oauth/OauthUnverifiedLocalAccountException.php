<?php
declare(strict_types=1);

namespace Phorum\Mod\Oauth;

/**
 * Thrown when a provider's verified email matches a local account that has
 * never proved control of that address itself.
 *
 * The provider vouches for its own side only. Linking on an email match alone
 * meant that an account registered on someone else's address — which
 * require_confirmation being off makes trivial — was handed to whoever later
 * signed in with that address at Google or GitHub. Refusing here keeps the two
 * accounts apart; the local one becomes linkable once it confirms its address
 * or completes a password reset.
 */
class OauthUnverifiedLocalAccountException extends \RuntimeException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct("A local account already uses '{$email}' but has not verified it.");
    }
}

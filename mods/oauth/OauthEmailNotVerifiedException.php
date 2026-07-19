<?php
declare(strict_types=1);

namespace Phorum\Mod\Oauth;

/** Thrown when a provider has no verified email to link or register an account against. */
class OauthEmailNotVerifiedException extends \RuntimeException
{
    public function __construct(public readonly string $provider)
    {
        parent::__construct("No verified email available from OAuth provider '{$provider}'.");
    }
}

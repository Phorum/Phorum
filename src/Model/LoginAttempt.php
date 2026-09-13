<?php
declare(strict_types=1);

namespace Phorum\Model;

/**
 * One recorded failed authentication attempt, used for rate limiting.
 *
 * `attempt_key` is a namespaced bucket rather than a raw address or name —
 * see LoginAttemptMapper for the prefixes.
 */
class LoginAttempt
{
    public int    $login_attempt_id = 0;
    public string $attempt_key      = '';
    public int    $attempted_at     = 0;
}

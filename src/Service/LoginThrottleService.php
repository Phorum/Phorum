<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Core\ClientIp;
use Phorum\Mapper\LoginAttemptMapper;
use Phorum\Mapper\SettingMapper;

/**
 * Rate-limits authentication and account-recovery endpoints.
 *
 * Nothing throttled password guessing before this: both login forms and the
 * two unauthenticated mail senders (forgot-password, resend-confirmation)
 * accepted unlimited requests.
 *
 * Two buckets are counted per login, because they stop different attacks and
 * neither alone is enough. The per-address bucket stops one host spraying a
 * password across many accounts — including accounts that don't exist, which
 * a per-account counter can't see at all. The per-account bucket stops
 * distributed guessing at one account.
 *
 * Exceeding a limit refuses the attempt for the rest of the window; it never
 * sets a lasting lock. A hard account lockout would hand anyone a way to
 * deny a chosen user access just by failing logins against their name, so the
 * per-account limit is deliberately looser than the per-address one, and both
 * lapse on their own. A successful login clears that account's bucket.
 */
class LoginThrottleService
{
    /** Defaults, overridable through the settings table (admin Settings panel). */
    public const DEFAULT_WINDOW          = 900; // 15 minutes
    public const DEFAULT_MAX_PER_IP      = 15;
    public const DEFAULT_MAX_PER_ACCOUNT = 8;
    public const DEFAULT_MAX_RESETS      = 5;

    public function __construct(
        private readonly LoginAttemptMapper $attempts,
        private readonly ?SettingMapper     $settings = null,
    ) {}

    /**
     * Seconds the caller must wait before another login attempt is accepted;
     * 0 when they are clear to proceed.
     *
     * @param string $username The submitted account name (may be unknown/blank).
     * @param ?array $server   Request server vars; defaults to $_SERVER.
     */
    public function loginRetryAfter(string $username, ?array $server = null): int
    {
        $window = $this->window();
        $since  = time() - $window;

        $wait = 0;

        $ip = ClientIp::resolve($server);
        if ($ip !== '') {
            $wait = max($wait, $this->retryAfterFor(
                LoginAttemptMapper::key(LoginAttemptMapper::KEY_IP, $ip),
                $this->limit('login_max_per_ip', self::DEFAULT_MAX_PER_IP),
                $since,
                $window,
            ));
        }

        if (trim($username) !== '') {
            $wait = max($wait, $this->retryAfterFor(
                LoginAttemptMapper::key(LoginAttemptMapper::KEY_USER, $username),
                $this->limit('login_max_per_account', self::DEFAULT_MAX_PER_ACCOUNT),
                $since,
                $window,
            ));
        }

        return $wait;
    }

    /** Record a failed login against both the source address and the account. */
    public function recordFailedLogin(string $username, ?array $server = null): void
    {
        $window = $this->window();

        $ip = ClientIp::resolve($server);
        if ($ip !== '') {
            $this->attempts->record(LoginAttemptMapper::key(LoginAttemptMapper::KEY_IP, $ip), $window);
        }

        if (trim($username) !== '') {
            $this->attempts->record(LoginAttemptMapper::key(LoginAttemptMapper::KEY_USER, $username), $window);
        }
    }

    /**
     * Clear the account bucket after a successful login.
     *
     * The address bucket is deliberately left alone: one correct password
     * among many wrong ones is exactly what a spraying run looks like, so a
     * success shouldn't reset the evidence of the failures around it.
     */
    public function clearAccount(string $username): void
    {
        if (trim($username) !== '') {
            $this->attempts->clear(LoginAttemptMapper::key(LoginAttemptMapper::KEY_USER, $username));
        }
    }

    /**
     * Seconds to wait before another password-reset or resend-confirmation
     * request is accepted from this address; 0 when clear.
     *
     * These send mail to an address the requester supplies, so left open they
     * are a way to flood someone else's inbox from this site.
     */
    public function resetRetryAfter(?array $server = null): int
    {
        $ip = ClientIp::resolve($server);
        if ($ip === '') {
            return 0;
        }

        $window = $this->window();

        return $this->retryAfterFor(
            LoginAttemptMapper::key(LoginAttemptMapper::KEY_RESET, $ip),
            $this->limit('login_max_resets', self::DEFAULT_MAX_RESETS),
            time() - $window,
            $window,
        );
    }

    /** Record one password-reset / resend-confirmation request. */
    public function recordResetRequest(?array $server = null): void
    {
        $ip = ClientIp::resolve($server);
        if ($ip !== '') {
            $this->attempts->record(LoginAttemptMapper::key(LoginAttemptMapper::KEY_RESET, $ip), $this->window());
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Seconds left in the window for $key once $max attempts are used up,
     * or 0 while it is still under the limit. A limit of 0 disables the check.
     */
    private function retryAfterFor(string $key, int $max, int $since, int $window): int
    {
        if ($max <= 0) {
            return 0;
        }

        if ($this->attempts->countSince($key, $since) < $max) {
            return 0;
        }

        // Measured from the most recent attempt, so hammering the endpoint
        // while blocked keeps extending the wait rather than shortening it.
        $last = $this->attempts->lastAttemptSince($key, $since);

        return max(1, ($last + $window) - time());
    }

    private function window(): int
    {
        $configured = (int) ($this->setting('login_throttle_window') ?? 0);
        return $configured > 0 ? $configured : self::DEFAULT_WINDOW;
    }

    private function limit(string $name, int $default): int
    {
        $configured = $this->setting($name);
        return $configured === null || $configured === '' ? $default : (int) $configured;
    }

    private function setting(string $name): mixed
    {
        if ($this->settings === null) {
            return null;
        }

        try {
            return $this->settings->getSetting($name);
        } catch (\Throwable) {
            return null;
        }
    }
}

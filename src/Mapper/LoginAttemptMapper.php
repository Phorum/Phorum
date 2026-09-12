<?php
declare(strict_types=1);

namespace Phorum\Mapper;

use Phorum\Model\LoginAttempt;

/**
 * Stores and counts failed authentication attempts for rate limiting.
 *
 * Attempts are bucketed by a namespaced key so one table can limit several
 * different things at once — a source address, a targeted account, a
 * password-reset request — without the buckets colliding.
 *
 * There is no scheduled job anywhere in this application, so expired rows are
 * pruned opportunistically on write. The table therefore stays at roughly one
 * limiting window's worth of rows rather than growing without bound.
 */
class LoginAttemptMapper extends AbstractPhorumMapper
{
    public const MAPPED_CLASS = LoginAttempt::class;
    public const PRIMARY_KEY  = 'login_attempt_id';
    public const TABLE_BASE   = 'login_attempts';

    /** Key prefix for the source address of a request. */
    public const KEY_IP = 'ip';

    /** Key prefix for a targeted account name. */
    public const KEY_USER = 'user';

    /** Key prefix for password-reset / resend-confirmation requests. */
    public const KEY_RESET = 'reset';

    public const MAPPING = [
        'login_attempt_id' => ['read_only' => true],
        'attempt_key'      => [],
        'attempted_at'     => [],
    ];

    /** Build the stored bucket key for a prefix and value, e.g. "ip:1.2.3.4". */
    public static function key(string $prefix, string $value): string
    {
        return $prefix . ':' . mb_strtolower(trim($value));
    }

    /** Record one failed attempt against $key, and prune anything long expired. */
    public function record(string $key, int $retentionSeconds, ?int $now = null): void
    {
        $now = $now ?? time();

        $attempt               = new LoginAttempt();
        $attempt->attempt_key  = $key;
        $attempt->attempted_at = $now;
        $this->save($attempt);

        $this->prune($now - $retentionSeconds);
    }

    /** How many attempts have been recorded against $key since $since. */
    public function countSince(string $key, int $since): int
    {
        $rows = $this->crud()->runFetch(
            'SELECT COUNT(*) AS cnt FROM ' . $this->table()
            . ' WHERE attempt_key = :key AND attempted_at > :since',
            [':key' => $key, ':since' => $since]
        );

        return (int) ($rows[0]['cnt'] ?? 0);
    }

    /** The most recent attempt time against $key since $since, or 0 if none. */
    public function lastAttemptSince(string $key, int $since): int
    {
        $rows = $this->crud()->runFetch(
            'SELECT MAX(attempted_at) AS last_at FROM ' . $this->table()
            . ' WHERE attempt_key = :key AND attempted_at > :since',
            [':key' => $key, ':since' => $since]
        );

        return (int) ($rows[0]['last_at'] ?? 0);
    }

    /** Drop every attempt recorded against $key — called on a successful login. */
    public function clear(string $key): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table() . ' WHERE attempt_key = :key',
            [':key' => $key]
        );
    }

    /** Delete attempts older than $cutoff. */
    public function prune(int $cutoff): void
    {
        $this->crud()->run(
            'DELETE FROM ' . $this->table() . ' WHERE attempted_at <= :cutoff',
            [':cutoff' => $cutoff]
        );
    }
}

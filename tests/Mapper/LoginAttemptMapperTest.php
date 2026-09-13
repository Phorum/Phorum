<?php
declare(strict_types=1);

namespace Phorum\Tests\Mapper;

use DealNews\DB\CRUD;
use Phorum\Mapper\LoginAttemptMapper;

/**
 * Exercises the failed-attempt store against real SQL, including the
 * prune-on-write behaviour that keeps the table bounded — this application
 * has no scheduled job, so nothing else would ever delete these rows.
 */
class LoginAttemptMapperTest extends MapperTestCase
{
    private function makeMapper(): LoginAttemptMapper
    {
        return new class extends LoginAttemptMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };
    }

    /** Keys are namespaced and case-folded so buckets can't collide. */
    public function testKeyNamespacesAndLowercases(): void
    {
        $this->assertSame('ip:1.2.3.4', LoginAttemptMapper::key(LoginAttemptMapper::KEY_IP, '1.2.3.4'));
        $this->assertSame('user:alice', LoginAttemptMapper::key(LoginAttemptMapper::KEY_USER, '  ALICE '));
    }

    /** Counting is scoped to one key and one time window. */
    public function testCountSinceCountsOnlyMatchingKeyWithinWindow(): void
    {
        $mapper = $this->makeMapper();
        $now    = 1_000_000;

        $mapper->record('ip:1.1.1.1', 900, $now);
        $mapper->record('ip:1.1.1.1', 900, $now);
        $mapper->record('ip:2.2.2.2', 900, $now);

        $this->assertSame(2, $mapper->countSince('ip:1.1.1.1', $now - 900));
        $this->assertSame(1, $mapper->countSince('ip:2.2.2.2', $now - 900));
        $this->assertSame(0, $mapper->countSince('ip:3.3.3.3', $now - 900));
    }

    /** Attempts older than the window don't count toward the limit. */
    public function testCountSinceExcludesAttemptsOutsideTheWindow(): void
    {
        $mapper = $this->makeMapper();
        $now    = 1_000_000;

        // Long retention so recording the recent one doesn't prune the old one.
        $mapper->record('ip:1.1.1.1', 100_000, $now - 5_000);
        $mapper->record('ip:1.1.1.1', 100_000, $now);

        $this->assertSame(1, $mapper->countSince('ip:1.1.1.1', $now - 900));
        $this->assertSame(2, $mapper->countSince('ip:1.1.1.1', $now - 10_000));
    }

    /** The retry-after calculation needs the most recent attempt time. */
    public function testLastAttemptSinceReturnsTheNewestTimestamp(): void
    {
        $mapper = $this->makeMapper();
        $now    = 1_000_000;

        $mapper->record('ip:1.1.1.1', 100_000, $now - 50);
        $mapper->record('ip:1.1.1.1', 100_000, $now - 10);

        $this->assertSame($now - 10, $mapper->lastAttemptSince('ip:1.1.1.1', $now - 900));
        $this->assertSame(0, $mapper->lastAttemptSince('ip:9.9.9.9', $now - 900));
    }

    /** A successful login clears that bucket without touching others. */
    public function testClearRemovesOnlyTheGivenKey(): void
    {
        $mapper = $this->makeMapper();
        $now    = 1_000_000;

        $mapper->record('user:alice', 900, $now);
        $mapper->record('ip:1.1.1.1', 900, $now);

        $mapper->clear('user:alice');

        $this->assertSame(0, $mapper->countSince('user:alice', $now - 900));
        $this->assertSame(1, $mapper->countSince('ip:1.1.1.1', $now - 900));
    }

    /**
     * Recording prunes expired rows, so the table stays at roughly one window
     * rather than growing forever — there is no cron to do it later.
     */
    public function testRecordPrunesExpiredRows(): void
    {
        $mapper = $this->makeMapper();
        $now    = 1_000_000;

        $mapper->record('ip:1.1.1.1', 100_000, $now - 5_000);
        $this->assertSame(1, $this->rowCount());

        // Retention of 900s means the 5000s-old row is now expired.
        $mapper->record('ip:1.1.1.1', 900, $now);

        $this->assertSame(1, $this->rowCount(), 'expired row was not pruned');
        $this->assertSame(1, $mapper->countSince('ip:1.1.1.1', $now - 900));
    }

    /** Total rows currently in the attempts table. */
    private function rowCount(): int
    {
        return (int) self::$crud->runFetch('SELECT COUNT(*) AS c FROM phorum_login_attempts', [])[0]['c'];
    }
}

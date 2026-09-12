<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use DealNews\DB\CRUD;
use Phorum\Core\ClientIp;
use Phorum\Core\Config;
use Phorum\Mapper\LoginAttemptMapper;
use Phorum\Service\LoginThrottleService;
use Phorum\Tests\Mapper\MapperTestCase;

/**
 * Tests login/reset rate limiting end to end against real SQL, since the
 * counting and windowing live in the queries.
 *
 * Runs against the mapper test database rather than a mocked mapper so the
 * limits are verified as a user would hit them, not as a sequence of calls.
 */
class LoginThrottleServiceTest extends MapperTestCase
{
    protected static array $tables = ['phorum_login_attempts'];

    private function makeService(): LoginThrottleService
    {
        $mapper = new class extends LoginAttemptMapper {
            protected function crud(): CRUD
            {
                return MapperTestCase::$crud;
            }
        };

        // No settings mapper: the service falls back to its own defaults.
        return new LoginThrottleService($mapper);
    }

    /** Request server vars for a given source address. */
    private function from(string $ip): array
    {
        return ['REMOTE_ADDR' => $ip];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // No trusted proxies: REMOTE_ADDR is taken at face value.
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $key === 'trusted_proxies' ? [] : $default
        );
        ClientIp::initialize($config);
    }

    /** A caller under the limit is never made to wait. */
    public function testFreshCallerIsNotThrottled(): void
    {
        $this->assertSame(0, $this->makeService()->loginRetryAfter('alice', $this->from('1.1.1.1')));
    }

    /**
     * Sustained guessing at one account is refused once the per-account limit
     * is reached, even though the per-address limit is higher.
     */
    public function testAccountIsThrottledAfterItsLimit(): void
    {
        $svc = $this->makeService();

        for ($i = 0; $i < LoginThrottleService::DEFAULT_MAX_PER_ACCOUNT; $i++) {
            $svc->recordFailedLogin('alice', $this->from('1.1.1.' . $i));
        }

        $this->assertGreaterThan(0, $svc->loginRetryAfter('alice', $this->from('9.9.9.9')));
        $this->assertSame(0, $svc->loginRetryAfter('bob', $this->from('9.9.9.9')));
    }

    /**
     * Spraying one password across many accounts from one host is refused by
     * the per-address limit — the case a per-account counter can't see, since
     * each individual account stays well under its own limit.
     */
    public function testAddressIsThrottledWhenSprayingAcrossAccounts(): void
    {
        $svc = $this->makeService();

        for ($i = 0; $i < LoginThrottleService::DEFAULT_MAX_PER_IP; $i++) {
            $svc->recordFailedLogin('user' . $i, $this->from('5.5.5.5'));
        }

        $this->assertGreaterThan(0, $svc->loginRetryAfter('someone-new', $this->from('5.5.5.5')));
        $this->assertSame(0, $svc->loginRetryAfter('someone-new', $this->from('6.6.6.6')));
    }

    /**
     * Attempts against usernames that don't exist still count. This is the
     * gap a users-table counter can't cover: there's no row to increment.
     */
    public function testAttemptsOnUnknownUsernamesStillCountTowardTheAddressLimit(): void
    {
        $svc = $this->makeService();

        for ($i = 0; $i < LoginThrottleService::DEFAULT_MAX_PER_IP; $i++) {
            $svc->recordFailedLogin('no-such-user-' . $i, $this->from('5.5.5.5'));
        }

        $this->assertGreaterThan(0, $svc->loginRetryAfter('', $this->from('5.5.5.5')));
    }

    /** A successful login clears the account bucket. */
    public function testClearAccountLiftsTheAccountThrottle(): void
    {
        $svc = $this->makeService();

        for ($i = 0; $i < LoginThrottleService::DEFAULT_MAX_PER_ACCOUNT; $i++) {
            $svc->recordFailedLogin('alice', $this->from('1.1.1.' . $i));
        }
        $this->assertGreaterThan(0, $svc->loginRetryAfter('alice', $this->from('9.9.9.9')));

        $svc->clearAccount('alice');

        $this->assertSame(0, $svc->loginRetryAfter('alice', $this->from('9.9.9.9')));
    }

    /**
     * A success must not wipe the address bucket: one correct password among
     * many wrong ones is what a spraying run looks like.
     */
    public function testClearAccountLeavesTheAddressBucketIntact(): void
    {
        $svc = $this->makeService();

        for ($i = 0; $i < LoginThrottleService::DEFAULT_MAX_PER_IP; $i++) {
            $svc->recordFailedLogin('user' . $i, $this->from('5.5.5.5'));
        }

        $svc->clearAccount('user0');

        $this->assertGreaterThan(0, $svc->loginRetryAfter('anyone', $this->from('5.5.5.5')));
    }

    /** Password-reset requests are limited per address. */
    public function testResetRequestsAreThrottledPerAddress(): void
    {
        $svc = $this->makeService();

        $this->assertSame(0, $svc->resetRetryAfter($this->from('7.7.7.7')));

        for ($i = 0; $i < LoginThrottleService::DEFAULT_MAX_RESETS; $i++) {
            $svc->recordResetRequest($this->from('7.7.7.7'));
        }

        $this->assertGreaterThan(0, $svc->resetRetryAfter($this->from('7.7.7.7')));
        $this->assertSame(0, $svc->resetRetryAfter($this->from('8.8.8.8')));
    }

    /** Reset requests and logins use separate buckets. */
    public function testResetAndLoginBucketsAreIndependent(): void
    {
        $svc = $this->makeService();

        for ($i = 0; $i < LoginThrottleService::DEFAULT_MAX_RESETS; $i++) {
            $svc->recordResetRequest($this->from('7.7.7.7'));
        }

        $this->assertGreaterThan(0, $svc->resetRetryAfter($this->from('7.7.7.7')));
        $this->assertSame(0, $svc->loginRetryAfter('alice', $this->from('7.7.7.7')));
    }

    /** With no resolvable address (CLI), address-keyed limits simply don't apply. */
    public function testMissingAddressDoesNotThrottle(): void
    {
        $svc = $this->makeService();

        for ($i = 0; $i < LoginThrottleService::DEFAULT_MAX_RESETS + 5; $i++) {
            $svc->recordResetRequest([]);
        }

        $this->assertSame(0, $svc->resetRetryAfter([]));
    }
}

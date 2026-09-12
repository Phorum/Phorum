<?php
declare(strict_types=1);

namespace Phorum\Tests\Core;

use Phorum\Core\ClientIp;
use Phorum\Core\Config;
use PHPUnit\Framework\TestCase;

/**
 * Covers client-IP resolution, including the trusted-proxy handling that
 * decides whether the attacker-controlled X-Forwarded-For header is believed
 * at all. Getting this wrong either lets a client forge its own rate-limit
 * bucket, or collapses every visitor behind a CDN into one bucket.
 */
class ClientIpTest extends TestCase
{
    /** Configure ClientIp with the given trusted proxy list. */
    private function withProxies(array $proxies): void
    {
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $key === 'trusted_proxies' ? $proxies : $default
        );
        ClientIp::initialize($config);
    }

    protected function tearDown(): void
    {
        $this->withProxies([]);
    }

    /** With no proxies configured, only REMOTE_ADDR is ever used. */
    public function testUsesRemoteAddrWhenNoProxiesConfigured(): void
    {
        $this->withProxies([]);

        $this->assertSame('203.0.113.9', ClientIp::resolve([
            'REMOTE_ADDR'          => '203.0.113.9',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ]));
    }

    /**
     * The header must be ignored when the request didn't come from a trusted
     * proxy — otherwise any client could spoof its way into a fresh bucket.
     */
    public function testIgnoresForwardedHeaderFromUntrustedSource(): void
    {
        $this->withProxies(['10.0.0.1']);

        $this->assertSame('203.0.113.9', ClientIp::resolve([
            'REMOTE_ADDR'          => '203.0.113.9',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ]));
    }

    /** Behind a configured proxy, the forwarded client address is used. */
    public function testUsesForwardedClientWhenProxyIsTrusted(): void
    {
        $this->withProxies(['10.0.0.1']);

        $this->assertSame('198.51.100.7', ClientIp::resolve([
            'REMOTE_ADDR'          => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
        ]));
    }

    /**
     * A client that prepends fake hops must not be able to shake off its real
     * address: resolution walks from the right, so the first untrusted entry
     * wins and the invented ones to its left are ignored.
     */
    public function testSpoofedLeadingHopsAreIgnored(): void
    {
        $this->withProxies(['10.0.0.1']);

        $this->assertSame('198.51.100.7', ClientIp::resolve([
            'REMOTE_ADDR'          => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9, 8.8.8.8, 198.51.100.7',
        ]));
    }

    /** Chained trusted proxies are walked past to the real client. */
    public function testWalksPastChainedTrustedProxies(): void
    {
        $this->withProxies(['10.0.0.0/8']);

        $this->assertSame('198.51.100.7', ClientIp::resolve([
            'REMOTE_ADDR'          => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7, 10.1.2.3, 10.4.5.6',
        ]));
    }

    /** CIDR blocks are matched, not just exact addresses. */
    public function testTrustsProxyMatchedByCidr(): void
    {
        $this->withProxies(['172.16.0.0/12']);

        $this->assertSame('198.51.100.7', ClientIp::resolve([
            'REMOTE_ADDR'          => '172.20.1.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
        ]));
    }

    /** An address outside the configured block stays untrusted. */
    public function testAddressOutsideCidrIsNotTrusted(): void
    {
        $this->withProxies(['172.16.0.0/12']);

        $this->assertSame('172.32.1.5', ClientIp::resolve([
            'REMOTE_ADDR'          => '172.32.1.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
        ]));
    }

    /** IPv6 proxies work the same way as IPv4. */
    public function testIpv6CidrIsSupported(): void
    {
        $this->withProxies(['2001:db8::/32']);

        $this->assertSame('198.51.100.7', ClientIp::resolve([
            'REMOTE_ADDR'          => '2001:db8::1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
        ]));
    }

    /** A v4 address must not match a v6 block (or vice versa). */
    public function testMixedAddressFamiliesDoNotMatch(): void
    {
        $this->withProxies(['2001:db8::/32']);

        $this->assertSame('203.0.113.9', ClientIp::resolve([
            'REMOTE_ADDR'          => '203.0.113.9',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
        ]));
    }

    /** A trusted proxy sending no forwarded header falls back to its own address. */
    public function testFallsBackToRemoteAddrWhenHeaderAbsent(): void
    {
        $this->withProxies(['10.0.0.1']);

        $this->assertSame('10.0.0.1', ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.1']));
    }

    /** No REMOTE_ADDR at all (CLI) resolves to an empty string, not a crash. */
    public function testMissingRemoteAddrResolvesToEmptyString(): void
    {
        $this->withProxies([]);

        $this->assertSame('', ClientIp::resolve([]));
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Tests\Service;

use Phorum\Mod\Webhooks\WebhookUrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers which outbound webhook targets this server is willing to call.
 *
 * Webhook URLs are admin-supplied and fetched by the server, so an
 * unrestricted target is a server-side request forgery into the internal
 * network and cloud metadata services.
 *
 * Every case uses an IP literal rather than a hostname, so the tests assert
 * the address policy without depending on DNS.
 */
class WebhookUrlGuardTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/mods/webhooks/WebhookUrlGuard.php';
    }

    /**
     * Addresses that must be refused — loopback, link-local (the cloud
     * metadata address), the RFC 1918 ranges, carrier-grade NAT, and the
     * IPv6 equivalents, including the IPv4-mapped form.
     *
     * @param string $url A webhook target that must be rejected.
     */
    #[DataProvider('blockedTargetProvider')]
    public function testInternalTargetsAreRefused(string $url): void
    {
        $guard = new WebhookUrlGuard();

        $this->assertFalse($guard->allows($url), $url . ' should have been refused');
        $this->assertNotNull($guard->problem($url));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedTargetProvider(): array
    {
        return [
            'loopback'            => ['http://127.0.0.1/hook'],
            'loopback other port' => ['http://127.0.0.1:8080/hook'],
            'all-zeros'           => ['http://0.0.0.0/hook'],
            'cloud metadata'      => ['http://169.254.169.254/latest/meta-data/'],
            'rfc1918 10'          => ['http://10.1.2.3/hook'],
            'rfc1918 172'         => ['http://172.16.0.5/hook'],
            'rfc1918 192'         => ['https://192.168.1.1/hook'],
            'cgnat'               => ['http://100.64.0.1/hook'],
            'test-net'            => ['http://192.0.2.10/hook'],
            'benchmark'           => ['http://198.18.0.1/hook'],
            'ipv6 loopback'       => ['http://[::1]/hook'],
            'ipv6 link-local'     => ['http://[fe80::1]/hook'],
            'ipv6 unique-local'   => ['http://[fd00::1]/hook'],
            'ipv4-mapped ipv6'    => ['http://[::ffff:127.0.0.1]/hook'],
        ];
    }

    /**
     * Non-HTTP schemes are refused before any address check — file:// and
     * gopher:// are classic SSRF escalations.
     *
     * @param string $url A malformed or non-HTTP target.
     */
    #[DataProvider('invalidTargetProvider')]
    public function testInvalidTargetsAreRefused(string $url): void
    {
        $this->assertFalse((new WebhookUrlGuard())->allows($url));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidTargetProvider(): array
    {
        return [
            'empty'      => [''],
            'not a url'  => ['definitely not a url'],
            'file'       => ['file:///etc/passwd'],
            'gopher'     => ['gopher://127.0.0.1:6379/_INFO'],
            'ftp'        => ['ftp://example.com/x'],
            'no host'    => ['http:///hook'],
        ];
    }

    /** A public address is allowed. */
    public function testPublicTargetIsAllowed(): void
    {
        $this->assertTrue((new WebhookUrlGuard())->allows('https://8.8.8.8/hook'));
        $this->assertTrue((new WebhookUrlGuard())->allows('https://[2606:4700::1111]/hook'));
    }

    /**
     * Deployments that genuinely deliver to an internal endpoint can opt in
     * through etc/phorum.php.
     */
    public function testPrivateTargetsAllowedWhenExplicitlyEnabled(): void
    {
        $guard = new WebhookUrlGuard(allowPrivateTargets: true);

        $this->assertTrue($guard->allows('http://127.0.0.1:9000/hook'));
        $this->assertTrue($guard->allows('http://10.0.0.5/hook'));
    }

    /** The opt-in still doesn't accept a non-HTTP scheme. */
    public function testOptInStillRejectsNonHttpSchemes(): void
    {
        $guard = new WebhookUrlGuard(allowPrivateTargets: true);

        $this->assertFalse($guard->allows('file:///etc/passwd'));
        $this->assertFalse($guard->allows('gopher://127.0.0.1:6379/_INFO'));
    }

    /** A host that resolves to nothing is refused rather than attempted. */
    public function testUnresolvableHostIsRefused(): void
    {
        $guard = new WebhookUrlGuard();

        $this->assertFalse($guard->allows('https://this-host-should-not-resolve.invalid/hook'));
    }

    /** The refusal message names the setting that would permit the target. */
    public function testRefusalMessageMentionsTheOptIn(): void
    {
        $problem = (new WebhookUrlGuard())->problem('http://169.254.169.254/latest/meta-data/');

        $this->assertStringContainsString('webhook_allow_private_targets', (string) $problem);
    }
}

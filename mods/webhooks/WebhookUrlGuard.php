<?php
declare(strict_types=1);

namespace Phorum\Mod\Webhooks;

use Phorum\Core\ClientIp;

/**
 * Decides whether a configured webhook target is safe for this server to call.
 *
 * Webhook URLs are admin-supplied and the server fetches them itself, which
 * makes them a server-side request forgery vector: `http://169.254.169.254/…`
 * reaches a cloud metadata service, `http://10.0.0.5/…` reaches whatever is on
 * the internal network, and the delivery is a POST with a body. The response
 * never reaches the admin, so this is blind SSRF — still enough to trigger
 * actions on internal services that a request alone is enough to drive.
 *
 * Admins are trusted, but not with the host's network position: an admin
 * session obtained through a stolen cookie or a CSRF should not become a
 * foothold inside the network. Deployments that genuinely need an internal
 * target can set `webhook_allow_private_targets` in etc/phorum.php.
 *
 * Limitation worth knowing: the target host is resolved and checked, but the
 * resolved address is not pinned for the request that follows, so a DNS entry
 * that changes between the check and the call is not covered. Closing that
 * needs address pinning at the HTTP client level.
 */
final class WebhookUrlGuard
{
    /**
     * Ranges PHP's own FILTER_FLAG_NO_PRIV_RANGE / NO_RES_RANGE don't reject
     * but which still shouldn't be reachable from a webhook.
     */
    private const EXTRA_BLOCKED_RANGES = [
        '100.64.0.0/10',    // RFC 6598 shared address space (carrier-grade NAT)
        '192.0.0.0/24',     // IETF protocol assignments
        '192.0.2.0/24',     // TEST-NET-1
        '198.18.0.0/15',    // benchmarking
        '198.51.100.0/24',  // TEST-NET-2
        '203.0.113.0/24',   // TEST-NET-3
        '64:ff9b::/96',     // NAT64, which can map onto IPv4 private space
    ];

    public function __construct(private readonly bool $allowPrivateTargets = false) {}

    /**
     * Describe why $url must not be called, or null when it's fine.
     *
     * Returns a message rather than throwing so both the admin form (which
     * shows it) and the dispatcher (which logs it and skips) can use it.
     */
    public function problem(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return 'A valid http:// or https:// URL is required.';
        }

        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?: ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return 'A valid http:// or https:// URL is required.';
        }

        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        if ($host === '') {
            return 'A valid http:// or https:// URL is required.';
        }

        if ($this->allowPrivateTargets) {
            return null;
        }

        // An IPv6 literal arrives from parse_url wrapped in brackets.
        $host = trim($host, '[]');

        $addresses = $this->resolve($host);
        if ($addresses === []) {
            return 'The host "' . $host . '" could not be resolved.';
        }

        // Every resolved address must be acceptable. A name that answers with
        // both a public and a private address is exactly the shape of a
        // rebinding attempt, so one bad answer rejects the target.
        foreach ($addresses as $address) {
            if ($this->isBlocked($address)) {
                return 'Webhook targets must be public addresses; "' . $host . '" resolves to '
                     . $address . ', which is a private, loopback, or otherwise reserved address. '
                     . 'Set webhook_allow_private_targets in etc/phorum.php to allow internal targets.';
            }
        }

        return null;
    }

    /** True when the configured URL is safe to call. */
    public function allows(string $url): bool
    {
        return $this->problem($url) === null;
    }

    /**
     * Every IP address $host resolves to, or the address itself when $host is
     * already an IP literal. Empty when the name cannot be resolved.
     *
     * @return string[]
     */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $addresses = $v4;
        }

        if (function_exists('dns_get_record')) {
            $v6 = @dns_get_record($host, DNS_AAAA);
            foreach (is_array($v6) ? $v6 : [] as $record) {
                if (!empty($record['ipv6'])) {
                    $addresses[] = (string) $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }

    /** True when $address is in a range a webhook must not reach. */
    private function isBlocked(string $address): bool
    {
        // PHP's own filters cover loopback, link-local (including the cloud
        // metadata address), the RFC 1918 ranges, and IPv4-mapped IPv6 forms.
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        foreach (self::EXTRA_BLOCKED_RANGES as $range) {
            if (ClientIp::inRange($address, $range)) {
                return true;
            }
        }

        return false;
    }
}

<?php
declare(strict_types=1);

namespace Phorum\Core;

/**
 * Resolves the IP address a request actually came from.
 *
 * `REMOTE_ADDR` is the only value a web server can vouch for, so it is the
 * default and the fallback. Behind a CDN or reverse proxy it is the proxy's
 * address, which would make every visitor share one rate-limit bucket — hence
 * the optional `trusted_proxies` config list. `X-Forwarded-For` is attacker-
 * controlled and is consulted *only* when REMOTE_ADDR is itself a configured
 * trusted proxy; the client IP taken from it is the right-most entry that
 * isn't also trusted, so a client that prepends fake hops can't shake off its
 * real address.
 *
 * Deliberately separate from BanService, which stays on raw REMOTE_ADDR: a
 * ban is a deliberate act against a known address, while rate limiting has to
 * distinguish visitors behind shared infrastructure.
 */
final class ClientIp
{
    /** @var string[] CIDR blocks / addresses whose X-Forwarded-For is believed. */
    private static array $trustedProxies = [];

    /** Pick up the trusted-proxy list from config. Call once per request during boot. */
    public static function initialize(Config $config): void
    {
        $configured = $config->get('trusted_proxies', []);
        self::$trustedProxies = is_array($configured) ? array_values(array_filter(
            array_map('trim', array_map('strval', $configured))
        )) : [];
    }

    /**
     * The requesting client's IP address, or '' when there isn't one
     * (CLI, or a server that didn't populate REMOTE_ADDR).
     *
     * @param array<string, mixed>|null $server Defaults to $_SERVER; injectable for tests.
     */
    public static function resolve(?array $server = null): string
    {
        $server     = $server ?? $_SERVER;
        $remoteAddr = trim((string) ($server['REMOTE_ADDR'] ?? ''));

        if ($remoteAddr === '' || !self::isTrustedProxy($remoteAddr)) {
            return $remoteAddr;
        }

        $forwarded = trim((string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded === '') {
            return $remoteAddr;
        }

        // Right-most first: hops closest to us are the ones we can reason
        // about. Walk back past our own trusted proxies and stop at the first
        // address that isn't one — anything further left was supplied by the
        // client and can't be believed.
        $hops = array_reverse(array_map('trim', explode(',', $forwarded)));
        foreach ($hops as $hop) {
            if ($hop !== '' && !self::isTrustedProxy($hop) && filter_var($hop, FILTER_VALIDATE_IP) !== false) {
                return $hop;
            }
        }

        return $remoteAddr;
    }

    /** True when $ip matches a configured trusted proxy address or CIDR block. */
    private static function isTrustedProxy(string $ip): bool
    {
        foreach (self::$trustedProxies as $trusted) {
            if (self::inRange($ip, $trusted)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Match $ip against a bare address or a CIDR block, for IPv4 and IPv6.
     *
     * Public because the webhook URL guard needs the same comparison to decide
     * whether an outbound target sits in a blocked range; a second CIDR
     * implementation is the kind of thing that drifts out of agreement.
     */
    public static function inRange(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        $bits      = (int) $bits;

        // Different families (v4 vs v6) never match, and a malformed range
        // matches nothing rather than everything.
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        if ($bits < 0 || $bits > strlen($ipBin) * 8) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        $restBits   = $bits % 8;

        if ($wholeBytes > 0 && strncmp($ipBin, $subnetBin, $wholeBytes) !== 0) {
            return false;
        }
        if ($restBits === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $restBits)) - 1) & 0xFF;
        return (ord($ipBin[$wholeBytes]) & $mask) === (ord($subnetBin[$wholeBytes]) & $mask);
    }
}

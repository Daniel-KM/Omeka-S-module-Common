<?php declare(strict_types=1);

namespace Common\Stdlib;

/**
 * Get the ip address of the client, even behind a reverse proxy.
 *
 * Backfill of Omeka\Stdlib\ClientIp for Omeka S without it: keep them identical.
 *
 * The headers X-Forwarded-For and X-Real-IP can be written by anybody, so they
 * are used only when the direct peer is a trusted proxy. The client is then the
 * first address from the right of X-Forwarded-For that is not a trusted proxy,
 * since each proxy appends the address of its peer.
 *
 * When no proxy is configured, the proxies with a private, loopback or reserved
 * address are trusted, that is the common case of a reverse proxy in the same
 * host or network (Traefik, nginx, Docker). A server exposed directly has a
 * public peer, so the headers are ignored. When the address is used to grant
 * rights, the strict mode ignores the headers when no proxy is set, so a
 * machine of the local network cannot announce another address.
 */
class ClientIp
{
    /**
     * Address returned when the client address is unknown, for example in cli.
     */
    const UNKNOWN = '::';

    /**
     * @var string[]
     */
    protected $trustedProxies;

    /**
     * @param string[] $trustedProxies Addresses or cidr ranges of the trusted
     *   proxies. When empty, the private and loopback addresses are trusted.
     */
    public function __construct(array $trustedProxies = [])
    {
        $this->trustedProxies = $trustedProxies;
    }

    /**
     * Normalize a list of trusted proxies, keeping only valid addresses and
     * cidr ranges.
     *
     * @param array|string|null $trustedProxies Array or string separated by
     *   spaces, commas or semicolons.
     * @return string[]
     */
    public static function normalizeTrustedProxies($trustedProxies): array
    {
        $trustedProxies = preg_split('/[\s,;]+/', implode(' ', is_array($trustedProxies) ? $trustedProxies : [(string) $trustedProxies]));
        $result = [];
        foreach ($trustedProxies as $proxy) {
            $parts = explode('/', $proxy, 2);
            if (!static::isValidIp($parts[0])) {
                continue;
            }
            if (isset($parts[1])) {
                $maxBits = strpos($parts[0], ':') === false ? 32 : 128;
                if (!ctype_digit($parts[1]) || (int) $parts[1] > $maxBits) {
                    continue;
                }
            }
            $result[] = $proxy;
        }
        return array_values(array_unique($result));
    }

    /**
     * @return string[]
     */
    public function getTrustedProxies(): array
    {
        return $this->trustedProxies;
    }

    /**
     * Get the ip address of the client.
     *
     * @param array|null $server Server params, default to $_SERVER.
     * @param string[]|null $trustedProxies Specific trusted proxies, else the
     *   configured ones.
     * @param bool $strict When no proxy is set, ignore the headers instead of
     *   trusting the private addresses and the server itself. On a shared
     *   hosting, another account may send requests from the address of the
     *   server, so the strict mode is used for the access rights and the
     *   locks, that require the explicit proxies.
     */
    public function getIp(?array $server = null, ?array $trustedProxies = null, bool $strict = false): string
    {
        $server ??= $_SERVER;
        $trustedProxies ??= $this->trustedProxies;
        if ($strict && !$trustedProxies) {
            $remoteIp = trim((string) ($server['REMOTE_ADDR'] ?? ''));
            return static::isValidIp($remoteIp) ? $remoteIp : self::UNKNOWN;
        }

        $remoteIp = trim((string) ($server['REMOTE_ADDR'] ?? ''));
        if (!static::isValidIp($remoteIp)) {
            return self::UNKNOWN;
        }
        if (!$this->isTrusted($remoteIp, $trustedProxies)
            && !static::isSameServer($remoteIp, $server)
        ) {
            return $remoteIp;
        }

        $forwarded = trim((string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $clientIp = $remoteIp;
            foreach (array_reverse(array_map('trim', explode(',', $forwarded))) as $ip) {
                if (!static::isValidIp($ip)) {
                    break;
                }
                $clientIp = $ip;
                if (!$this->isTrusted($ip, $trustedProxies)) {
                    break;
                }
            }
            return $clientIp;
        }

        $realIp = trim((string) ($server['HTTP_X_REAL_IP'] ?? ''));
        return static::isValidIp($realIp) ? $realIp : $remoteIp;
    }

    /**
     * Check if an address is a trusted proxy.
     *
     * @param string[]|null $trustedProxies Specific trusted proxies, else the
     *   configured ones.
     */
    public function isTrusted(string $ip, ?array $trustedProxies = null): bool
    {
        $trustedProxies ??= $this->trustedProxies;
        if (!$trustedProxies) {
            return static::isPrivateIp($ip);
        }
        foreach ($trustedProxies as $proxy) {
            if (static::ipInCidr($ip, (string) $proxy)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Describe how the server receives the requests, to help to configure it.
     *
     * The status is one of:
     * - "direct": no proxy headers;
     * - "proxy": proxy headers from a trusted peer;
     * - "untrusted": proxy headers from a peer that is not trusted, so the
     *   headers are ignored. When the peer is the reverse proxy of the server,
     *   it should be added to the trusted proxies, else it may be a forgery.
     *
     * @param array|null $server Server params, default to $_SERVER.
     * @param string[]|null $trustedProxies Specific trusted proxies, else the
     *   configured ones.
     * @return array{peer: string, status: string}
     */
    public function detect(?array $server = null, ?array $trustedProxies = null): array
    {
        $server ??= $_SERVER;
        $peer = trim((string) ($server['REMOTE_ADDR'] ?? ''));
        $hasHeaders = trim((string) ($server['HTTP_X_FORWARDED_FOR'] ?? '')) !== ''
            || trim((string) ($server['HTTP_X_REAL_IP'] ?? '')) !== '';
        if (!$hasHeaders || !static::isValidIp($peer)) {
            $status = 'direct';
        } elseif ($this->isTrusted($peer, $trustedProxies) || static::isSameServer($peer, $server)) {
            $status = 'proxy';
        } else {
            $status = 'untrusted';
        }
        return [
            'peer' => $peer,
            'status' => $status,
        ];
    }

    /**
     * Check if the request comes from the server itself, for example from a
     * reverse proxy on the same machine (Caddy or Nginx in front of Apache).
     *
     * A remote client cannot connect with the address of the server, so the
     * proxy headers of such a request are set by a local process.
     */
    public static function isSameServer(string $ip, ?array $server = null): bool
    {
        $server ??= $_SERVER;
        $serverIp = trim((string) ($server['SERVER_ADDR'] ?? ''));
        return static::isValidIp($ip)
            && static::isValidIp($serverIp)
            && inet_pton($ip) === inet_pton($serverIp);
    }

    /**
     * Check if an address is a private, loopback or reserved one.
     */
    public static function isPrivateIp(string $ip): bool
    {
        return static::isValidIp($ip)
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Check if an address is a single address or is within a cidr range.
     */
    public static function ipInCidr(string $ip, string $cidr): bool
    {
        $cidr = trim($cidr);
        if ($cidr === '') {
            return false;
        }
        if (strpos($cidr, '/') === false) {
            $ipBin = @inet_pton($ip);
            return $ipBin !== false && $ipBin === @inet_pton($cidr);
        }

        [$range, $bits] = explode('/', $cidr, 2);
        $ipBin = @inet_pton($ip);
        $rangeBin = @inet_pton($range);
        if ($ipBin === false || $rangeBin === false || strlen($ipBin) !== strlen($rangeBin)) {
            return false;
        }

        $bits = (int) $bits;
        if ($bits < 0 || $bits > strlen($ipBin) * 8) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        if ($bytes && substr($ipBin, 0, $bytes) !== substr($rangeBin, 0, $bytes)) {
            return false;
        }
        $remainder = $bits % 8;
        if (!$remainder) {
            return true;
        }
        $mask = chr((0xFF << (8 - $remainder)) & 0xFF);
        return ($ipBin[$bytes] & $mask) === ($rangeBin[$bytes] & $mask);
    }

    protected static function isValidIp(string $ip): bool
    {
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}

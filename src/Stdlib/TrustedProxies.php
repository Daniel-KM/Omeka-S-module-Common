<?php declare(strict_types=1);

namespace Common\Stdlib;

use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Detect the reverse proxy of the current request to fill the trusted proxies.
 *
 * The strict mode of the client ip, used to grant rights or to lock out, needs
 * an explicit list. A private address changes when a container is recreated, so
 * its whole private range is trusted, like the automatic mode does.
 */
class TrustedProxies
{
    const PRIVATE_RANGES = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '169.254.0.0/16',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
    ];

    /**
     * Get the proxy to trust for a request with proxy headers, if any.
     */
    public static function detect(array $server): ?string
    {
        $remote = (string) ($server['REMOTE_ADDR'] ?? '');
        if (empty($server['HTTP_X_FORWARDED_FOR']) && empty($server['HTTP_X_REAL_IP'])
            || filter_var($remote, FILTER_VALIDATE_IP) === false
        ) {
            return null;
        }
        return static::widen($remote);
    }

    /**
     * Get the private range of an address, or the address itself.
     */
    public static function widen(string $ip): string
    {
        foreach (self::PRIVATE_RANGES as $range) {
            if (ClientIp::ipInCidr($ip, $range)) {
                return $range;
            }
        }
        return $ip;
    }

    /**
     * Fill the trusted proxies of the main settings when no list is set.
     *
     * @return string|null The trusted proxy added.
     */
    public static function autoDetect(ServiceLocatorInterface $services, ?array $server = null): ?string
    {
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        if (!empty($config['trusted_proxies']) || $settings->get('trusted_proxies')) {
            return null;
        }

        $proxy = static::detect($server ?? $_SERVER);
        if ($proxy === null) {
            return null;
        }

        $settings->set('trusted_proxies', [$proxy]);
        $services->get('ControllerPluginManager')->get('messenger')->addSuccess(new PsrMessage(
            'Trusted proxy automatically detected and added to the security section of the main settings: {ip}. Review and adjust the list if more than one proxy is involved.', // @translate
            ['ip' => $proxy]
        ));
        return $proxy;
    }
}

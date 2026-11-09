<?php declare(strict_types=1);

namespace Common\Service\Stdlib;

use Common\Stdlib\ClientIp;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Backfill of Omeka\Service\ClientIpFactory.
 */
class ClientIpFactory implements FactoryInterface
{
    /**
     * The trusted proxies of the config file take precedence over the setting.
     */
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $config = $services->get('Config');
        $trustedProxies = ClientIp::normalizeTrustedProxies($config['trusted_proxies'] ?? []);
        if (!$trustedProxies) {
            try {
                $trustedProxies = ClientIp::normalizeTrustedProxies($services->get('Omeka\Settings')->get('trusted_proxies', []));
            } catch (\Exception $e) {
                // The settings are not available before install.
            }
        }
        return new ClientIp($trustedProxies);
    }
}

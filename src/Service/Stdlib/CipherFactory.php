<?php declare(strict_types=1);

namespace Common\Service\Stdlib;

use Common\Stdlib\Cipher;
use Common\Stdlib\SecretKey;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class CipherFactory implements FactoryInterface
{
    /**
     * Create the Cipher service from the resolved secret key.
     *
     * @return Cipher
     */
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new Cipher(SecretKey::resolve());
    }
}

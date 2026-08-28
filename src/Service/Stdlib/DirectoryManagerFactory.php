<?php declare(strict_types=1);

namespace Common\Service\Stdlib;

use Common\Stdlib\DirectoryManager;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class DirectoryManagerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new DirectoryManager(
            $services->get('Omeka\Logger')
        );
    }
}

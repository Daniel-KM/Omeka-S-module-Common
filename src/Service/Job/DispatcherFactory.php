<?php declare(strict_types=1);

namespace Common\Service\Job;

use Common\Job\Dispatcher;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class DispatcherFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new Dispatcher(
            $services->get('Omeka\Job\DispatchStrategy'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Logger'),
            $services->get('Omeka\AuthenticationService')
        );
    }
}

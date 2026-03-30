<?php declare(strict_types=1);

namespace Common\Service\Stdlib;

use Common\Stdlib\DeferredJobDispatch;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class DeferredJobDispatchFactory implements FactoryInterface
{
    public function __invoke(
        ContainerInterface $services,
        $requestedName,
        ?array $options = null
    ) {
        return new DeferredJobDispatch($services);
    }
}

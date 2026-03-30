<?php declare(strict_types=1);

namespace Common\Service\View\Helper;

use Common\View\Helper\ModuleConfigNav;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ModuleConfigNavFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ModuleConfigNav(
            $services->get('Omeka\ModuleManager')
        );
    }
}

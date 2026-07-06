<?php declare(strict_types=1);

namespace Common\Service\ViewHelper;

use Common\View\Helper\Trigger;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TriggerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new Trigger(
            $services->get('EventManager'),
            $services->get('ControllerPluginManager')
        );
    }
}

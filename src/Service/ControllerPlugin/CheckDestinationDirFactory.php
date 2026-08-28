<?php declare(strict_types=1);

namespace Common\Service\ControllerPlugin;

use Common\Mvc\Controller\Plugin\CheckDestinationDir;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class CheckDestinationDirFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new CheckDestinationDir(
            $services->get('Common\DirectoryManager')
        );
    }
}

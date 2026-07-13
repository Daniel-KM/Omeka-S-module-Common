<?php declare(strict_types=1);

namespace Common\Service\ViewHelper;

use Common\View\Helper\EasyMeta;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class EasyMetaFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new EasyMeta(
            $services->get('Common\EasyMeta')
        );
    }
}

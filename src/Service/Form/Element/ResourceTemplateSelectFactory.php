<?php declare(strict_types=1);

namespace Common\Service\Form\Element;

use Common\Form\Element\ResourceTemplateSelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ResourceTemplateSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new ResourceTemplateSelect;
        $element->setApiManager($services->get('Omeka\ApiManager'));
        return $element;
    }
}

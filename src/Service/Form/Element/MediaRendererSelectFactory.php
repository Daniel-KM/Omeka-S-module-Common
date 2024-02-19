<?php declare(strict_types=1);

namespace Common\Service\Form\Element;

use Common\Form\Element\MediaRendererSelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MediaRendererSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // See MediaTypeSelect to get the list of used renderers.

        /** @var \Omeka\Media\Renderer\Manager $rendererManager */
        $rendererManager = $services->get('Omeka\Media\Renderer\Manager');

        $renderers = [];
        foreach ($rendererManager->getRegisteredNames() as $renderer) {
            $renderers[$renderer] = $rendererManager->get($renderer)->getLabel();
        }

        $element = new MediaRendererSelect(null, $options ?? []);
        return $element
            ->setValueOptions($renderers)
            ->setEmptyOption('Select media renderers…'); // @translate
    }
}

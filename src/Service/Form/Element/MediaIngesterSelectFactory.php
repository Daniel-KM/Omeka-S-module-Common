<?php declare(strict_types=1);

namespace Common\Service\Form\Element;

use Common\Form\Element\MediaIngesterSelect;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class MediaIngesterSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        // See MediaTypeSelect to get the list of used ingesters.

        /** @var \Omeka\Media\Ingester\Manager $ingesterManager */
        $ingesterManager = $services->get('Omeka\Media\Ingester\Manager');

        $ingesters = [];
        foreach ($ingesterManager->getRegisteredNames() as $ingester) {
            $ingesters[$ingester] = $ingesterManager->get($ingester)->getLabel();
        }

        $element = new MediaIngesterSelect(null, $options ?? []);
        return $element
            ->setValueOptions($ingesters)
            ->setEmptyOption('Select media ingesters…'); // @translate
    }
}

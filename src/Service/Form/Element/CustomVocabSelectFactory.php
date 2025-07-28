<?php declare(strict_types=1);

namespace Common\Service\Form\Element;

use Common\Form\Element\CustomVocabSelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CustomVocabSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return (new CustomVocabSelect(null, $options ?? []))
            ->setApiManager($services->get('Omeka\ApiManager'));
    }
}

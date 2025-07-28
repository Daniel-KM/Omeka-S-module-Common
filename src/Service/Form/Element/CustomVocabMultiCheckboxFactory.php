<?php declare(strict_types=1);

namespace Common\Service\Form\Element;

use Common\Form\Element\CustomVocabMultiCheckbox;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CustomVocabMultiCheckboxFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return (new CustomVocabMultiCheckbox(null, $options ?? []))
            ->setApiManager($services->get('Omeka\ApiManager'));
    }
}

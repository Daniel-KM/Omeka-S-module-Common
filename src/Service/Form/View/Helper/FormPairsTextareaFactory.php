<?php declare(strict_types=1);

namespace Common\Service\Form\View\Helper;

use Common\Form\View\Helper\FormPairsTextarea;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class FormPairsTextareaFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        // The form element manager builds the element of the cell of the
        // value, that may require services, like ItemSetSelect and the api.
        return new FormPairsTextarea(
            $services->get('FormElementManager')
        );
    }
}

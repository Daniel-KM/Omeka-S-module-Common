<?php declare(strict_types=1);

namespace Common\Service\Delegator;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;

/**
 * Map the elements provided by Common to their view helpers.
 *
 * Only Common's own elements are registered here; the other custom types
 * (recaptcha, ckeditor, color_picker, Asset, Query…) are already mapped by the
 * core delegator.
 */
class FormElementDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name,
        callable $callback, ?array $options = null
    ) {
        $formElement = $callback();
        $formElement->addType('note', 'formNote');
        $formElement->addClass('Omeka\Form\Element\Secret', 'formSecret');
        $formElement->addClass('Common\Form\Element\Secret', 'formSecret');
        return $formElement;
    }
}

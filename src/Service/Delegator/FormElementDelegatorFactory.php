<?php declare(strict_types=1);

namespace Common\Service\Delegator;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;

/**
 * Map custom Common element types to their view helpers.
 */
class FormElementDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name, callable $callback, ?array $options = null)
    {
        $formElement = $callback();
        $formElement->addType('note', 'formNote');
        return $formElement;
    }
}

<?php declare(strict_types=1);

namespace Common\Service\ControllerPlugin;

use Common\Mvc\Controller\Plugin\PrepareMessage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PrepareMessageFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new PrepareMessage(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\Mailer'),
            $services->get('Omeka\Settings')
        );
    }
}

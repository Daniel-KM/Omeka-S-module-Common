<?php declare(strict_types=1);

namespace Common\Service\ControllerPlugin;

use Common\Mvc\Controller\Plugin\SendEmail;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SendEmailFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SendEmail(
            $services->get('Omeka\Logger'),
            $services->get('Omeka\Mailer'),
            $services->get('Omeka\Settings')
        );
    }
}

<?php declare(strict_types=1);

namespace Common\Service\ControllerPlugin;

use Common\Mvc\Controller\Plugin\SendEmail;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class SendEmailFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new SendEmail(
            $services->get('Omeka\Logger'),
            $services->get('Omeka\Mailer'),
            $services->get('Omeka\Settings')
        );
    }
}

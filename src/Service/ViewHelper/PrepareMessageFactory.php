<?php declare(strict_types=1);

namespace Common\Service\ViewHelper;

use Common\View\Helper\PrepareMessage;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

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

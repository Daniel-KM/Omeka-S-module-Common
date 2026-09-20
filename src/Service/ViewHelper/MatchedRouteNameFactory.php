<?php declare(strict_types=1);

namespace Common\Service\ViewHelper;

use Common\View\Helper\MatchedRouteName;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class MatchedRouteNameFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        // There is no route match outside of a routed http request, in
        // particular in a background job or in a cli process.
        $routeMatch = $services->get('Application')->getMvcEvent()->getRouteMatch();
        return new MatchedRouteName(
            $routeMatch ? (string) $routeMatch->getMatchedRouteName() : ''
        );
    }
}

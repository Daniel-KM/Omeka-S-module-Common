<?php declare(strict_types=1);

namespace Common\View\Helper;

use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\Controller\PluginManager as ControllerPluginManager;
use Laminas\View\Helper\AbstractHelper;

/**
 * Drop-in override of the core "trigger" view helper, until the upstream fix
 * for the same issue lands in Omeka S 4.3.
 *
 * The core helper short-circuits when there is no route match (i.e. on an error
 * / 404 layout), preventing any listener attached to "view.layout" from
 * observing the event. This breaks legitimate cross-cutting concerns such as
 * the Privacy module replacing external font links on every layout.
 *
 * This override fires the event with a fallback identifier
 * "Omeka\Controller\Error" in that case, and swallows listener exceptions only
 * when rendering an error, so a faulty listener cannot cascade into a second
 * exception and hide the original failure.
 *
 * @see https://github.com/omeka/omeka-s/pull/<TBD>
 */
class Trigger extends AbstractHelper
{
    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var ControllerPluginManager
     */
    protected $controllerPluginManager;

    public function __construct(EventManagerInterface $eventManager, ControllerPluginManager $controllerPluginManager)
    {
        $this->events = $eventManager;
        $this->controllerPluginManager = $controllerPluginManager;
    }

    public function __invoke($name, array $params = [], $filter = false, ?array $ids = null)
    {
        $controller = $this->controllerPluginManager->getController();
        $routeMatch = $controller ? $controller->getEvent()->getRouteMatch() : null;
        if ($filter) {
            $params = $this->events->prepareArgs($params);
        }
        $event = new Event($name, $this->getView(), $params);
        $isError = !$routeMatch;
        $this->events->setIdentifiers($ids ?: [$isError ? 'Omeka\Controller\Error' : $routeMatch->getParam('controller')]);
        if ($isError) {
            try {
                $this->events->triggerEvent($event);
            } catch (\Throwable $e) {
            }
        } else {
            $this->events->triggerEvent($event);
        }
        if ($filter) {
            return $params;
        }
    }
}

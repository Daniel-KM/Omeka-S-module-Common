<?php declare(strict_types=1);

namespace Common\Service\Delegator;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;

/**
 * Delegator to restructure admin site navigation based on parent keys in config.
 *
 * Modules can define navigation items with 'parent_route' and 'parent_action'
 * keys to specify that the item should be moved as a child of another item.
 *
 * Example in module.config.php:
 * ```php
 * 'navigation' => [
 *     'site' => [
 *         [
 *             'label' => 'Menus',
 *             'route' => 'admin/site/slug/menu',
 *             'parent_route' => 'admin/site/slug/action',  // Parent route
 *             'parent_action' => 'navigation',              // Parent action
 *             // ...
 *         ],
 *     ],
 * ],
 * ```
 *
 * Both parent_route and parent_action must match for proper identification.
 * This delegator only applies to admin site navigation (Laminas\Navigation\Site),
 * not to public site navigation.
 */
class SiteNavigationDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name, callable $callback, ?array $options = null)
    {
        /** @var \Laminas\Navigation\Navigation $navigation */
        $navigation = $callback();

        // Collect pages that need to be moved (have parent_route and parent_action).
        $pagesToMove = [];
        foreach ($navigation as $page) {
            $parentRoute = $page->get('parent_route');
            $parentAction = $page->get('parent_action');
            if ($parentRoute && $parentAction) {
                $pagesToMove[] = [
                    'page' => $page,
                    'parent_route' => $parentRoute,
                    'parent_action' => $parentAction,
                ];
            }
        }

        if (empty($pagesToMove)) {
            return $navigation;
        }

        // Build index of pages by route+action for quick lookup.
        $pagesByRouteAction = [];
        foreach ($navigation as $page) {
            $route = $page->getRoute();
            $action = $page->get('action');
            if ($route && $action) {
                $key = $route . '::' . $action;
                $pagesByRouteAction[$key] = $page;
            }
        }

        // Move pages under their parent.
        foreach ($pagesToMove as $moveInfo) {
            $page = $moveInfo['page'];
            $key = $moveInfo['parent_route'] . '::' . $moveInfo['parent_action'];

            // Find parent page by route + action.
            if (!isset($pagesByRouteAction[$key])) {
                continue;
            }

            $parentPage = $pagesByRouteAction[$key];

            // Remove from root level.
            $navigation->removePage($page);

            // Remove the parent properties (no longer needed).
            $page->set('parent_route', null);
            $page->set('parent_action', null);

            // Add as child of parent.
            $parentPage->addPage($page);
        }

        return $navigation;
    }
}

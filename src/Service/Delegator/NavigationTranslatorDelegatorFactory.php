<?php declare(strict_types=1);

namespace Common\Service\Delegator;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\DelegatorFactoryInterface;
use Omeka\Site\Navigation\Translator;

/**
 * Delegator to protect against missing routes in navigation.
 *
 * During module upgrades, routes defined by modules may not be available,
 * causing RuntimeException when the NavigationTranslator tries to generate
 * URLs. This delegator wraps the translator to catch these errors.
 *
 * This is particularly useful when a module using TraitModule needs protection
 * during the upgrade of the Common module, when Common's services are not yet
 * available but TraitModule is loaded directly.
 */
class NavigationTranslatorDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(ContainerInterface $container, $name, callable $callback, ?array $options = null)
    {
        /** @var \Omeka\Site\Navigation\Translator $translator */
        $translator = $callback();

        // Return a proxy that catches route errors.
        return new class($translator, $container) extends Translator {
            /**
             * @var \Omeka\Site\Navigation\Translator
             */
            protected $innerTranslator;

            /**
             * @var \Interop\Container\ContainerInterface
             */
            protected $container;

            public function __construct(Translator $translator, ContainerInterface $container)
            {
                $this->innerTranslator = $translator;
                $this->container = $container;
            }

            public function toZend(\Omeka\Api\Representation\SiteRepresentation $site)
            {
                // Get the url helper to test routes.
                $urlHelper = $this->container->get('ViewHelperManager')->get('Url');

                $manager = $this->container->get('Omeka\Site\NavigationLinkManager');
                $i18n = $this->container->get('MvcTranslator');

                // Cache route validity to avoid calling $urlHelper() for every link.
                $validRoutes = [];

                $buildLinks = function ($linksIn) use (&$buildLinks, $site, $manager, $urlHelper, $i18n, &$validRoutes) {
                    $linksOut = [];
                    foreach ($linksIn as $key => $data) {
                        // Skip links whose type is not available (module not active).
                        if (!$manager->has($data['type'])) {
                            continue;
                        }
                        $linkType = $manager->get($data['type']);
                        $linkData = $data['data'];
                        $linkZend = $linkType->toZend($linkData, $site);

                        // Skip links with routes that don't exist (module being upgraded).
                        if (isset($linkZend['route'])) {
                            $route = $linkZend['route'];
                            if (!isset($validRoutes[$route])) {
                                try {
                                    $urlHelper($route, $linkZend['params'] ?? []);
                                    $validRoutes[$route] = true;
                                } catch (\Laminas\Router\Exception\RuntimeException $e) {
                                    $validRoutes[$route] = false;
                                }
                            }
                            if (!$validRoutes[$route]) {
                                continue;
                            }
                        }

                        $linksOut[$key] = $linkZend;
                        $linksOut[$key]['label'] = $this->innerTranslator->getLinkLabel($linkType, $linkData, $site);

                        if (isset($data['links'])) {
                            $linksOut[$key]['pages'] = $buildLinks($data['links']);
                        }
                    }
                    return $linksOut;
                };

                $links = $buildLinks($site->navigation());

                if (!$links) {
                    // The site must have at least one page for navigation to work.
                    $links = [[
                        'label' => $i18n->translate('Home'),
                        'route' => 'site',
                        'params' => [
                            'site-slug' => $site->slug(),
                        ],
                    ]];
                }

                return $links;
            }

            public function toJstree(\Omeka\Api\Representation\SiteRepresentation $site)
            {
                return $this->innerTranslator->toJstree($site);
            }

            public function fromJstree(array $jstree)
            {
                return $this->innerTranslator->fromJstree($jstree);
            }

            public function getLinkLabel(\Omeka\Site\Navigation\Link\LinkInterface $linkType, array $data, \Omeka\Api\Representation\SiteRepresentation $site)
            {
                return $this->innerTranslator->getLinkLabel($linkType, $data, $site);
            }

            public function getLinkUrl(\Omeka\Site\Navigation\Link\LinkInterface $linkType, array $data, \Omeka\Api\Representation\SiteRepresentation $site)
            {
                return $this->innerTranslator->getLinkUrl($linkType, $data, $site);
            }
        };
    }
}

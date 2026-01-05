<?php declare(strict_types=1);

namespace Common\Service\ViewHelper;

use Common\View\Helper\AssetUrl;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Module\Manager as ModuleManager;

/**
 * Service factory for the assetUrl view helper.
 */
class AssetUrlFactory implements FactoryInterface
{
    /**
     * Create and return the assetUrl view helper.
     *
     * Override core helper to:
     * - Allow to override internal assets in a generic way.
     * - Get the current theme dynamically because the view helper is created
     *   before the theme is set in MvcListeners for sites. This allows theme
     *   assets like thumbnails fallbacks to be used in sites.
     *
     * @return AssetUrl
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $assetConfig = $services->get('Config')['assets'];
        return new AssetUrl(
            $services->get('Omeka\Site\ThemeManager'),
            $services->get('Omeka\ModuleManager')->getModulesByState(ModuleManager::STATE_ACTIVE),
            $assetConfig['use_externals'] ? $assetConfig['externals'] : [],
            $assetConfig['internals'] ?? []
        );
    }
}

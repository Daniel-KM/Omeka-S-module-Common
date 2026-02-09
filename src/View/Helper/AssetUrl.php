<?php declare(strict_types=1);

namespace Common\View\Helper;

use Omeka\Site\Theme\Manager as ThemeManager;

/**
 * View helper for returning a path to an asset.
 *
 * Override core helper to allow to override internal assets in a generic way.
 *
 * Fix core issue: get the current theme dynamically because the view helper is
 * created before the theme is set in MvcListeners for sites. This allows theme
 * assets like thumbnails fallbacks to be used.
 */
class AssetUrl extends \Omeka\View\Helper\AssetUrl
{
    /**
     * @var ThemeManager
     */
    protected $themeManager;

    /**
     * @var array Array of all internals overrides to use for asset URLs
     */
    protected $internals;

    public function __construct(ThemeManager $themeManager, $modules, $externals, $internals)
    {
        $this->themeManager = $themeManager;
        $this->activeModules = $modules;
        $this->externals = $externals;
        $this->internals = $internals;
    }

    public function __invoke($file, $module = null, $override = false, $versioned = true, $absolute = false)
    {
        if ($module === 'Omeka'
            && isset($this->internals[$file])
            && array_key_exists($this->internals[$file], $this->activeModules)
        ) {
            $view = $this->getView();
            return sprintf(
                self::MODULE_ASSETS_PATH,
                ($absolute ? $view->serverUrl() : '') . $view->basePath(),
                $this->internals[$file],
                $file,
                $versioned ? '?v=' . $this->activeModules[$this->internals[$file]]->getIni('version') : ''
            );
        }

        // Get current theme dynamically (may be set after helper creation).
        // Cache once resolved to avoid repeated calls (helper is invoked many
        // times per page).
        if ($this->currentTheme === null) {
            $this->currentTheme = $this->themeManager->getCurrentTheme();
        }

        return parent::__invoke($file, $module, $override, $versioned, $absolute);
    }
}

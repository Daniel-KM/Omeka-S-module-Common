<?php declare(strict_types=1);

namespace Common\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Render a navigation bar to switch between related module config forms.
 *
 * Only active and configurable modules are listed.
 */
class ModuleConfigNav extends AbstractHelper
{
    /**
     * @var \Omeka\Module\Manager
     */
    protected $moduleManager;

    public function __construct($moduleManager)
    {
        $this->moduleManager = $moduleManager;
    }

    /**
     * @param array $moduleNames List of module to include.
     * @param string $currentName The current module.
     * @return string Html navigation bar.
     */
    public function __invoke(array $moduleNames, string $currentName): string
    {
        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $escape = $plugins->get('escapeHtml');
        $escapeAttr = $plugins->get('escapeHtmlAttr');

        $links = [];
        foreach ($moduleNames as $moduleName) {
            $module = $this->moduleManager->getModule($moduleName);
            if (!$module || $module->getState() !== 'active') {
                continue;
            }
            $info = $module->getIni();
            if (empty($info['configurable'])) {
                continue;
            }
            $label = $info['name'] ?? $moduleName;
            $url = $view->url(
                'admin/default',
                ['controller' => 'module','action' => 'configure'],
                ['query' => ['id' => $moduleName]]
            );
            $class = ($moduleName === $currentName) ? 'active' : '';
            $links[] = sprintf(
                '<li class="%s"><a href="%s">%s</a></li>',
                $class,
                $escapeAttr($url),
                $escape($label)
            );
        }

        if (count($links) <= 1) {
            return '';
        }

        $linksHtml = implode("\n", $links);
        return <<<HTML
            <nav><ul class="section-nav module-config-nav">
                $linksHtml
            </ul></nav>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var mcn = document.querySelector('.module-config-nav');
                if (!mcn) return;
                var activeLi = mcn.querySelector('li.active');
                if (!activeLi) return;
                document.querySelectorAll('.section-nav:not(.module-config-nav) a').forEach(function(a) {
                    a.addEventListener('click', function() {
                        setTimeout(function() { activeLi.classList.add('active'); }, 0);
                    });
                });
            });
            </script>
            HTML;
    }
}

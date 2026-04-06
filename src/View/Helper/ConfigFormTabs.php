<?php declare(strict_types=1);

namespace Common\View\Helper;

use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Laminas\View\Helper\AbstractHelper;

/**
 * Render a Laminas form as Omeka admin tabbed sections.
 *
 * The list of tabs is passed explicitly:
 *   [
 *       'tab-id' => [
 *           'label' => 'Tab label', // translated 'elements' => ['name1',
 *           'name2', 'fieldset1'],
 *       ],
 *       ...
 *   ]
 *
 * Elements listed in a tab are removed from the form before the remaining
 * elements are appended to the last tab (fallback bucket). A tab without an
 * explicit 'elements' key receives all remaining ones.
 */
class ConfigFormTabs extends AbstractHelper
{
    public function __invoke(Form $form, array $tabs, string $trigger = ''): string
    {
        $view = $this->getView();
        $sectionNav = [];
        foreach ($tabs as $id => $tab) {
            $sectionNav[$id] = $tab['label'] ?? $id;
        }

        $rendered = [];
        $fallbackId = null;
        foreach ($tabs as $id => $tab) {
            if (!array_key_exists('elements', $tab)) {
                $fallbackId = $id;
                $rendered[$id] = '';
                continue;
            }
            $subForm = new Fieldset();
            foreach ((array) $tab['elements'] as $name) {
                if (!$form->has($name)) {
                    continue;
                }
                $subForm->add($form->get($name));
                $form->remove($name);
            }
            $rendered[$id] = ($tab['content_before'] ?? '')
                . $this->renderCollection($subForm)
                . ($tab['content_after'] ?? '');
        }

        if ($fallbackId === null) {
            $fallbackId = array_key_last($tabs);
        }
        $fallback = $tabs[$fallbackId];
        $rendered[$fallbackId] = ($fallback['content_before'] ?? '')
            . $this->renderCollection($form)
            . ($fallback['content_after'] ?? '')
            . $rendered[$fallbackId];

        $html = $view->sectionNav($sectionNav, $trigger);
        $first = true;
        foreach ($rendered as $id => $content) {
            $class = 'section' . ($first ? ' active' : '');
            $first = false;
            $html .= sprintf(
                '<div id="%s" class="%s">%s</div>',
                $view->escapeHtmlAttr($id),
                $class,
                $content
            );
        }
        return $html;
    }

    protected function renderCollection(Fieldset $fieldset): string
    {
        $view = $this->getView();
        $hasGroups = (bool) $fieldset->getOption('element_groups');
        if ($hasGroups && $view->getHelperPluginManager()->has('formCollectionElementGroups')) {
            return $view->plugin('formCollectionElementGroups')->render($fieldset);
        }
        return $view->formCollection($fieldset, false);
    }
}

<?php declare(strict_types=1);

namespace Common\View\Helper;

use Laminas\Form\Fieldset;
use Laminas\Form\FieldsetInterface;
use Laminas\Form\Form;
use Laminas\View\Helper\AbstractHelper;

/**
 * Render any Laminas form as Omeka admin tabbed sections.
 *
 * Not specific to config forms: it works on any form (config, resource, site
 * settings…).
 *
 * The list of tabs may be passed explicitly:
 *   [
 *       'tab-id' => [
 *           'label' => 'Tab label', // @translated
 *           'elements' => ['name1', 'name2', 'fieldset1'],
 *       ],
 *       ...
 *   ]
 *
 * Elements listed in a tab are removed from the form before the remaining
 * elements are appended to the last tab (fallback bucket). A tab without an
 * explicit 'elements' key receives all remaining ones.
 *
 * When $tabs is empty, one tab is derived from each entry of the form option
 * "element_tabs", and every element/fieldset is dispatched to its own "tab"
 * option. Tabs are independent from "element_groups" (which still render as
 * sections, possibly nested inside a tab).
 */
class FormTabs extends AbstractHelper
{
    public function __invoke(Form $form, array $tabs = [], string $trigger = ''): string
    {
        if (!$tabs) {
            $tabs = $this->tabsFromOption($form);
        }

        $view = $this->getView();

        // Prefix the section ids so they never collide with ids already used by
        // the admin layout (for example the global "#advanced" button of the
        // search bar), which would leave the matching tab empty.
        $prefix = $this->idPrefix($form);

        $sectionNav = [];
        foreach ($tabs as $id => $tab) {
            $sectionNav[$prefix . $id] = $tab['label'] ?? $id;
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
            // Keep the groups, else the elements of a tab are rendered as a
            // flat list while the ones of the fallback tab keep their sections.
            // The groups without any element in the tab are not rendered.
            $subForm->setOption('element_groups', $form->getOption('element_groups'));
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

        // Let the tab bar grow to several rows: the admin default fixes its
        // height to a single row, which would clip the extra rows. Emitted once
        // here so modules no longer need to duplicate this rule.
        $html = '<style>.section-nav { height: initial; min-height: 42px; }</style>';

        $html .= $view->sectionNav($sectionNav, $trigger);
        $first = true;
        foreach ($rendered as $id => $content) {
            $class = 'section' . ($first ? ' active' : '');
            $first = false;
            $html .= sprintf(
                '<div id="%s" class="%s">%s</div>',
                $view->escapeHtmlAttr($prefix . $id),
                $class,
                $content
            );
        }
        return $html;
    }

    /**
     * Build a stable, page-unique prefix for the section ids, derived from the
     * form name so distinct forms on the same page do not collide.
     */
    protected function idPrefix(Form $form): string
    {
        $name = (string) $form->getName();
        $name = preg_replace('/[^a-z0-9]+/i', '-', $name);
        $name = trim((string) $name, '-');
        return ($name === '' ? 'form' : $name) . '-';
    }

    /**
     * Derive the tab list from the form option "element_tabs", dispatching each
     * element and fieldset to its own "tab" option.
     *
     * Public so callers can derive the declarative tabs, then inject extra
     * non-form content into a tab via "content_before"/"content_after" before
     * passing the result back to __invoke(), for example a diagnostics tab.
     */
    public function tabsFromOption(Form $form): array
    {
        $elementTabs = $form->getOption('element_tabs') ?: [];
        if (!$elementTabs) {
            return [];
        }

        // Iterate on the form itself and not on getElements() then
        // getFieldsets(): the order of insertion must be kept, else a form
        // where a fieldset is only a title followed by elements of the first
        // level, like the one of CleanUrl, would display all its elements
        // first, then all its empty titles.
        $grouped = array_fill_keys(array_keys($elementTabs), []);
        foreach ($form as $child) {
            $tab = $child->getOption('tab');
            if ($tab && isset($grouped[$tab])) {
                $grouped[$tab][] = $child->getName();
            }
        }

        $translate = $this->getView()->plugin('translate');
        $tabs = [];
        foreach ($elementTabs as $id => $label) {
            $tabs[$id] = [
                'label' => $translate($label),
                'elements' => $grouped[$id],
            ];
        }
        return $tabs;
    }

    protected function renderCollection(Fieldset $fieldset): string
    {
        $view = $this->getView();
        $hasGroups = (bool) $fieldset->getOption('element_groups');
        if ($hasGroups) {
            // The nested renderer keeps the fieldsets that are a real group of
            // values, unlike the core one that flattens them.
            $helpers = $view->getHelperPluginManager();
            foreach (['formCollectionElementGroupsNested', 'formCollectionElementGroups'] as $helper) {
                if ($helpers->has($helper)) {
                    return $view->plugin($helper)->render($fieldset);
                }
            }
        }

        // The content of a tab is not wrapped in a fieldset, since the tab is
        // already a section. But formCollection() applies its "wrap" argument
        // to the whole descendance, so a nested fieldset would lose its own
        // "<fieldset><legend>" too, and the sections of a form built with
        // fieldsets would all disappear. So render each child directly.
        $markup = '';
        foreach ($fieldset as $child) {
            // A fieldset may declare its own helper via the option
            // "render_helper", for example a collection with its own editor.
            $helper = $child->getOption('render_helper');
            if ($helper && $view->getHelperPluginManager()->has($helper)) {
                $markup .= (string) $view->plugin($helper)->__invoke($child);
                continue;
            }
            $markup .= $child instanceof FieldsetInterface
                ? $view->formCollection($child)
                : $view->formRow($child);
        }
        return $markup;
    }
}

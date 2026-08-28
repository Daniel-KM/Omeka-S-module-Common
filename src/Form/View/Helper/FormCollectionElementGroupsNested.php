<?php declare(strict_types=1);

namespace Common\Form\View\Helper;

use Laminas\Form\ElementInterface;
use Laminas\Form\FieldsetInterface;
use Omeka\Form\View\Helper\FormCollectionElementGroups;

/**
 * Render the element groups of a form, keeping the nested fieldsets.
 *
 * The core helper walks into the fieldsets and renders every element with
 * formRow(), so a fieldset that is a real group of values, for example a
 * collection of rules or a set of options stored as one setting, loses its own
 * "<fieldset><legend>" and its elements are scattered in the groups. Modules
 * had to remove such fieldsets from the form before rendering.
 *
 * Here only the children of the first level are dispatched in the groups: an
 * element is rendered with formRow() and a fieldset with formCollection(), that
 * keeps its own section and its inner order.
 *
 * @see \Omeka\Form\View\Helper\FormCollectionElementGroups
 */
class FormCollectionElementGroupsNested extends FormCollectionElementGroups
{
    public function render(ElementInterface $element): string
    {
        $elementGroups = $element->getOption('element_groups');
        if (!$elementGroups || !is_array($elementGroups)) {
            return parent::render($element);
        }

        $view = $this->getView();

        // Dispatch the children of the first level only, keeping their order.
        $inGroups = [];
        $notInGroups = [];
        foreach ($element->getIterator() as $child) {
            if (!$child instanceof ElementInterface && !$child instanceof FieldsetInterface) {
                continue;
            }
            $group = $child->getOption('element_group');
            if ($group && isset($elementGroups[$group])) {
                $inGroups[$group][] = $child;
            } else {
                $notInGroups[] = $child;
            }
        }

        $renderChild = fn ($child): string => $this->renderChild($child);

        $markup = '';
        foreach ($notInGroups as $child) {
            $markup .= $renderChild($child);
        }

        foreach ($elementGroups as $groupName => $groupLabel) {
            if (!isset($inGroups[$groupName])) {
                continue;
            }
            $markup .= sprintf('<fieldset id="%s">', $view->escapeHtml($groupName));
            $markup .= sprintf('<legend><h2 class="fieldsets-heading">%s</h2></legend>', $view->escapeHtml($view->translate($groupLabel)));
            foreach ($inGroups[$groupName] as $child) {
                $markup .= $renderChild($child);
            }
            $markup .= '</fieldset>';
        }

        return $markup;
    }

    /**
     * Render an element or a fieldset, with its own helper when it declares one.
     *
     * A fieldset that needs a specific markup, for example a collection of
     * rules with its own editor, may set the option "render_helper" with the
     * name of a view helper, that receives it and returns its html. Else the
     * standard helpers are used.
     */
    protected function renderChild(ElementInterface $child): string
    {
        $view = $this->getView();

        $helper = $child->getOption('render_helper');
        if ($helper && $view->getHelperPluginManager()->has($helper)) {
            return (string) $view->plugin($helper)->__invoke($child);
        }

        return $child instanceof FieldsetInterface
            ? $view->formCollection($child)
            : $view->formRow($child);
    }
}

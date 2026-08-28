<?php declare(strict_types=1);

namespace Common\Form\View\Helper;

use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\FormTextarea;

/**
 * Render a ArrayQueriesTextarea element and enqueue its assets.
 *
 * The element is rendered as a standard textarea, but its editor assets (the
 * query sidebar and the toggle script) are enqueued here, so the element is
 * self-contained and the assets no longer need a manual call in the template.
 *
 * @see \Common\Form\Element\ArrayQueriesTextarea
 * @see \Common\View\Helper\ArrayQueriesTextareaAssets
 */
class FormArrayQueriesTextarea extends FormTextarea
{
    public function render(ElementInterface $element): string
    {
        $this->getView()->arrayQueriesTextareaAssets();
        return parent::render($element);
    }
}

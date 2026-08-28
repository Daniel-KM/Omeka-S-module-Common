<?php declare(strict_types=1);

namespace Common\Form\View\Helper;

use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\FormTextarea;

/**
 * Render a FieldsTextarea element and enqueue its assets.
 *
 * The element is rendered as standard textarea, but assets should be enqueued.
 */
class FormFieldsTextarea extends FormTextarea
{
    public function render(ElementInterface $element): string
    {
        $this->getView()->fieldsTextareaAssets();
        return parent::render($element);
    }
}

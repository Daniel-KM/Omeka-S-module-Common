<?php declare(strict_types=1);

namespace Common\Form\View\Helper;

use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\FormCollection as LaminasFormCollection;

/**
 * Override FormCollection to render "info" option on fieldset as paragraph.
 */
class FormCollection extends LaminasFormCollection
{
    public function render(ElementInterface $element): string
    {
        $markup = parent::render($element);

        // Inject info text after <legend> if present.
        $info = $element->getOption('info');
        if ($info && $this->shouldWrap()) {
            $info = $this->getTranslator()->translate($info);
            $escape = $this->getView()->plugin('escapeHtml');
            $infoHtml = '<p class="field-comment">'
                . $escape($info) . '</p>';
            // Insert after the closing </legend> tag.
            $markup = preg_replace(
                '#(</legend>)#',
                '$1' . $infoHtml,
                $markup,
                1
            );
        }

        return $markup;
    }
}

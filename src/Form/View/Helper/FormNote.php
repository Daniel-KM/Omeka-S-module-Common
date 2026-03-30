<?php declare(strict_types=1);

namespace Common\Form\View\Helper;

use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\AbstractHelper;

/**
 * Render a Note element as a simple paragraph, without any input value.
 */
class FormNote extends AbstractHelper
{
    public function render(ElementInterface $element): string
    {
        $text = $element->getOption('text') ?? '';
        if ($text === '') {
            return '';
        }
        $escape = $element->getOption('disable_html_escape') !== true;
        if ($escape) {
            $text = $this->getView()->escapeHtml($text);
        }
        return '<p class="note">' . $text . '</p>';
    }

    public function __invoke(?ElementInterface $element = null): string
    {
        return $element === null
            ? ''
            : $this->render($element);
    }
}

<?php declare(strict_types=1);

namespace Common\Form\View\Helper;

use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\AbstractHelper;

/**
 * Render a Note element as a static text block, without any input value.
 */
class FormNote extends AbstractHelper
{
    /**
     * @var bool
     */
    protected $summaryStyleAppended = false;

    public function render(ElementInterface $element): string
    {
        $text = $element->getOption('text') ?? '';
        if ($text === '') {
            return '';
        }
        $view = $this->getView();
        $text = $view->translate($text);
        $escape = $element->getOption('disable_html_escape') !== true;
        if ($escape) {
            $text = $view->escapeHtml($text);
        }

        // Use div instead of p to manage raw and html notes.
        $output = $escape
            ? '<p class="note">' . $text . '</p>'
            : '<div class="note">' . $text . '</div>';

        // Restore the summary display over admin theme normalize.css.
        if (!$this->summaryStyleAppended && stripos($text, '<summary') !== false) {
            $this->summaryStyleAppended = true;
            $output = '<style>.note summary { display: list-item; cursor: pointer; }</style>' . $output;
        }

        return $output;
    }

    public function __invoke(?ElementInterface $element = null): string
    {
        return $element === null
            ? ''
            : $this->render($element);
    }
}

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

        // Keep the attributes (id, class, data…), used by forms and scripts.
        $attributes = array_diff_key($element->getAttributes(), ['type' => null, 'name' => null]);
        $attributes['class'] = trim('note ' . ($attributes['class'] ?? ''));

        // Use div instead of p to manage raw and html notes.
        $tag = $escape ? 'p' : 'div';
        $output = sprintf('<%1$s %2$s>%3$s</%1$s>', $tag, $this->createAttributesString($attributes), $text);

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

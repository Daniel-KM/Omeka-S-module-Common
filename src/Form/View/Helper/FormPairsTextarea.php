<?php declare(strict_types=1);

namespace Common\Form\View\Helper;

use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\FormTextarea;
use Laminas\Form\FormElementManager;

/**
 * Render a textarea and enqueue the assets of the editor of pairs if enabled.
 */
class FormPairsTextarea extends FormTextarea
{
    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    public function __construct(?FormElementManager $formElementManager = null)
    {
        $this->formElementManager = $formElementManager;
    }

    public function render(ElementInterface $element): string
    {
        $class = ' ' . (string) $element->getAttribute('class') . ' ';
        if (strpos($class, ' common-pairs-textarea ') === false) {
            return parent::render($element);
        }

        $view = $this->getView();
        $view->pairsTextareaAssets();

        // The labels of the columns are data attributes, so they are not
        // translated by the standard rendering of the element. Do not modify
        // the element itself: it may be rendered more than once.
        $translate = $view->plugin('translate');
        $element = clone $element;
        foreach (['data-pairs-key-label', 'data-pairs-value-label'] as $attribute) {
            $label = (string) $element->getAttribute($attribute);
            if ($label !== '') {
                $element->setAttribute($attribute, $translate($label));
            }
        }

        // The templates are rendered before the textarea, so the attributes
        // that point to them are set when the textarea is rendered.
        $template = $this->renderCellTemplate($element, 'key')
            . $this->renderCellTemplate($element, 'value');

        // The element of Omeka enqueues its own assets, but the name and the
        // thumbnail of an asset already saved are fetched by the editor.
        if (strpos($template, 'asset-form-element') !== false) {
            $this->appendValueOption($element, 'apiUrl', $view->url('api/default', ['resource' => 'assets']));
        }

        return parent::render($element) . $template;
    }

    /**
     * Render the element of a cell once, as a template.
     *
     * The javascript clones it for each row, so any element of Omeka is
     * usable, with its own rendering and its own script, that is delegated on
     * "#content" for the asset and for the query. The names and the ids are
     * removed from the clones: the values are only stored in the textarea.
     *
     * @param string $cell "key" or "value".
     */
    protected function renderCellTemplate(ElementInterface $element, string $cell): string
    {
        $pairsEditor = $element->getOption('pairs_editor');
        $spec = is_array($pairsEditor) ? ($pairsEditor[$cell . '_element'] ?? null) : null;
        if (!$spec || !is_array($spec) || empty($spec['type']) || !$this->formElementManager) {
            return '';
        }

        static $index = 0;
        $templateId = 'common-pairs-' . $cell . '-tpl-' . ++$index;
        $element->setAttribute('data-pairs-' . $cell . '-template', $templateId);

        $cellElement = $this->formElementManager->get($spec['type'], $spec['options'] ?? []);
        $cellElement->setName($spec['name'] ?? 'common-pairs-cell');
        if (!empty($spec['options'])) {
            $cellElement->setOptions($spec['options']);
        }
        if (!empty($spec['attributes'])) {
            $cellElement->setAttributes($spec['attributes']);
        }

        return sprintf(
            '<template id="%s">%s</template>',
            $this->getView()->escapeHtmlAttr($templateId),
            $this->getView()->formElement($cellElement)
        );
    }

    /**
     * Add a default option for the widget of the value.
     */
    protected function appendValueOption(ElementInterface $element, string $name, $value): void
    {
        $valueOptions = json_decode((string) $element->getAttribute('data-pairs-value-options'), true) ?: [];
        $valueOptions += [$name => $value];
        $element->setAttribute('data-pairs-value-options', json_encode($valueOptions, 320));
    }
}

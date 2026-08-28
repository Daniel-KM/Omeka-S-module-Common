<?php declare(strict_types=1);

namespace Common\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Enqueue the assets of the FieldsTextarea element.
 */
class FieldsTextareaAssets extends AbstractHelper
{
    protected $appended = false;

    public function __invoke(): void
    {
        if ($this->appended) {
            return;
        }
        $this->appended = true;

        $view = $this->getView();
        $translate = $view->plugin('translate');

        $data = [
            'types' => [
                'checkbox',
                'date',
                'datetime',
                'email',
                'hidden',
                'multicheckbox',
                'number',
                'password',
                'radio',
                'select',
                'tel',
                'text',
                'textarea',
                'time',
                'url',
            ],
            'labels' => [
                'editAsForm' => $translate('Edit as a form'), // @translate
                'editAsText' => $translate('Edit as text'), // @translate
                'preview' => $translate('Preview'), // @translate
                'hidePreview' => $translate('Hide preview'), // @translate
                'submit' => $translate('Submit'), // @translate
                'addField' => $translate('Add a field'), // @translate
                'colName' => $translate('name'), // @translate
                'colLabel' => $translate('label'), // @translate
                'colRequired' => $translate('required'), // @translate
                'valuesTitle' => $translate('Values'), // @translate
                'optionsTitle' => $translate('Options'), // @translate
                'attributesTitle' => $translate('Attributes'), // @translate
                'addValue' => $translate('Add a value'), // @translate
                'addOption' => $translate('Add an option'), // @translate
                'addAttribute' => $translate('Add an attribute'), // @translate
                'colKey' => $translate('key'), // @translate
                'colValue' => $translate('value'), // @translate
                'deleteField' => $translate('Delete this field'), // @translate
                'remove' => $translate('Remove'), // @translate
                'drag' => $translate('Move'), // @translate
                'moveUp' => $translate('Move up'), // @translate
                'moveDown' => $translate('Move down'), // @translate
                'invalidYaml' => $translate('The YAML cannot be parsed. Fix it before switching to the form.'), // @translate
            ],
        ];

        $assetUrl = $view->plugin('assetUrl');
        $view->headLink()
            ->appendStylesheet($assetUrl('css/common-fields-textarea.css', 'Common'));
        $view->headScript()
            ->appendScript('window.CommonFieldsTextarea = ' . json_encode($data, 320) . ';')
            ->appendFile($assetUrl('vendor/js-yaml/js-yaml.min.js', 'Common'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('js/common-fields-textarea.js', 'Common'), 'text/javascript', ['defer' => 'defer']);
    }
}

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

        $fieldsTextarea = new \Common\Form\Element\FieldsTextarea();
        $dynamics = $fieldsTextarea->getTypesWithDynamicValues();

        $data = [
            'dynamics' => $dynamics,
            'sources' => (object) $fieldsTextarea->getTypesWithSource(),
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
                ...$dynamics,
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
                'rawSyntax' => $translate('This syntax cannot be edited as a form. Convert it to the simple syntax first.'), // @translate
                'notFields' => $translate('The text is not a list of fields: each field should start a line with its name followed by ":".'), // @translate
                'invalidNames' => $translate('These names of field contain forbidden characters: only the letters without accent, the digits, "_" and "-" are allowed.'), // @translate
            ],
        ];

        // The groups of pairs use the editor of pairs.
        $view->pairsTextareaAssets();

        // The messages are displayed with the dialog of the module.
        $assetUrl = $view->plugin('assetUrl');
        $isAdmin = $view->status()->isAdminRequest();
        $view->headLink()
            ->appendStylesheet($assetUrl($isAdmin ? 'css/common-dialog-admin.css' : 'css/common-dialog.css', 'Common'))
            ->appendStylesheet($assetUrl('css/common-fields-textarea.css', 'Common'));
        $view->headScript()
            ->appendScript('window.CommonFieldsTextarea = ' . json_encode($data, 320) . ';')
            ->appendFile($assetUrl('js/common-dialog.js', 'Common'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('vendor/js-yaml/js-yaml.min.js', 'Common'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('js/common-fields-textarea.js', 'Common'), 'text/javascript', ['defer' => 'defer']);
    }
}

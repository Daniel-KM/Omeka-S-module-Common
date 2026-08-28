<?php declare(strict_types=1);

namespace Common\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * Enqueue assets for ArrayQueriesTextarea element once per request.

 * @see \Common\Form\Element\ArrayQueriesTextarea
 * @see \Common\Form\View\Helper\FormArrayQueriesTextarea
 */
class ArrayQueriesTextareaAssets extends AbstractHelper
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
        $url = $view->plugin('url');
        $assetUrl = $view->plugin('assetUrl');

        $data = [
            'urls' => [
                'sidebarEdit' => $url('admin/default', ['controller' => 'query', 'action' => 'sidebar-edit']),
                'searchFilters' => $url('admin/default', ['controller' => 'query', 'action' => 'search-filters']),
                'sidebarPreview' => $url('admin/default', ['controller' => 'query', 'action' => 'sidebar-preview']),
            ],
            'labels' => [
                'editAsQuerier' => $translate('Edit with the query builder'), // @translate
                'editAsText' => $translate('Edit as text'), // @translate
                'edit' => $translate('Edit'), // @translate
                'editAdvanced' => $translate('Advanced edit'), // @translate
                'apply' => $translate('Apply'), // @translate
                'cancel' => $translate('Cancel'), // @translate
                'addQuery' => $translate('Add a query'), // @translate
                'key' => $translate('key'), // @translate
                'remove' => $translate('Remove'), // @translate
            ],
        ];

        // Core assets required by the query sidebar and editor of this element.
        $view->headLink()
            ->prependStylesheet($assetUrl('css/advanced-search.css', 'Omeka'))
            ->appendStylesheet($assetUrl('css/common-array-queries-textarea.css', 'Common'));
        $view->headScript()
            ->appendFile($assetUrl('js/advanced-search.js', 'Omeka'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('js/query-form.js', 'Omeka'), 'text/javascript', ['defer' => 'defer'])
            ->appendScript('window.CommonArrayQueriesTextarea = ' . json_encode($data, 320) . ';')
            ->appendFile($assetUrl('js/common-array-queries-textarea.js', 'Common'), 'text/javascript', ['defer' => 'defer']);
    }
}

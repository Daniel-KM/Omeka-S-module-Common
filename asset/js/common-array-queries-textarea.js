'use strict';

/**
 * Editor for form element ArrayQueriesTextarea.
 *
 * Add a toggle between the raw text mode (one query by line) and a query
 * builder mode, where each query is edited through the core query sidebar
 * (advanced search).
 *
 * @see \Common\Form\Element\ArrayQueriesTextarea
 * @see application/asset/js/query-form.js
 */
(function () {
    var cfg = window.CommonArrayQueriesTextarea || {};
    var urls = cfg.urls || {};

    function t(key, fallback) {
        return (cfg.labels && cfg.labels[key]) || fallback;
    }

    function build($, textarea) {
        var $textarea = $(textarea);
        if ($textarea.data('aqtReady')) {
            return;
        }
        $textarea.data('aqtReady', true);

        var asKeyValue = textarea.dataset.asKeyValue === '1';
        var separator = (textarea.dataset.keyValueSeparator || '=').trim() || '=';
        var resourceType = textarea.dataset.queryResourceType || 'items';

        var $toggle = $('<button type="button" class="button common-array-queries-textarea-toggle">' + t('editAsQuerier', 'Edit with the query builder') + '</button>');
        var $editor = $('<div class="aqt-editor" style="display:none;"></div>');
        var $rows = $('<div class="aqt-rows"></div>');
        var $add = $('<button type="button" class="button aqt-add o-icon-add" style="display:none;">' + t('addQuery', 'Add a query') + '</button>');
        var $footer = $('<div class="aqt-footer"></div>').append($toggle).append($add);
        $editor.append($rows);
        $textarea.after($editor);
        $editor.after($footer);

        // Fetch the human readable filters of a query, like the core sidebar
        // does after each edition, so the querier mode shows them immediately.
        function loadFilters($qfe, query) {
            if (!urls.searchFilters || !query) {
                return;
            }
            $.get(urls.searchFilters + '?' + query, function (data) {
                $qfe.find('.query-form-search-filters').html(data);
            });
        }

        function makeRow(key, query) {
            query = (query || '').trim();
            var $row = $('<div class="aqt-row"></div>');
            var $qfe = $('<div class="query-form-element aqt-qfe"></div>')
                .attr('data-resource-type', resourceType)
                .attr('data-query', '')
                .attr('data-partial-excludelist', '[]')
                .attr('data-preview-append-query', '[]')
                .attr('data-search-filters-url', urls.searchFilters || '')
                .attr('data-sidebar-edit-url', urls.sidebarEdit || '')
                .attr('data-sidebar-preview-url', urls.sidebarPreview || '');
            if (asKeyValue) {
                $qfe.append($('<input type="text" class="aqt-key">')
                    .attr('placeholder', t('key', 'key'))
                    .val(key || ''));
            }
            $qfe.append('<div class="query-display"><div class="query-form-search-filters"></div><input type="hidden" class="query-form-query"></div>');
            $qfe.find('.query-form-query').val(query);
            $qfe.append('<button type="button" class="query-form-edit button">' + t('edit', 'Edit') + '</button>');
            $qfe.append('<button type="button" class="query-form-advanced-edit-show button">' + t('editAdvanced', 'Advanced edit') + '</button>');
            $qfe.append('<button type="button" class="query-form-advanced-edit-apply button inactive">' + t('apply', 'Apply') + '</button>');
            $qfe.append('<button type="button" class="query-form-advanced-edit-cancel button red inactive">' + t('cancel', 'Cancel') + '</button>');
            $row.append($qfe);
            $row.append('<button type="button" class="button red aqt-remove o-icon-delete" title="' + t('remove', 'Remove') + '"></button>');
            loadFilters($qfe, query);
            return $row;
        }

        function parseToRows() {
            $rows.empty();
            (textarea.value || '').split(/\r\n|\r|\n/).forEach(function (line) {
                line = line.trim();
                if (!line) {
                    return;
                }
                var key = '';
                var query = line;
                if (asKeyValue) {
                    var index = line.indexOf(separator);
                    if (index === -1) {
                        key = line;
                        query = '';
                    } else {
                        key = line.slice(0, index).trim();
                        query = line.slice(index + separator.length).trim();
                    }
                }
                $rows.append(makeRow(key, query));
            });
            if (!$rows.children().length) {
                $rows.append(makeRow('', ''));
            }
        }

        function serialize() {
            var lines = [];
            $rows.find('.aqt-row').each(function () {
                var $row = $(this);
                var query = ($row.find('.query-form-query').val() || '').trim().replace(/^\?+/, '');
                if (asKeyValue) {
                    var key = ($row.find('.aqt-key').val() || '').trim();
                    if (!key && !query) {
                        return;
                    }
                    lines.push(query.length ? (key + ' ' + separator + ' ' + query) : key);
                } else {
                    if (!query) {
                        return;
                    }
                    lines.push(query);
                }
            });
            textarea.value = lines.join('\n');
        }

        $toggle.on('click', function () {
            if ($editor.is(':visible')) {
                serialize();
                $editor.hide();
                $add.hide();
                $textarea.show();
                $toggle.text(t('editAsQuerier', 'Edit with the query builder'));
            } else {
                parseToRows();
                $textarea.hide();
                $editor.show();
                $add.show();
                $toggle.text(t('editAsText', 'Edit as text'));
            }
        });

        $add.on('click', function () {
            $rows.append(makeRow('', ''));
        });
        $rows.on('click', '.aqt-remove', function () {
            $(this).closest('.aqt-row').remove();
            serialize();
        });
        // The core query sidebar writes the query back to ".query-form-query"
        // and triggers "input"; also react to the key edition.
        $editor.on('input change', '.query-form-query, .aqt-key', function () {
            serialize();
        });
        // The advanced edit "apply" button does not trigger "input", so
        // resynchronize after any query button ran its core handler.
        $editor.on('click', '.query-form-element button', function () {
            setTimeout(serialize, 0);
        });
    }

    function init() {
        var $ = window.jQuery;
        if (!$) {
            return;
        }
        Array.prototype.forEach.call(
            document.querySelectorAll('textarea.common-array-queries-textarea'),
            function (textarea) {
                build($, textarea);
            }
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

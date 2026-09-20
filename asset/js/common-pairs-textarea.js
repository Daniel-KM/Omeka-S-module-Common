/**
 * Editor of pairs "key = value".
 *
 * Two layers:
 * - CommonPairsEditor.create(): a reusable list of rows (key, value), sortable,
 *   with a picker of known keys, usable by any script (see FieldsTextarea);
 * - the binding to the textareas flagged by
 * Common\Form\Element\TraitPairsEditor:
 *   the lines of the textarea are parsed into rows and serialized back on each
 *   change, so the textarea remains the posted value.
 */

(function () {
    'use strict';

    const config = window.CommonPairsTextarea || {};
    const labels = config.labels || {};
    const t = function (key, fallback) {
        return labels[key] || fallback;
    };

    const escapeHtml = function (s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    };

    // ---- Widgets of the value column --------------------------------------

    /**
     * A widget builds the cell of the value of a row. It receives an object
     * {value, options, changed} and returns an element that contains, or is,
     * a field with the class "common-pairs-value": its value is the one
     * written in the textarea.
     */
    const valueWidgets = {};

    /**
     * Display the name and the thumbnail of an asset already selected.
     */
    const fillAsset = function (cell, apiUrl) {
        const hidden = cell.querySelector('.common-pairs-value');
        const selected = cell.querySelector('.selected-asset');
        const name = cell.querySelector('.selected-asset-name');
        const image = cell.querySelector('.selected-asset-image');
        const id = (hidden.value || '').trim();
        // The class "empty" belongs to the element of Omeka: its own css hides
        // "[No asset selected]" and the button to clear it accordingly.
        (cell.querySelector('.asset-form-element') || cell).classList.toggle('empty', !id);
        selected.style.display = id ? '' : 'none';
        if (!id) {
            name.textContent = '';
            image.removeAttribute('src');
            return;
        }
        // Without the api, the id is the only thing to display.
        name.textContent = '#' + id;
        if (!apiUrl) return;
        fetch(apiUrl.replace(/\/$/, '') + '/' + encodeURIComponent(id), {headers: {Accept: 'application/json'}})
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (asset) {
                if (!asset || hidden.value.trim() !== id) return;
                name.textContent = asset['o:name'] || ('#' + id);
                if (asset['o:asset_url']) image.src = asset['o:asset_url'];
            })
            .catch(function () {});
    };

    /**
     * Build the cell of the value from the template rendered by php.
     *
     * The names and the ids are removed: the cells are never posted, the value
     * is only stored in the textarea, and duplicated ids would break the
     * labels and the scripts of the page.
     */
    const cloneCellTemplate = function (templateId, value, label, apiUrl, cellClass) {
        const template = document.getElementById(templateId);
        if (!template || !template.content) return null;

        const cell = document.createElement('span');
        cell.className = 'common-pairs-cell-' + cellClass + ' common-pairs-element';
        cell.appendChild(template.content.cloneNode(true));

        cell.querySelectorAll('[id]').forEach(function (node) { node.removeAttribute('id'); });
        cell.querySelectorAll('[for]').forEach(function (node) { node.removeAttribute('for'); });

        const fields = cell.querySelectorAll('input, select, textarea');
        fields.forEach(function (field) { field.removeAttribute('name'); });

        // The field that holds the value: the hidden one when the element has
        // its own interface (asset, query), else the first real field.
        const field = cell.querySelector('input[type=hidden]')
            || cell.querySelector('select, textarea, input');
        if (field) {
            field.classList.add('common-pairs-' + cellClass);
            field.value = value || '';
            if (label) field.setAttribute('aria-label', label);
        }

        // An asset already saved: the template is rendered without value, so
        // its name and its thumbnail are fetched here.
        if (value && cell.querySelector('.asset-form-element')) {
            fillAsset(cell, apiUrl);
        }

        // Chosen is initialized once on load by Omeka, so a select added later
        // must be initialized here.
        if (window.jQuery && window.jQuery.fn.chosen) {
            setTimeout(function () {
                cell.querySelectorAll('select.chosen-select').forEach(function (select) {
                    window.jQuery(select).chosen({width: '100%'});
                });
            }, 0);
        }

        return cell;
    };

    /**
     * A closed list of values, from the options or from another select of the
     * page. A value that is not in the list any more remains selected.
     */
    valueWidgets.select = function (context) {
        const valueOptions = context.options.valueOptions || {};
        const select = document.createElement('select');
        select.className = 'common-pairs-value common-pairs-cell-value';
        select.setAttribute('aria-label', context.options.valueLabel || '');

        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = '';
        select.appendChild(empty);

        const values = new Map();
        const list = valueOptions.options || {};
        if (Array.isArray(list)) {
            list.forEach(function (pair) {
                if (Array.isArray(pair)) values.set(String(pair[0]), pair[1]);
                else values.set(String(pair), pair);
            });
        } else {
            Object.keys(list).forEach(function (key) { values.set(String(key), list[key]); });
        }
        if (valueOptions.source) {
            const source = document.querySelector(valueOptions.source);
            if (source) {
                Array.prototype.forEach.call(source.options, function (option) {
                    if (option.value !== '') values.set(String(option.value), option.textContent);
                });
            }
        }
        values.forEach(function (label, value) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label && label !== value ? label + ' (' + value + ')' : value;
            select.appendChild(option);
        });
        if (context.value && !values.has(String(context.value))) {
            const option = document.createElement('option');
            option.value = context.value;
            option.textContent = context.value;
            select.appendChild(option);
        }
        select.value = context.value || '';
        return select;
    };
    // ---- Core editor -----------------------------------------------------

    const defaultEditorOptions = {
        keyLabel: '',
        valueLabel: '',
        // "text" or "number"; "none" hides the value column (simple list).
        // Any other name is a widget registered in "valueWidgets", for example
        // "asset", that selects the value in a sidebar instead of typing it.
        valueType: 'text',
        // Options given to the widget of the value, as a plain object.
        valueOptions: {},
        // Id of a <template> rendered by php: its content is cloned as the
        // cell of each row, so any element of Omeka is usable.
        keyTemplate: '',
        valueTemplate: '',
        sortable: true,
        // Known keys: {key: default value or label}.
        keys: {},
        // A css selector of a select whose options complete the known keys.
        keySource: '',
        keySelect: false,
        keySkip: [],
        // A regex: only the matching keys of the source are proposed.
        keyPattern: '',
        // Fill the value with the default of the key when picked.
        keyFill: true,
        // The user can type any key.
        freeKeys: true,
        keyReadonly: false,
        // A string forbidden in the keys (the separator of the textarea).
        keyForbidden: '',
        // Show the header with the labels of the columns.
        header: true,
        // Show the picker and the "+" button.
        actions: true,
        // Callback on any change.
        onChange: null,
    };

    // The known keys: the static list, completed by the options of the source
    // select at call time (its options may be built by the page).
    const knownKeys = function (options) {
        // A Map keeps the order of the keys, unlike an object, that lists the
        // integer ones first, so a key like "default" would move to the end.
        const keys = new Map();
        if (Array.isArray(options.keys)) {
            options.keys.forEach(function (pair) { keys.set(String(pair[0]), pair[1]); });
        } else {
            Object.keys(options.keys || {}).forEach(function (k) { keys.set(String(k), options.keys[k]); });
        }
        if (options.keySource) {
            const source = document.querySelector(options.keySource);
            if (source) {
                const pattern = options.keyPattern ? new RegExp(options.keyPattern) : null;
                Array.from(source.querySelectorAll('option')).forEach(function (opt) {
                    if (opt.value === '' || opt.disabled) return;
                    if (options.keySkip.indexOf(opt.value) !== -1) return;
                    if (pattern && !pattern.test(opt.value)) return;
                    if (!keys.has(opt.value)) keys.set(opt.value, opt.textContent.trim());
                });
            }
        }
        return keys;
    };

    /**
     * Create an editor of pairs in a mount element.
     *
     * @return {object} {element, list, getRows, setRows, addRow, count}
     */
    let editorIndex = 0;

    const editors = [];

    // An element of Omeka (asset, query) fills its hidden input from its own
    // script, with jQuery val(), that emits no event. Rather than knowing each
    // of them, the rows are compared after a click anywhere in the page.
    document.addEventListener('click', function () {
        setTimeout(function () {
            editors.forEach(function (editor) { editor.syncIfChanged(); });
        }, 0);
    });

    const createEditor = function (mount, userOptions, initialRows) {
        const options = Object.assign({}, defaultEditorOptions, userOptions || {});
        editorIndex++;
        options.keyLabel = options.keyLabel || t('key', 'key');
        options.valueLabel = options.valueLabel || t('value', 'value');
        const isList = options.valueType === 'none';

        const element = document.createElement('div');
        element.className = 'common-pairs';
        mount.appendChild(element);

        const table = document.createElement('div');
        table.className = 'common-pairs-rows';
        if (options.header) {
            table.innerHTML = '<div class="common-pairs-head">'
                + (options.sortable ? '<span class="common-pairs-cell-handle"></span>' : '')
                + (options.sortable ? '<span class="common-pairs-cell-moves"></span>' : '')
                + '<span class="common-pairs-cell-key">' + escapeHtml(options.keyLabel) + '</span>'
                + (isList ? '' : '<span class="common-pairs-cell-value">' + escapeHtml(options.valueLabel) + '</span>')
                + '<span class="common-pairs-cell-remove"></span>'
                + '</div>';
        }
        const list = document.createElement('div');
        list.className = 'common-pairs-list';
        table.appendChild(list);
        element.appendChild(table);

        const actions = document.createElement('div');
        actions.className = 'common-pairs-actions';
        element.appendChild(actions);

        // The known keys are proposed inside the key of each row, and not
        // only in the picker below the list: the user adds a row first, so a
        // picker apart is easily missed. A datalist keeps the input free, so
        // a new key can still be typed.
        let datalist = null;
        const datalistId = 'common-pairs-keys-' + editorIndex;

        let picker = null;
        const countKeys = Array.isArray(options.keys) ? options.keys.length : Object.keys(options.keys || {}).length;
        // With a select by row, the picker of keys would be a second way to do
        // the same thing, so only the button to add a row remains.
        const hasKeys = !options.keySelect && (!!options.keySource || countKeys > 0);
        if (options.actions && hasKeys) {
            picker = document.createElement('select');
            picker.className = 'common-pairs-picker';
            picker.setAttribute('aria-label', t('pick', 'Add…'));
            actions.appendChild(picker);
        }
        if (hasKeys && options.freeKeys) {
            datalist = document.createElement('datalist');
            datalist.id = datalistId;
            element.appendChild(datalist);
        }

        let addButton = null;
        if (options.actions && (options.freeKeys || options.keySelect)) {
            addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'common-pairs-add o-icon-add button';
            addButton.title = t('add', 'Add');
            addButton.setAttribute('aria-label', t('add', 'Add'));
            actions.appendChild(addButton);
        }
        if (!picker && !addButton) actions.hidden = true;

        const getRows = function () {
            return Array.from(list.children).map(function (row) {
                const keyField = row.querySelector('.common-pairs-key');
                const valueField = isList ? null : row.querySelector('.common-pairs-value');
                return {
                    key: keyField ? keyField.value : '',
                    value: valueField ? valueField.value : '',
                };
            });
        };

        const refreshDatalist = function () {
            if (!datalist) return;
            datalist.innerHTML = '';
            knownKeys(options).forEach(function (label, key) {
                const opt = document.createElement('option');
                opt.value = key;
                if (label && label !== key) opt.label = label;
                datalist.appendChild(opt);
            });
        };

        const refreshPicker = function () {
            if (!picker) return;
            const keys = knownKeys(options);
            const used = getRows().map(function (r) { return r.key.trim(); });
            picker.innerHTML = '';
            const first = document.createElement('option');
            first.value = '';
            first.textContent = t('pick', 'Add…');
            picker.appendChild(first);
            keys.forEach(function (label, key) {
                if (used.indexOf(key) !== -1) return;
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = label && label !== key ? label + ' (' + key + ')' : key;
                picker.appendChild(opt);
            });
            picker.disabled = picker.options.length <= 1;
            // A long list of keys deserves the searchable select.
            if (window.jQuery && window.jQuery.fn.chosen) {
                const $picker = window.jQuery(picker);
                if (!picker.dataset.chosenReady && picker.options.length > 10) {
                    picker.dataset.chosenReady = '1';
                    picker.classList.add('chosen-select');
                    $picker.chosen({
                        allow_single_deselect: true,
                        disable_search_threshold: 10,
                        width: '100%',
                        placeholder_text_single: t('pick', 'Add…'),
                    });
                }
                if (picker.dataset.chosenReady) $picker.trigger('chosen:updated');
            }
        };

        const refreshMoves = function () {
            if (!options.sortable) return;
            const rows = list.querySelectorAll('.common-pairs-row');
            rows.forEach(function (row, i) {
                const up = row.querySelector('.common-pairs-up');
                const down = row.querySelector('.common-pairs-down');
                if (up) up.disabled = i === 0;
                if (down) down.disabled = i === rows.length - 1;
            });
        };

        /**
         * Move a row up (-1) or down (1). Return false when it cannot move.
         */
        const moveRow = function (row, delta) {
            const sibling = delta < 0 ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling) return false;
            if (delta < 0) {
                list.insertBefore(row, sibling);
            } else {
                list.insertBefore(sibling, row);
            }
            changed();
            return true;
        };

        let lastRows = '';

        const changed = function () {
            lastRows = JSON.stringify(getRows());
            refreshDatalist();
            refreshPicker();
            refreshMoves();
            if (options.onChange) options.onChange(getRows());
        };

        const makeRow = function (pair) {
            const row = document.createElement('div');
            row.className = 'common-pairs-row';
            if (options.sortable) {
                row.innerHTML = '<button type="button" class="common-pairs-cell-handle sortable-handle"'
                    + ' title="' + escapeHtml(t('drag', 'Drag to reorder')) + '"'
                    + ' aria-label="' + escapeHtml(t('move', 'Move: use the up and down arrows to reorder')) + '"'
                    // The bare arrows are the announced command; the variant
                    // with Alt is the one of the collections of AdvancedSearch,
                    // and it works here too, so a learned habit never fails.
                    + ' aria-keyshortcuts="ArrowUp ArrowDown Alt+ArrowUp Alt+ArrowDown"></button>'
                    // A drag is not usable by everyone, so the rows also move
                    // with two buttons, like the collections of fieldsets.
                    + '<span class="common-pairs-cell-moves">'
                    + '<button type="button" class="common-pairs-up o-icon- fas fa-caret-up"'
                    + ' aria-label="' + escapeHtml(t('up', 'Move up')) + '"'
                    + ' title="' + escapeHtml(t('up', 'Move up')) + '"></button>'
                    + '<button type="button" class="common-pairs-down o-icon- fas fa-caret-down"'
                    + ' aria-label="' + escapeHtml(t('down', 'Move down')) + '"'
                    + ' title="' + escapeHtml(t('down', 'Move down')) + '"></button>'
                    + '</span>';
            }
            // The key is a select when the keys are a closed list, so the
            // user picks it instead of typing an id or a slug.
            let key;
            const keyCell = options.keyTemplate
                ? cloneCellTemplate(options.keyTemplate, pair.key, options.keyLabel, '', 'key')
                : null;
            if (keyCell) {
                row.appendChild(keyCell);
                key = keyCell.querySelector('.common-pairs-key');
            } else if (options.keySelect) {
                key = document.createElement('select');
                key.className = 'common-pairs-key common-pairs-cell-key';
                const keys = knownKeys(options);
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = '';
                empty.hidden = true;
                key.appendChild(empty);
                keys.forEach(function (label, k) {
                    const opt = document.createElement('option');
                    opt.value = k;
                    opt.textContent = label && label !== k ? label + ' (' + k + ')' : k;
                    key.appendChild(opt);
                });
                // A key that is no more in the list remains editable as text.
                if (pair.key && !keys.has(String(pair.key))) {
                    const opt = document.createElement('option');
                    opt.value = pair.key;
                    opt.textContent = pair.key;
                    key.appendChild(opt);
                }
                key.value = pair.key || '';
                // A long list of keys deserves the searchable select.
                if (window.jQuery && window.jQuery.fn.chosen && key.options.length > 10) {
                    setTimeout(function () {
                        key.classList.add('chosen-select');
                        window.jQuery(key).chosen({
                            disable_search_threshold: 10,
                            width: '100%',
                            placeholder_text_single: t('choose', 'Choose…'),
                        }).on('change', function () {
                            // Synchronize directly: dispatching a "change"
                            // here would be caught by chosen again, endlessly.
                            changed();
                        });
                    }, 0);
                }
            } else {
                key = document.createElement('input');
                key.type = 'text';
                key.value = pair.key || '';
                if (datalist) {
                    key.setAttribute('list', datalistId);
                    key.setAttribute('autocomplete', 'off');
                }
            }
            if (!keyCell) {
                key.className = 'common-pairs-key common-pairs-cell-key';
                key.setAttribute('aria-label', options.keyLabel);
                row.appendChild(key);
            }
            if (key && options.keyReadonly) {
                if (key.tagName === 'SELECT') {
                    key.disabled = true;
                } else {
                    key.readOnly = true;
                }
            }
            let cell = null;
            if (!isList && options.valueTemplate) {
                cell = cloneCellTemplate(options.valueTemplate, pair.value, options.valueLabel,
                    (options.valueOptions || {}).apiUrl || '', 'value');
            }
            if (cell) {
                row.appendChild(cell);
            } else if (!isList && valueWidgets[options.valueType]) {
                row.appendChild(valueWidgets[options.valueType]({
                    value: pair.value || '',
                    options: options,
                    changed: changed,
                }));
            } else if (!isList) {
                const value = document.createElement('input');
                value.type = options.valueType === 'number' ? 'number' : 'text';
                if (options.valueType === 'number') value.step = 'any';
                value.className = 'common-pairs-value common-pairs-cell-value';
                value.value = pair.value || '';
                value.setAttribute('aria-label', options.valueLabel);
                const keys = knownKeys(options);
                const keyLabel = keys.get(String(pair.key));
                if (options.keyFill && keyLabel && keyLabel !== pair.key) value.placeholder = keyLabel;
                row.appendChild(value);
            }
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'common-pairs-remove common-pairs-cell-remove o-icon-delete';
            remove.title = t('remove', 'Remove');
            remove.setAttribute('aria-label', t('remove', 'Remove'));
            row.appendChild(remove);
            return row;
        };

        const addRow = function (pair, focus) {
            pair = pair || {key: '', value: ''};
            const row = makeRow(pair);
            list.appendChild(row);
            if (focus) {
                const input = row.querySelector(pair.key ? '.common-pairs-value' : '.common-pairs-key') || row.querySelector('input');
                if (input) input.focus();
            }
            changed();
            return row;
        };

        const setRows = function (rows) {
            list.innerHTML = '';
            (rows || []).forEach(function (pair) { list.appendChild(makeRow(pair)); });
            refreshDatalist();
            refreshPicker();
            refreshMoves();
        };

        if (addButton) {
            addButton.addEventListener('click', function () {
                addRow(null, true);
            });
        }
        if (picker) {
            const onPick = function () {
                const key = picker.value;
                if (!key) return;
                const keys = knownKeys(options);
                const keyLabel = keys.get(String(key));
                addRow({key: key, value: options.keyFill && !isList && keyLabel !== key ? keyLabel : ''}, false);
                picker.value = '';
            };
            picker.addEventListener('change', onPick);
            // Chosen triggers a jQuery event, not a native one.
            if (window.jQuery) window.jQuery(picker).on('change', onPick);
        }
        // The separator of the textarea cannot be part of a key (it is split
        // at its first occurrence), but a value may contain it.
        const checkKey = function (input) {
            if (!options.keyForbidden) return;
            input.setCustomValidity(input.value.indexOf(options.keyForbidden) === -1
                ? ''
                : t('keyForbidden', 'The key cannot contain "{separator}".').replace('{separator}', options.keyForbidden));
            input.reportValidity();
        };
        list.addEventListener('input', function (e) {
            if (e.target.classList.contains('common-pairs-key')) checkKey(e.target);
            changed();
        });
        // A select of keys or of values is edited with "change", and chosen
        // sends it too.
        list.addEventListener('change', function (e) {
            if (e.target.classList.contains('common-pairs-key')) {
                checkKey(e.target);
            } else if (!e.target.classList.contains('common-pairs-value')) {
                return;
            }
            changed();
        });
        list.addEventListener('click', function (e) {
            const remove = e.target.closest('.common-pairs-remove');
            if (!remove) return;
            const row = remove.closest('.common-pairs-row');
            // Keep the focus in the editor: the next row, else the previous
            // one, else the button to add a row.
            const next = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            const target = next
                ? next.querySelector('.common-pairs-remove')
                : (addButton || null);
            if (target) target.focus();
            changed();
        });

        // The rows are reordered with the arrows from the handle, with or
        // without Alt. The default is always prevented, so the page never
        // scrolls from the handle, not even at the ends of the list.
        list.addEventListener('keydown', function (e) {
            const handle = e.target.closest('.common-pairs-cell-handle');
            if (!handle || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
            e.preventDefault();
            if (moveRow(handle.closest('.common-pairs-row'), e.key === 'ArrowUp' ? -1 : 1)) {
                handle.focus();
            }
        });

        // The buttons do the same as the drag, for the pointer and the touch.
        list.addEventListener('click', function (e) {
            const button = e.target.closest('.common-pairs-up, .common-pairs-down');
            if (!button) return;
            const up = button.classList.contains('common-pairs-up');
            const row = button.closest('.common-pairs-row');
            if (!moveRow(row, up ? -1 : 1)) return;
            // The button of an end becomes disabled, so move the focus to the
            // other one rather than losing it on the body.
            const target = button.disabled
                ? row.querySelector(up ? '.common-pairs-down' : '.common-pairs-up')
                : button;
            if (target) target.focus();
        });
        list.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || !e.target.matches('input')) return;
            e.preventDefault();
            addRow(null, true);
        });

        // Reorder by drag and drop of the handle. The events are stopped: the
        // editor may be nested in another sortable list.
        if (options.sortable) {
            let dragged = null;
            list.addEventListener('mousedown', function (e) {
                const row = e.target.closest('.common-pairs-row');
                if (row) row.draggable = !!e.target.closest('.common-pairs-cell-handle');
            });
            list.addEventListener('dragstart', function (e) {
                const row = e.target.closest('.common-pairs-row');
                if (!row || !row.draggable) {
                    e.preventDefault();
                    return;
                }
                e.stopPropagation();
                dragged = row;
                row.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', ''); } catch (err) {}
            });
            list.addEventListener('dragover', function (e) {
                if (!dragged) return;
                e.preventDefault();
                e.stopPropagation();
                const over = e.target.closest('.common-pairs-row');
                if (!over || over === dragged) return;
                const rect = over.getBoundingClientRect();
                const before = e.clientY < rect.top + rect.height / 2;
                list.insertBefore(dragged, before ? over : over.nextSibling);
            });
            list.addEventListener('dragend', function (e) {
                if (!dragged) return;
                e.stopPropagation();
                dragged.classList.remove('dragging');
                dragged.draggable = false;
                dragged = null;
                changed();
            });
        }

        setRows(initialRows || []);

        const syncIfChanged = function () {
            if (!element.isConnected) return;
            const current = JSON.stringify(getRows());
            if (current === lastRows) return;
            lastRows = current;
            changed();
        };

        const editor = {
            element: element,
            list: list,
            getRows: getRows,
            setRows: setRows,
            addRow: addRow,
            count: function () { return list.children.length; },
            syncIfChanged: syncIfChanged,
            // Rebuild the rows, for example when a widget of a module was
            // registered after the editor was built (deferred script).
            rebuild: function () { setRows(getRows()); },
        };
        editors.push(editor);
        return editor;
    };

    // ---- Formats of the textarea -----------------------------------------

    const unquote = function (v) {
        v = v.trim();
        if (v.length >= 2 && ((v[0] === '"' && v[v.length - 1] === '"') || (v[0] === "'" && v[v.length - 1] === "'"))) {
            return v.substring(1, v.length - 1);
        }
        return v;
    };

    const isRawIniValue = function (v) {
        return /^-?\d+(\.\d+)?$/.test(v) || /^(true|false|null|on|off|yes|no)$/i.test(v);
    };

    // Parse the text into rows, or return null when a line cannot be displayed
    // as a row (ini sections, comments, invalid lines).
    const parse = function (text, options) {
        const rows = [];
        const lines = text.split(/\r?\n/);
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            if (!line.trim()) continue;
            if (options.format === 'list') {
                rows.push({key: line.trim(), value: ''});
                continue;
            }
            if (options.format === 'ini' && /^\s*[;#\[]/.test(line)) return null;
            const pos = line.indexOf(options.separator);
            if (pos === -1) {
                if (options.format === 'ini') return null;
                rows.push({key: line.trim(), value: ''});
                continue;
            }
            const key = line.substring(0, pos).trim();
            let value = line.substring(pos + options.separator.length).trim();
            if (options.format === 'ini') value = unquote(value);
            rows.push({key: key, value: value});
        }
        return rows;
    };

    const serialize = function (rows, options) {
        const lines = [];
        rows.forEach(function (row) {
            const key = row.key.trim();
            let value = row.value.trim();
            if (options.format === 'list') {
                if (key !== '') lines.push(key);
                return;
            }
            if (key === '' && value === '') return;
            if (options.format === 'ini') {
                if (!isRawIniValue(value)) value = '"' + value + '"';
                lines.push(key + ' ' + options.separator + ' ' + value);
                return;
            }
            lines.push(value === '' ? key : key + ' ' + options.separator + ' ' + value);
        });
        return lines.length ? lines.join('\n') + '\n' : '';
    };

    // ---- Binding to a textarea -------------------------------------------

    const readOptions = function (textarea) {
        const d = textarea.dataset;
        let keys = {};
        if (d.pairsKeys) {
            try { keys = JSON.parse(d.pairsKeys) || {}; } catch (e) { keys = {}; }
        }
        let valueOptions = {};
        if (d.pairsValueOptions) {
            try { valueOptions = JSON.parse(d.pairsValueOptions) || {}; } catch (e) { valueOptions = {}; }
        }
        let skip = [];
        if (d.pairsKeySkip) {
            try { skip = JSON.parse(d.pairsKeySkip) || []; } catch (e) { skip = []; }
        }
        const format = d.pairsFormat || 'lines';
        return {
            format: format,
            separator: d.pairsSeparator || '=',
            defaultDisplay: d.pairsDefaultDisplay === 'text' ? 'text' : 'form',
            labelAsNote: d.pairsLabelAsNote === '1',
            editor: {
                keyLabel: d.pairsKeyLabel || '',
                valueLabel: d.pairsValueLabel || '',
                valueType: format === 'list' ? 'none' : (d.pairsValueType || 'text'),
                valueOptions: valueOptions,
                keyTemplate: d.pairsKeyTemplate || '',
                valueTemplate: d.pairsValueTemplate || '',
                sortable: d.pairsSortable !== '0',
                keyFill: d.pairsKeyFill === '1',
                freeKeys: d.pairsFreeKeys !== '0',
                keyReadonly: d.pairsKeyReadonly === '1',
                keyForbidden: format === 'list' ? '' : (d.pairsSeparator || '='),
                keys: keys,
                keySource: d.pairsKeySource || '',
                keySelect: d.pairsKeySelect === '1',
                keySkip: skip,
                keyPattern: d.pairsKeyPattern || '',
            },
        };
    };

    const bindTextarea = function (textarea) {
        if (textarea.dataset.pairsReady) return;
        textarea.dataset.pairsReady = '1';
        const options = readOptions(textarea);

        const wrapper = document.createElement('div');
        wrapper.className = 'common-pairs-wrapper';
        // The label of the field points to the textarea, that is hidden in the
        // form mode, so the group is named by the same label.
        wrapper.setAttribute('role', 'group');
        const labels = textarea.labels && textarea.labels.length ? textarea.labels : null;
        if (labels) {
            const ids = [];
            Array.prototype.forEach.call(labels, function (label, index) {
                if (!label.id) label.id = (textarea.id || 'common-pairs') + '-label-' + index;
                ids.push(label.id);
            });
            wrapper.setAttribute('aria-labelledby', ids.join(' '));
        }
        textarea.parentNode.insertBefore(wrapper, textarea);

        let syncing = false;
        const editor = createEditor(wrapper, Object.assign({
            onChange: function (rows) {
                syncing = true;
                textarea.value = serialize(rows, options);
                syncing = false;
            },
        }, options.editor));

        // The textarea goes between the rows and the actions, where the
        // toggle form/text is added on the right.
        const table = editor.element.querySelector('.common-pairs-rows');
        const actions = editor.element.querySelector('.common-pairs-actions');
        editor.element.insertBefore(textarea, actions);
        actions.hidden = false;
        const notice = document.createElement('span');
        notice.className = 'common-pairs-notice';
        notice.hidden = true;
        notice.textContent = t('unparsable', 'The text cannot be edited as a list: fix it or edit it as text.');
        actions.appendChild(notice);
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'common-pairs-toggle button';
        actions.appendChild(toggle);
        const formOnly = Array.from(actions.querySelectorAll('.common-pairs-picker, .common-pairs-add'));

        // A label that describes the syntax of the text only makes sense in the
        // text mode, and its column wastes the width of a form with a single
        // field. So hide the column and repeat the text as a note above the
        // textarea. The label stays in the dom: it still names the group, and
        // a reference of aria-labelledby may point to a hidden element.
        const textOnly = [];
        if (options.labelAsNote && labels) {
            const note = document.createElement('p');
            note.className = 'common-pairs-note';
            note.textContent = labels[0].textContent.trim();
            textarea.parentNode.insertBefore(note, textarea);
            textOnly.push(note);
            Array.prototype.forEach.call(labels, function (label) {
                const meta = label.closest('.field-meta');
                (meta || label).hidden = true;
                // The core gives a fixed width to the inputs, so the field is
                // marked to take back the width left by the hidden column.
                const field = label.closest('.field');
                if (field) field.classList.add('common-pairs-field-note');
            });
        }

        const build = function () {
            const rows = parse(textarea.value, options);
            if (rows === null) return false;
            editor.setRows(rows);
            return true;
        };

        let formMode = false;
        const setMode = function (form) {
            if (form && !build()) {
                notice.hidden = false;
                form = false;
            } else {
                notice.hidden = true;
            }
            formMode = form;
            table.hidden = !form;
            formOnly.forEach(function (el) { el.hidden = !form; });
            textOnly.forEach(function (el) { el.hidden = form; });
            textarea.hidden = form;
            toggle.textContent = form ? t('editAsText', 'Edit as text') : t('editAsForm', 'Edit as a form');
        };

        toggle.addEventListener('click', function () {
            setMode(!formMode);
        });
        // The textarea may be filled by another script: rebuild the rows.
        textarea.addEventListener('input', function () {
            if (formMode && !syncing) build();
        });

        setMode(options.defaultDisplay === 'form');
    };

    const initAll = function (root) {
        (root || document).querySelectorAll('textarea.common-pairs-textarea').forEach(bindTextarea);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initAll(); });
    } else {
        initAll();
    }

    // Textareas added later (collections, sidebars).
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            m.addedNodes.forEach(function (n) {
                if (n.nodeType !== 1) return;
                if (n.matches && n.matches('textarea.common-pairs-textarea')) bindTextarea(n);
                else initAll(n);
            });
        });
    });
    observer.observe(document.documentElement, {childList: true, subtree: true});

    window.CommonPairsEditor = {
        create: createEditor,
        valueWidgets: valueWidgets,
        // A module that registers a widget after the editors were built calls
        // it, so its rows use the widget without depending on the order of
        // the scripts.
        rebuildAll: function () {
            editors.forEach(function (editor) {
                if (editor.element.isConnected) editor.rebuild();
            });
        },
        bind: bindTextarea,
        init: initAll,
    };
})();

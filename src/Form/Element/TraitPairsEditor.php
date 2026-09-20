<?php declare(strict_types=1);

namespace Common\Form\Element;

/**
 * Enable the editor of pairs "key = value" on a textarea element.
 *
 * The option "pairs_editor" (true or array) is converted into data attributes
 * read by the javascript "common-pairs-textarea.js", that displays the lines of
 * the textarea as a list of rows with a key and a value, sortable, with an
 * optional list of known keys and default values. The textarea remains the
 * posted value.
 *
 * Options of "pairs_editor":
 * - format (string): "lines" (key = value by line, default), "ini" (values
 *   quoted, typed), or "list" (one value by line, no key column).
 * - separator (string): the separator between key and value (default "=").
 * - key_label, value_label (string): the headers of the columns.
 * - value_type (string): "text" (default), "number", or the name of a widget
 *   registered in "CommonPairsEditor.valueWidgets", that builds the cell of
 *   the value instead of a plain input. Common provides "select" (a closed
 *   list) and "asset" (selected with the sidebar of Omeka). A module may
 *   register its own widget for an item set, a query, etc.
 * - key_element, value_element (array): a laminas element specification
 *   ("type", "options", "attributes") rendered once as the template of the
 *   cell, then cloned for each row. It is the way to use a real element of
 *   Omeka, with its own rendering and its own script: ItemSetSelect, Query,
 *   Asset, OptionalNumber, etc. "value_element" takes precedence over
 *   "value_type", and "key_element" over "key_select".
 * - value_options (array): the options given to the widget of the value. For
 *   "select", "options" is a map "value => label" and "source" a css selector
 *   of another select. For "asset", the keys "sidebarUrl" and "apiUrl" are
 *   filled automatically by the view helper when they are not set.
 * - sortable (bool): the rows can be reordered (default true).
 * - keys (array): the known keys with their default value or label, used to
 *   fill a picker "Add…" and, when "key_fill" is set, the value of the row.
 * - key_source (string): a css selector of a select whose options are the
 *   known keys (value) and default values (text).
 * - key_skip (array): keys of the source to skip.
 * - key_fill (bool): fill the value with the default of the key (default true
 *   when keys or key_source is set).
 * - free_keys (bool): the user can type any key (default true).
 * - key_readonly (bool): the key of a row cannot be edited (default false).
 * - key_select (bool): the key is a select filled with the known keys, instead
 *   of a text input with a picker (default false). A stored key that is no
 *   more in the list is kept as an option of its own row.
 * - default_display (string): "form" (default) or "text".
 * - label_as_note (bool): the label of the element describes the syntax of the
 *   text, so remove its column, that wastes the width of a form with a single
 *   field, and display it as a note above the textarea in the text mode only
 *   (default false).
 */
trait TraitPairsEditor
{
    protected function setPairsEditor($options): self
    {
        if (!$options) {
            return $this;
        }
        $options = is_array($options) ? $options : [];

        $class = trim((string) $this->getAttribute('class'));
        if (strpos(" $class ", ' common-pairs-textarea ') === false) {
            $this->setAttribute('class', trim($class . ' common-pairs-textarea'));
        }

        $format = $options['format'] ?? $this->pairsEditorDefaultFormat();
        $keys = $options['keys'] ?? null;
        $keySource = $options['key_source'] ?? null;
        $keyFill = $options['key_fill'] ?? ($keys || $keySource);

        $attributes = [
            'data-pairs-format' => $format,
            'data-pairs-separator' => $options['separator'] ?? $this->pairsEditorDefaultSeparator(),
            'data-pairs-key-label' => $options['key_label'] ?? '',
            'data-pairs-value-label' => $options['value_label'] ?? '',
            'data-pairs-value-type' => $options['value_type'] ?? 'text',
            'data-pairs-sortable' => empty($options['sortable']) && array_key_exists('sortable', $options) ? '0' : '1',
            'data-pairs-key-fill' => $keyFill ? '1' : '0',
            'data-pairs-free-keys' => empty($options['free_keys']) && array_key_exists('free_keys', $options) ? '0' : '1',
            'data-pairs-key-readonly' => empty($options['key_readonly']) ? '0' : '1',
            'data-pairs-key-select' => empty($options['key_select']) ? '0' : '1',
            'data-pairs-default-display' => ($options['default_display'] ?? 'form') === 'text' ? 'text' : 'form',
            'data-pairs-label-as-note' => empty($options['label_as_note']) ? '0' : '1',
        ];
        if ($keys) {
            // Encode as a list of pairs: a json object would list the integer
            // keys first, so a key like "default" would move to the end.
            $pairs = [];
            foreach ($keys as $key => $label) {
                $pairs[] = [(string) $key, $label];
            }
            $attributes['data-pairs-keys'] = json_encode($pairs, 320);
        }
        if ($keySource) {
            $attributes['data-pairs-key-source'] = $keySource;
        }
        if (!empty($options['key_skip'])) {
            $attributes['data-pairs-key-skip'] = json_encode(array_values($options['key_skip']), 320);
        }
        if (!empty($options['key_pattern'])) {
            $attributes['data-pairs-key-pattern'] = $options['key_pattern'];
        }
        if (!empty($options['value_options'])) {
            $attributes['data-pairs-value-options'] = json_encode($options['value_options'], 320);
        }
        // The elements are rendered by the view helper, that owns the
        // renderer, and that sets the attributes pointing to the templates.
        if (!empty($options['value_element'])) {
            $attributes['data-pairs-value-type'] = 'element';
        }
        foreach ($attributes as $name => $value) {
            $this->setAttribute($name, $value);
        }
        return $this;
    }

    protected function pairsEditorDefaultFormat(): string
    {
        if ($this instanceof IniTextarea) {
            return 'ini';
        }
        return method_exists($this, 'getAsKeyValue') && !$this->getAsKeyValue()
            ? 'list'
            : 'lines';
    }

    protected function pairsEditorDefaultSeparator(): string
    {
        // The core getter requires a useless argument.
        return method_exists($this, 'getKeyValueSeparator')
            ? (string) $this->getKeyValueSeparator('=')
            : '=';
    }
}

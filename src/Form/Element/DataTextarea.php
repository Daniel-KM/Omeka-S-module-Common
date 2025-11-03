<?php declare(strict_types=1);

namespace Common\Form\Element;

use Omeka\Form\Element\ArrayTextarea;

class DataTextarea extends ArrayTextarea
{
    /**
     * @var array
     */
    protected $dataKeys = [];

    /**
     * @var array
     */
    protected $dataArrayKeys = [];

    /**
     * @var array
     */
    protected $dataAssociativeKeys = [];

    /**
     * @var array
     */
    protected $dataOptions = [];

    /**
     * @var string
     *
     * Name of the key to return directly (flatten) when asKeyValue is true.
     * If set, top-level array will map first field as data[dataFlatKey].
     */
    protected $dataFlatKey = '';

    /**
     * @var array
     *
     * Keys whose sub-values must be cast to integer when parsed.
     */
    protected $dataIntegerKeys = [];

    /**
     * @var string
     *
     * May be "by_line" (one line by data, default) or "last_is_list".
     */
    protected $dataTextMode = '';

    /**
     * @param array $options
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if (array_key_exists('data_keys', $this->options)) {
            $this->setDataKeys($this->options['data_keys']);
        }
        if (array_key_exists('data_array_keys', $this->options)) {
            $this->setDataArrayKeys($this->options['data_array_keys']);
        }
        if (array_key_exists('data_options', $this->options)) {
            $this->setDataOptions($this->options['data_options']);
        }
        if (array_key_exists('data_text_mode', $this->options)) {
            $this->setDataTextMode($this->options['data_text_mode']);
        }
        if (array_key_exists('data_flat_key', $this->options)) {
            $this->setDataFlatKey($this->options['data_flat_key']);
        }
        // For backward-compatibility: allow explicit associative keys.
        if (array_key_exists('data_associative_keys', $this->options)
            && is_array($this->options['data_associative_keys'])
        ) {
            $this->dataAssociativeKeys = $this->options['data_associative_keys'];
        }

        return $this;
    }

    public function arrayToString($array): string
    {
        if (is_string($array)) {
            return $array;
        } elseif ($array === null) {
            return '';
        }

        // Rebuild original rows if data was flattened.
        $array = $this->unflatDataKey((array) $array);

        $textMode = $this->getDataTextMode();
        if ($textMode === 'last_is_list') {
            return $this->arrayToStringLastIsList($array);
        }
        return $this->arrayToStringByLine($array);
    }

    public function stringToArray($string): array
    {
        if (is_array($string)) {
            return $string;
        } elseif ($string === null) {
            return [];
        }
        $textMode = $this->getDataTextMode();
        if ($textMode === 'last_is_list') {
            return $this->stringToArrayLastIsList((string) $string);
        }
        return $this->stringToArrayByLine((string) $string);
    }

    /**
     * Set the ordered list of keys to use for each line.
     *
     * This option allows to get an associative array instead of a simple list
     * for each row.
     *
     * Each specified key will be used as the keys of each part of each line.
     * There is no default keys: in that case, the values are a simple array of
     * array.
     * With option "as_key_value", the first value will be the used as key for
     * the main array too.
     *
     * @example When passing options to an element:
     * ```php
     *     'data_keys' => [
     *         'field',
     *         'label',
     *         'type',
     *         'options',
     *     ],
     * ```
     *
     * @deprecated Use setDataOptions() instead.
     */
    public function setDataKeys(array $dataKeys)
    {
        $this->dataKeys = array_fill_keys($dataKeys, null);
        return $this;
    }

    /**
     * Get the list of data keys.
     *
     * The data keys are the name of each value of a row in order to get an
     * associative array instead of a simple list.
     *
     * @deprecated Use getDataOptions() instead.
     */
    public function getDataKeys(): array
    {
        return array_keys($this->dataKeys);
    }

    /**
     * Set the option to separate values into multiple values.
     *
     * This option allows to create an array for specific values of the row.
     * Each value of the row can have its own separator.
     *
     * The keys should be a subset of the data keys, so they must be defined.
     *
     * It is not recommended to set the first key when option "as_key_value" is
     *  set. In that case, the whole value is used as key before to be splitted.
     *
     *  This option as no effect for last key when option "last_is_list" is set.
     *
     * @example When passing options to an element:
     * ```php
     *     'data_array_keys' => [
     *         'options' => '|',
     *     ],
     * ```
     *
     * @deprecated Use setDataOptions() instead.
     */
    public function setDataArrayKeys(array $dataArrayKeys)
    {
        $this->dataArrayKeys = $dataArrayKeys;
        return $this;
    }

    /**
     * Get the option to separate values into multiple values.
     *
     * @deprecated Use getDataOptions() instead.
     */
    public function getDataArrayKeys(): array
    {
        return $this->dataArrayKeys;
    }

    /**
     * Set the ordered list of keys to use for each line and their options.
     *
     * This option allows to get an associative array instead of a simple list
     * for each row and to specify options for each of them.
     * Managed sub-options are:
     * - separator (string): allow to explode the string to create a sub-array
     * - associative (string): allow to create an associative sub-array. This
     *   option as no effect for last key when option "last_is_list" is set.
     *   When the value is a string, it's the separator used to get sub-array.
     * - is_integer (bool): cast each value of the sub-array as integer.
     *
     * Each specified key will be used as the keys of each part of each line.
     * There is no default keys: in that case, the values are a simple array of
     * array.
     * With option "as_key_value", the first value will be the used as key for
     * the main array too.
     *
     * @example When passing options to an element:
     * ```php
     *     'data_options' => [
     *         'field' => null,
     *         'label' => null,
     *         'type' => null,
     *         'options' => [
     *             'separator' => '|',
     *             'associative' => '=',
     *             'is_integer' => true,
     *         ],
     *     ],
     * ```
     * @todo Allow data_options for sub-options to get associative sub-array automatically.
     */
    public function setDataOptions(array $dataOptions)
    {
        $this->dataOptions = $dataOptions;
        // TODO For compatibility as long as code is not updated to use dataOptions.
        // Backward-compatible to fill deprecated structures.
        $this->dataKeys = array_fill_keys(array_keys($dataOptions), null);

        $arrayKeys = [];
        $associativeKeys = [];
        $integerKeys = [];
        foreach (array_filter($dataOptions) as $key => $value) {
            if (is_array($value)) {
                if (isset($value['separator'])) {
                    $arrayKeys[$key] = (string) $value['separator'];
                }
                if (isset($value['associative'])) {
                    $associativeKeys[$key] = (string) $value['associative'];
                }
                if (!empty($value['is_integer'])) {
                    $integerKeys[$key] = true;
                }
            } elseif (is_scalar($value)) {
                $arrayKeys[$key] = $value;
            }
        }

        $this->dataArrayKeys = $arrayKeys;
        $this->dataAssociativeKeys = $associativeKeys;
        $this->dataIntegerKeys = $integerKeys;
        return $this;
    }

    public function getDataOptions(): array
    {
        return $this->dataOptions;
    }

    /**
     * Set the mode to display the text inside the textarea input.
     *
     * - "by_line" (default: all the data are on the same line):
     * ```
     * x = y = z = a | b | c
     * ```
     *
     * - "last_is_list" (the last field is exploded and an empty line is added),
     *   allowing to create groups:
     * ```
     * x = y = z
     * a
     * b
     * c
     *
     * ```
     */
    public function setDataTextMode(?string $dataTextMode)
    {
        $this->dataTextMode = (string) $dataTextMode;
        return $this;
    }

    public function setDataFlatKey(?string $dataFlatKey)
    {
        $this->dataFlatKey = (string) $dataFlatKey;
        return $this;
    }

    /**
     * Get the text mode of the data.
     */
    public function getDataTextMode(): string
    {
        return $this->dataTextMode;
    }

    protected function arrayToStringByLine(array $array): string
    {
        // Reorder values according to specified keys and fill empty values.
        $string = '';
        $countDataKeys = count($this->dataKeys);
        // Associative array.
        if ($countDataKeys) {
            $arrayKeys = array_intersect_key($this->dataArrayKeys, $this->dataKeys);
            foreach ($array as $values) {
                if (!is_array($values)) {
                    $values = (array) $values;
                }
                $data = array_replace($this->dataKeys, $values);
                // Manage sub-values.
                foreach ($arrayKeys as $arrayKey => $arraySeparator) {
                    $separator = ' ' . $arraySeparator . ' ';
                    $list = array_map('strval', isset($data[$arrayKey]) ? (array) $data[$arrayKey] : []);
                    if (isset($this->dataAssociativeKeys[$arrayKey]) && !$this->arrayIsList($list)) {
                        $subSeparator = ' ' . $this->dataAssociativeKeys[$arrayKey] . ' ';
                        $kvList = [];
                        foreach ($list as $k => $v) {
                            $kvList[] = $k . $subSeparator . $v;
                        }
                        $data[$arrayKey] = implode($separator, $kvList);
                    } else {
                        $data[$arrayKey] = implode($separator, $list);
                    }
                }
                $string .= implode(' ' . $this->keyValueSeparator . ' ', array_map('strval', $data)) . "\n";
            }
        }
        // Simple list.
        else {
            foreach ($array as $values) {
                if (!is_array($values)) {
                    $values = (array) $values;
                }
                $data = array_values($values);
                $string .= implode(' ' . $this->keyValueSeparator . ' ', array_map('strval', $data)) . "\n";
            }
        }
        $string = rtrim($string, "\n");
        return strlen($string) ? $string . "\n" : '';
    }

    protected function arrayToStringLastIsList(array $array): string
    {
        // Reorder values according to specified keys and fill empty values.
        $string = '';
        $countDataKeys = count($this->dataKeys);
        // Associative array.
        if ($countDataKeys) {
            // Without last key, the result is the same than by line.
            $lastKey = key(array_slice($this->dataKeys, -1));
            $arrayKeys = array_intersect_key($this->dataArrayKeys, $this->dataKeys);
            if (!isset($arrayKeys[$lastKey])) {
                return $this->arrayToStringByLine($array);
            }
            foreach ($array as $values) {
                if (!is_array($values)) {
                    $values = (array) $values;
                }
                $data = array_replace($this->dataKeys, $values);
                // Manage sub-values.
                foreach ($arrayKeys as $arrayKey => $arraySeparator) {
                    $isLastKey = $arrayKey === $lastKey;
                    $separator = $isLastKey ? "\n" : ' ' . $arraySeparator . ' ';
                    $list = array_map('strval', isset($data[$arrayKey]) ? (array) $data[$arrayKey] : []);
                    if (isset($this->dataAssociativeKeys[$arrayKey]) && !$this->arrayIsList($list)) {
                        $subSeparator = ' ' . $this->dataAssociativeKeys[$arrayKey] . ' ';
                        $kvList = [];
                        foreach ($list as $k => $v) {
                            $kvList[] = $k . $subSeparator . $v;
                        }
                        $data[$arrayKey] = implode($separator, $kvList);
                    } else {
                        $data[$arrayKey] = implode($separator, $list);
                    }
                }
                // Don't add the key value separator for the last field, and
                // append a line break to add an empty line.
                $string .= implode(' ' . $this->keyValueSeparator . ' ', array_map('strval', array_slice($data, 0, -1))) . "\n"
                    . $data[$lastKey] . "\n\n";
            }
        }
        // Simple list.
        else {
            foreach ($array as $values) {
                if (!is_array($values)) {
                    $values = (array) $values;
                }
                $data = array_values($values);
                $string .= implode("\n", array_map('strval', $data)) . "\n\n";
            }
        }
        $string = rtrim($string, "\n");
        return strlen($string) ? $string . "\n" : '';
    }

    protected function stringToArrayByLine(string $string): array
    {
        $array = [];
        $countDataKeys = count($this->dataKeys);
        if ($countDataKeys) {
            $arrayKeys = array_intersect_key($this->dataArrayKeys, $this->dataKeys);
            $list = $this->stringToList($string);
            foreach ($list as $values) {
                $values = array_map('trim', explode($this->keyValueSeparator, $values, $countDataKeys));
                // Add empty missing values. The number cannot be higher.
                // TODO Use substr_count() if quicker.
                $missing = $countDataKeys - count($values);
                if ($missing) {
                    $values = array_merge($values, array_fill(0, $missing, ''));
                }
                $data = array_combine(array_keys($this->dataKeys), $values);
                // Manage sub-values.
                foreach ($arrayKeys as $arrayKey => $arraySeparator) {
                    $parts = $this->splitAndClean($data[$arrayKey], $arraySeparator);
                    if ($parts && isset($this->dataAssociativeKeys[$arrayKey])) {
                        $asso = [];
                        foreach ($parts as $k => $v) {
                            if (strpos($v, $this->dataAssociativeKeys[$arrayKey]) !== false) {
                                [$kk, $vv] = array_map('trim', explode($this->dataAssociativeKeys[$arrayKey], $v, 2));
                                $asso[$kk] = $this->castToInteger($arrayKey, $vv);
                            } else {
                                $asso[$k] = $this->castToInteger($arrayKey, $v);
                            }
                        }
                        $data[$arrayKey] = $asso;
                    } else {
                        $data[$arrayKey] = $this->castToIntegerArray($arrayKey, $parts);
                    }
                }
                $array = $this->appendToArray($array, $data);
            }
        } else {
            $list = $this->stringToList($string);
            foreach ($list as $values) {
                // No keys: a simple list.
                $data = array_map('trim', explode($this->keyValueSeparator, $values));
                $array = $this->appendToArray($array, $data);
            }
        }
        return $array;
    }

    protected function stringToArrayLastIsList(string $string): array
    {
        $array = [];
        $countDataKeys = count($this->dataKeys);
        if ($countDataKeys) {
            // Without last key, the result is the same than by line.
            $lastKey = key(array_slice($this->dataKeys, -1));
            $arrayKeys = array_intersect_key($this->dataArrayKeys, $this->dataKeys);
            if (!isset($arrayKeys[$lastKey])) {
                return $this->stringToArrayByLine($string);
            }
            // Create groups from empty lines, namely a double line break.
            $groups = array_filter(array_map('trim', explode("\n\n", $this->fixEndOfLine($string))), 'strlen');
            foreach ($groups as $group) {
                // Remove empty lines inside group.
                $values = array_values(array_filter(array_map('trim', explode("\n", $group)), 'strlen'));
                if ($values === []) {
                    continue;
                }
                $firstFieldsValues = array_map('trim', explode($this->keyValueSeparator, reset($values), $countDataKeys - 1));
                $lastFieldValues = array_slice($values, 1);
                // Add empty missing values. The number cannot be higher.
                // TODO Use substr_count() if quicker.
                $missing = $countDataKeys - 1 - count($firstFieldsValues);
                if ($missing) {
                    $firstFieldsValues = array_merge($firstFieldsValues, array_fill(0, $missing, ''));
                }
                $values = $firstFieldsValues;
                // Last field is a list of lines already trimmed and cleaned above.
                $values[] = $lastFieldValues;
                $data = array_combine(array_keys($this->dataKeys), $values);
                // Manage sub-values.
                foreach ($arrayKeys as $arrayKey => $arraySeparator) {
                    $isLastKey = $arrayKey === $lastKey;
                    // The option "last is list" means the last key is a simple list, in any case.
                    if ($isLastKey) {
                        continue;
                    }
                    $parts = $this->splitAndClean($data[$arrayKey], $arraySeparator);
                    if ($parts && isset($this->dataAssociativeKeys[$arrayKey])) {
                        $asso = [];
                        foreach ($parts as $k => $v) {
                            if (strpos($v, $this->dataAssociativeKeys[$arrayKey]) !== false) {
                                [$kk, $vv] = array_map('trim', explode($this->dataAssociativeKeys[$arrayKey], $v, 2));
                                $asso[$kk] = $this->castToInteger($arrayKey, $vv);
                            } else {
                                $asso[$k] = $this->castToInteger($arrayKey, $v);
                            }
                        }
                        $data[$arrayKey] = $asso;
                    } else {
                        $data[$arrayKey] = $this->castToIntegerArray($arrayKey, $parts);
                    }
                }
                $array = $this->appendToArray($array, $data);
            }
        } else {
            // Create groups from empty lines, namely a double line break.
            $groups = array_filter(array_map('trim', explode("\n\n", $this->fixEndOfLine($string))), 'strlen');
            foreach ($groups as $group) {
                // No keys: a simple list.
                $data = array_values(array_filter(array_map('trim', explode("\n", $group)), 'strlen'));
                $array = $this->appendToArray($array, $data);
            }
        }
        return $array;
    }

    protected function arrayIsList(array $array): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($array);
        }
        return $array === []
            || array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Append data to array according to asKeyValue option.
     *
     * This method is used to avoid issue when data is a sub array and option
     * asKeyValue is t rue.
     */
    protected function appendToArray(array $array, $data): array
    {
        if ($this->asKeyValue) {
            $key = reset($data);
            if (is_scalar($key)) {
                $array[(string) $key] = strlen($this->dataFlatKey) && array_key_exists($this->dataFlatKey, $data)
                    ? $data[$this->dataFlatKey]
                    : $data;
            } else {
                // Fallback: cannot use non-scalar key.
                $array[] = $data;
            }
        } else {
            $array[] = $data;
        }
        return $array;
    }

    /**
     * For option dataFlatKey, rebuild full rows from array.
     */
    protected function unflatDataKey(array $array): array
    {
        if (!$this->asKeyValue
            || !strlen($this->dataFlatKey)
            || !$this->dataKeys
            || !array_key_exists($this->dataFlatKey, $this->dataKeys)
        ) {
            return $array;
        }

        $dataKeys = array_keys($this->dataKeys);

        $firstKey = $dataKeys[0];
        $flatKey = $this->dataFlatKey;

        // Detect if rows are already structured (contain all named keys).
        $structured = true;
        foreach ($array as $row) {
            if (!is_array($row) || array_intersect($dataKeys, array_keys((array) $row)) !== $dataKeys) {
                $structured = false;
                break;
            }
        }

        // Already a full structure.
        if ($structured) {
            return array_values($array);
        }

        $rebuilt = [];
        foreach ($array as $topKey => $flatValue) {
            $row = array_fill_keys($dataKeys, '');
            $row[$firstKey] = $topKey;

            // Normalize flattened value: keep arrays, wrap scalars, preserve
            // empty.
            if (is_array($flatValue)) {
                $row[$flatKey] = $flatValue;
            } elseif ($flatValue === '') {
                $row[$flatKey] = [];
            } else {
                $row[$flatKey] = [$flatValue];
            }

            $rebuilt[] = $row;
        }

        return $rebuilt;
    }

    /**
     * Split and trim string with a separator and remove empty strings ("").
     */
    protected function splitAndClean(string $value, string $separator): array
    {
        if ($value === '') {
            return [];
        }
        $parts = array_map('trim', explode($separator, $value));
        return array_values(array_filter($parts, 'strlen'));
    }

    protected function castToInteger(string $key, $value)
    {
        return isset($this->dataIntegerKeys[$key]) && is_numeric($value)
            ? (int) $value
            : $value;
    }

    protected function castToIntegerArray(string $key, array $values): array
    {
        return isset($this->dataIntegerKeys[$key])
            ? array_map(fn($v) => is_numeric($v) ? (int) $v : $v, $values)
            : $values;
    }
}

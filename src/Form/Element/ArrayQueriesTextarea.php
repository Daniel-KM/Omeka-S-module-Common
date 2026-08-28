<?php declare(strict_types=1);

namespace Common\Form\Element;

use Omeka\Form\Element\ArrayTextarea;

class ArrayQueriesTextarea extends ArrayTextarea
{
    /**
     * Store each query as a parsed array or a raw query string (textarea).
     *
     * @var bool
     */
    protected $asArray = true;

    /**
     * @var bool
     */
    protected $removeArgumentsPageAndSort = false;

    /**
     * @var bool
     */
    protected $removeArgumentsUseless = false;

    public function setOptions($options)
    {
        parent::setOptions($options);
        if (array_key_exists('as_array', $this->options)) {
            $this->setAsArray($this->options['as_array']);
        }
        if (array_key_exists('remove_arguments_page_and_sort', $this->options)) {
            $this->setRemoveArgumentsPageAndSort($this->options['remove_arguments_page_and_sort']);
        }
        if (array_key_exists('remove_arguments_useless', $this->options)) {
            $this->setRemoveArgumentsUseless($this->options['remove_arguments_useless']);
        }

        // The javascript editor targets the textarea by this class and reads
        // the options through data attributes; the assets are enqueued by the
        // render helper FormArrayQueriesTextarea.
        $class = trim((string) $this->getAttribute('class'));
        if (strpos(" $class ", ' common-array-queries-textarea ') === false) {
            $this->setAttribute('class', trim($class . ' common-array-queries-textarea'));
        }
        $this
            ->setAttribute('data-as-key-value', $this->asKeyValue ? '1' : '0')
            ->setAttribute('data-key-value-separator', (string) $this->keyValueSeparator)
            ->setAttribute('data-query-resource-type', (string) ($this->options['query_resource_type'] ?? 'items'));

        return $this;
    }

    public function arrayToString($array)
    {
        if (is_string($array)) {
            return $array;
        }

        if (!$array) {
            return '';
        }

        $strings = [];
        if ($this->asKeyValue) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $value = urldecode((string) http_build_query($value, '', '&', PHP_QUERY_RFC3986));
                }
                $strings[] = strlen((string) $value) ? "$key $this->keyValueSeparator $value" : $key;
            }
        } else {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $value = urldecode((string) http_build_query($value, '', '&', PHP_QUERY_RFC3986));
                }
                $strings[] = $value;
            }
        }

        return implode("\n", $strings);
    }

    public function stringToArray($string)
    {
        // Keep the raw query strings when the array storage is disabled.
        if (!$this->asArray) {
            return parent::stringToArray($string);
        }

        if (is_array($string)) {
            return $string;
        }

        $string = trim((string) $string);
        if (!strlen($string)) {
            return [];
        }

        return $this->asKeyValue
            ? $this->stringToKeyQueries($string)
            : $this->stringToListQueries($string);
    }

    protected function stringToKeyQueries($string): array
    {
        $result = [];
        foreach ($this->stringToList($string) as $keyValue) {
            if (strpos($keyValue, $this->keyValueSeparator) === false) {
                $result[trim($keyValue)] = '';
            } else {
                [$key, $value] = array_map('trim', explode($this->keyValueSeparator, $keyValue, 2));
                $query = [];
                parse_str(ltrim((string) $value, "? \t\n\r\0\x0B"), $query);
                if ($this->removeArgumentsPageAndSort) {
                    $query = $this->removeArgumentsPageAndSort($query);
                }
                if ($this->removeArgumentsUseless) {
                    $query = $this->arrayFilterRecursiveEmpty($query);
                }
                $result[$key] = $query;
            }
        }
        return $result;
    }

    protected function stringToListQueries($string): array
    {
        $strings = parent::stringToList($string);
        foreach ($strings as $key => $string) {
            $query = [];
            parse_str(ltrim((string) $string, "? \t\n\r\0\x0B"), $query);
            if ($this->removeArgumentsPageAndSort) {
                $query = $this->removeArgumentsPageAndSort($query);
            }
            if ($this->removeArgumentsUseless) {
                $query = $this->arrayFilterRecursiveEmpty($query);
            }
            $strings[$key] = $query;
        }
        return $strings;
    }

    /**
     * Remove arguments page and sort.
     */
    public function removeArgumentsPageAndSort(array $query): array
    {
        unset(
            $query['page'],
            $query['per_page'],
            $query['offset'],
            $query['limit'],
            $query['sort_by'],
            $query['sort_order'],
            $query['sort_by_default'],
            $query['sort_order_default'],
            $query['submit'],
            // Not for standard search, but common.
            $query['sort'],
            $query['order'],
            $query['order_by']
        );
        return $query;
    }

    /**
     * Clean an array recursively, removing empty values ("", null and []).
     *
     * "0" is a valid value, and the same for 0 and false.
     * It is mainly used to clean a url query.
     *
     * @param array $array The array is passed by reference.
     * @return array
     */
    public function arrayFilterRecursiveEmpty(array &$array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->arrayFilterRecursiveEmpty($value);
            }
            if (in_array($array[$key], ['', null, []], true)) {
                unset($array[$key]);
            }
        }
        return $array;
    }

    public function setAsArray($asArray): self
    {
        $this->asArray = (bool) $asArray;
        return $this;
    }

    public function getAsArray(): bool
    {
        return $this->asArray;
    }

    public function setRemoveArgumentsPageAndSort($removeArgumentsPageAndSort): self
    {
        $this->removeArgumentsPageAndSort = (bool) $removeArgumentsPageAndSort;
        return $this;
    }

    public function getRemoveArgumentsPageAndSort(): bool
    {
        return $this->removeArgumentsPageAndSort;
    }

    public function setRemoveArgumentsUseless($removeArgumentsUseless): self
    {
        $this->removeArgumentsUseless = (bool) $removeArgumentsUseless;
        return $this;
    }

    public function getRemoveArgumentsUseless(): bool
    {
        return $this->removeArgumentsUseless;
    }
}

<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Form\Element\Text;
use Laminas\InputFilter\InputProviderInterface;

class UrlQuery extends Text implements InputProviderInterface
{
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
        if (array_key_exists('remove_arguments_page_and_sort', $this->options)) {
            $this->setRemoveArgumentsPageAndSort($this->options['remove_arguments_page_and_sort']);
        }
        if (array_key_exists('remove_arguments_useless', $this->options)) {
            $this->setRemoveArgumentsUseless($this->options['remove_arguments_useless']);
        }
        return $this;
    }

    public function setValue($value)
    {
        $this->value = $this->arrayToQuery($value);
        return $this;
    }

    public function getInputSpecification(): array
    {
        return [
            'name' => $this->getName(),
            'required' => false,
            'allow_empty' => true,
            'filters' => [
                [
                    'name' => \Laminas\Filter\Callback::class,
                    'options' => [
                        'callback' => [$this, 'queryToArray'],
                    ],
                ],
            ],
        ];
    }

    public function arrayToQuery($array): string
    {
        if (is_string($array)) {
            return $array;
        }
        return empty($array)
            ? ''
            : http_build_query($array, '', '&', PHP_QUERY_RFC3986);
    }

    public function queryToArray($string): array
    {
        if (is_array($string)) {
            return $string;
        }

        if (!strlen((string) $string)) {
            return [];
        }

        $query = [];
        parse_str(ltrim((string) $string, "? \t\n\r\0\x0B"), $query);

        if ($this->removeArgumentsPageAndSort) {
            $query = $this->removeArgumentsPageAndSort($query);
        }

        if ($this->removeArgumentsUseless) {
            $query = $this->arrayFilterRecursiveEmpty($query);
        }

        return $query;
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

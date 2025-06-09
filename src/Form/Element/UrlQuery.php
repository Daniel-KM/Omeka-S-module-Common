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

    public function setOptions($options)
    {
        parent::setOptions($options);
        if (array_key_exists('remove_arguments_page_and_sort', $this->options)) {
            $this->setRemoveArgumentsPageAndSort($this->options['remove_arguments_page_and_sort']);
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
        return is_string($array)
            ? $array
            : http_build_query($array, '', '&', PHP_QUERY_RFC3986);
    }

    public function queryToArray($string): array
    {
        if (is_array($string)) {
            return $string;
        }

        if (!strlen($string)) {
            return [];
        }

        $query = [];
        parse_str(ltrim((string) $string, "? \t\n\r\0\x0B"), $query);

        if ($this->removeArgumentsPageAndSort) {
            $query = $this->removeArgumentsPageAndSort($query);
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

    public function setRemoveArgumentsPageAndSort($removeArgumentsPageAndSort): self
    {
        $this->removeArgumentsPageAndSort = (bool) $removeArgumentsPageAndSort;
        return $this;
    }

    public function getRemoveArgumentsPageAndSort(): bool
    {
        return $this->removeArgumentsPageAndSort;
    }
}

<?php declare(strict_types=1);

namespace Common\Form\Element;

use Omeka\Form\Element\ArrayTextarea;

class GroupTextarea extends ArrayTextarea
{
    public function setValue($value)
    {
        $this->value = $this->arrayToString($value);
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
                        'callback' => [$this, 'stringToArray'],
                    ],
                ],
            ],
        ];
    }

    public function arrayToString($array): string
    {
        if (is_string($array)) {
            return $array;
        }

        if (!count($array)) {
            return '';
        }

        $string = '';
        if ($this->asKeyValue) {
            foreach ($array ?? [] as $group => $values) {
                $string .= '# ' . $group . "\n";
                foreach ($values ?? [] as $key => $value) {
                    $string .= strlen($value) ? "$key $this->keyValueSeparator $value\n" : $key . "\n";
                }
                $string .= "\n";
            }
        } else {
            foreach ($array ?? [] as $group => $values) {
                $string .= '# ' . $group . "\n" . implode("\n", $values) . "\n\n";
            }
        }

        return trim($string);
    }

    public function stringToArray($string)
    {
        if (is_array($string)) {
            return $string;
        }

        if ($string === '' || is_null($string)) {
            return [];
        }

        // Clean the text area from end of lines.
        // Fixes Windows and Apple issues for copy/paste.
        $string = str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], (string) $string);
        $array = array_filter(array_map('trim', explode("\n", $string)), 'strlen');

        $groupsArray = [];
        $id = 0;
        foreach ($array as $string) {
            $cleanString = preg_replace('/\s+/', ' ', $string);
            if (mb_substr($cleanString, 0, 1) === '#') {
                ++$id;
                $groupName = trim(mb_substr($cleanString, 1));
                $groupsArray[$groupName] = [];
                continue;
            } elseif ($id === 0) {
                ++$id;
                // Set a default group name when missing.
                $groupName = sprintf('Group %d', $id); // $translate
            }
            if ($this->asKeyValue) {
                if (strpos($cleanString, $this->keyValueSeparator) === false) {
                    $key = trim($cleanString);
                    $value = '';
                } else {
                    [$key, $value] = array_map('trim', explode($this->keyValueSeparator, $cleanString, 2));
                }
                $groupsArray[$groupName][$key] = $value;
            } else {
                $groupsArray[$groupName][] = $cleanString;
            }
        }
        return $groupsArray;
    }
}

<?php declare(strict_types=1);

namespace Common\Form\Element;

trait TraitPrependValuesOptions
{
    public function getValueOptions(): array
    {
        $prependValueOptions = $this->getOption('prepend_value_options');
        $valueOptions = $this->valueOptions;
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }
        return $valueOptions;
    }
}

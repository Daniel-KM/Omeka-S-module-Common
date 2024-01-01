<?php declare(strict_types=1);

namespace Common\Form\Element;

class ItemSetSelect extends \Omeka\Form\Element\ItemSetSelect
{
    use TraitGroupByOwner;
    use TraitOptionalElement;

    public function getValueOptions(): array
    {
        return $this->getValueOptionsFix();
    }
}

<?php declare(strict_types=1);

namespace Common\Form\Element;

class ResourceTemplateSelect extends \Omeka\Form\Element\ResourceTemplateSelect
{
    use TraitGroupByOwner;
    use TraitOptionalElement;

    public function getValueOptions(): array
    {
        return $this->getValueOptionsFix();
    }
}

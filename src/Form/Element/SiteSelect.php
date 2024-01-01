<?php declare(strict_types=1);

namespace Common\Form\Element;

class SiteSelect extends \Omeka\Form\Element\SiteSelect
{
    use TraitGroupByOwner;
    use TraitOptionalElement;

    public function getValueOptions(): array
    {
        return $this->getValueOptionsFix();
    }
}

<?php declare(strict_types=1);

namespace Common\Form\Element;

use Omeka\Form\Element\UserSelect;

class OptionalUserSelect extends UserSelect
{
    use TraitOptionalElement;
}

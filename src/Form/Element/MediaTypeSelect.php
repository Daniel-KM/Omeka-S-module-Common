<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Form\Element\Select;

class MediaTypeSelect extends Select
{
    use TraitOptionalElement;
    use TraitPrependAndGetValuesOptions;
}

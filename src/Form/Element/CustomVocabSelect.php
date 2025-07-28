<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Form\Element\Select;

class CustomVocabSelect extends Select
{
    use CustomVocabTrait;
    use TraitOptionalElement;
}

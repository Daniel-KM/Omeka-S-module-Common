<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Form\Element\Radio;

class CustomVocabRadio extends Radio
{
    use CustomVocabTrait;
    use TraitOptionalElement;
}

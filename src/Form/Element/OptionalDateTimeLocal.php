<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Form\Element\DateTimeLocal;

class OptionalDateTimeLocal extends DateTimeLocal
{
    use TraitOptionalElement;
}

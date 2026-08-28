<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Form\Element\Time;

class OptionalTime extends Time
{
    use TraitOptionalElement;
}

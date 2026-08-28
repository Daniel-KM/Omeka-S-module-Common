<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Form\Element\DateTime;

/**
 * @deprecated DateTime has been removed from WHATWG HTML. Use DateTimeLocal instead.
 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/datetime
 */
class OptionalDateTime extends DateTime
{
    use TraitOptionalElement;
}

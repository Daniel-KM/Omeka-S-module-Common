<?php declare(strict_types=1);

namespace Common\Form\Element;

use Laminas\Form\Element;

/**
 * Add a static text in forms.
 *
 * A static text can be added in Zend v1.12, but not in Laminas.
 * Furthermore, the "fieldset" element cannot display an info text in Omeka.
 * Unlike Zend, the note cannot be ignored simply in the form output.
 *
 * The content is passed via "text" and option "disable_html_escape" can be set.
 *
 * Rewritten from an idea in Zend_Form_Element_Note in Zend framework version 1.
 * @link https://github.com/zendframework/zf1/blob/master/library/Zend/Form/Element/Note.php
 * @link https://github.com/zendframework/zf1/blob/master/library/Zend/View/Helper/FormNote.php
 *
 * Unlike previous version, it does not create an input filter entry.
 */
class Note extends Element
{
    protected $attributes = [
        'type' => 'note',
    ];

    public function getValue()
    {
        return null;
    }
}

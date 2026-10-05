<?php declare(strict_types=1);

namespace CommonTest\Form\Element;

use Common\Form\Element\MediaTypeSelect;
use Laminas\Form\Form;
use PHPUnit\Framework\TestCase;

class MediaTypeSelectTest extends TestCase
{
    protected function isValid(array $options, array $value): bool
    {
        $element = new MediaTypeSelect('media_types', $options);
        $element
            ->setValueOptions(['image/jpeg' => 'image/jpeg'])
            ->setAttribute('multiple', true);
        $form = new Form();
        $form->add($element);
        $form->setData(['media_types' => $value]);
        return $form->isValid();
    }

    public function testMediaTypeMissingFromTheOptionsIsInvalidByDefault(): void
    {
        $this->assertFalse($this->isValid([], ['application/vnd.pdf2xml+xml']));
        $this->assertTrue($this->isValid([], ['image/jpeg']));
    }

    public function testMediaTypeMissingFromTheOptionsIsValidWithoutInArrayValidator(): void
    {
        // The options are the media types of the medias stored, so a saved one
        // may not exist any more: the modules that store the value disable the
        // validator, else the whole form cannot be saved any more.
        $this->assertTrue($this->isValid(['disable_inarray_validator' => true], ['application/vnd.pdf2xml+xml']));
    }
}

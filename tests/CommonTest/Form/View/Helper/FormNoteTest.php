<?php declare(strict_types=1);

namespace CommonTest\Form\View\Helper;

use Common\Form\Element\Note;
use CommonTest\AbstractHttpControllerTestCase;

class FormNoteTest extends AbstractHttpControllerTestCase
{
    /**
     * @dataProvider noteProvider
     */
    public function testRender(array $options, array $attributes, string $expected): void
    {
        $note = new Note('note');
        $note->setOptions($options);
        $note->setAttributes($attributes);

        $helper = $this->getApplicationServiceLocator()->get('ViewHelperManager')->get('formNote');

        $this->assertSame($expected, $helper($note));
    }

    public function noteProvider(): array
    {
        return [
            'no text' => [[], ['id' => 'a'], ''],
            'text' => [['text' => 'Text'], [], '<p class="note">Text</p>'],
            'text is escaped' => [['text' => '<b>'], [], '<p class="note">&lt;b&gt;</p>'],
            'html' => [['text' => '<b>x</b>', 'disable_html_escape' => true], [], '<div class="note"><b>x</b></div>'],
            'attributes' => [
                ['text' => 'Text'],
                ['id' => 'a', 'class' => 'field', 'data-mode' => 'b', 'style' => 'display: none'],
                '<p id="a" class="note&#x20;field" data-mode="b" style="display&#x3A;&#x20;none">Text</p>',
            ],
        ];
    }
}

<?php declare(strict_types=1);

namespace CommonTest\Form\Element;

use Common\Form\Element as CommonElement;
use Common\Form\Element\FieldsTextarea;
use Laminas\Form\Element;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the FieldsTextarea element: the YAML definition is parsed into Laminas
 * element specifications, kept backward compatible with the "name: label"
 * shorthand, and rendered back for a stable round-trip.
 */
class FieldsTextareaTest extends TestCase
{
    private function element(): FieldsTextarea
    {
        return (new FieldsTextarea())->setName('contactus_fields');
    }

    public function testScalarValueIsATextField(): void
    {
        $specs = $this->element()->stringToArray('phone: Phone');
        $this->assertSame([
            'phone' => [
                'name' => 'phone',
                'type' => Element\Text::class,
                'options' => ['label' => 'Phone'],
                'attributes' => [],
            ],
        ], $specs);
    }

    public function testNullValueIsCoreDefault(): void
    {
        $specs = $this->element()->stringToArray("name:\n");
        $this->assertArrayHasKey('name', $specs);
        $this->assertSame('', $specs['name']['options']['label']);
    }

    public function testObjectWithTypeValuesRequiredAttributes(): void
    {
        $yaml = "topic:\n  label: Subject\n  type: select\n  values: [Question, Bug]\n  required: true\n  attributes:\n    placeholder: Choisir\n";
        $specs = $this->element()->stringToArray($yaml);
        $this->assertSame(CommonElement\OptionalSelect::class, $specs['topic']['type']);
        $this->assertSame(['Question' => 'Question', 'Bug' => 'Bug'], $specs['topic']['options']['value_options']);
        $this->assertTrue($specs['topic']['attributes']['required']);
        $this->assertSame('Choisir', $specs['topic']['attributes']['placeholder']);
    }

    public function testValuesAsMap(): void
    {
        $yaml = "svc:\n  type: select\n  values:\n    com: Communication\n    tech: Technique\n";
        $specs = $this->element()->stringToArray($yaml);
        $this->assertSame(['com' => 'Communication', 'tech' => 'Technique'], $specs['svc']['options']['value_options']);
    }

    public function testMultiCheckboxIsMultiple(): void
    {
        $specs = $this->element()->stringToArray("i:\n  type: multicheckbox\n  values: [A, B]\n");
        $this->assertSame(CommonElement\OptionalMultiCheckbox::class, $specs['i']['type']);
        $this->assertTrue($specs['i']['attributes']['multiple']);
    }

    public function testPhoneAliasMapsToTel(): void
    {
        $specs = $this->element()->stringToArray("phone:\n  type: phone\n");
        $this->assertSame(Element\Tel::class, $specs['phone']['type']);
    }

    public function testUnknownTypeFallsBackToText(): void
    {
        $specs = $this->element()->stringToArray("foo:\n  type: bar\n");
        $this->assertSame(Element\Text::class, $specs['foo']['type']);
    }

    public function testOrderIsPreserved(): void
    {
        $specs = $this->element()->stringToArray("name:\nphone: Phone\nemail: Courriel\nmessage:\n  type: textarea\n");
        $this->assertSame(['name', 'phone', 'email', 'message'], array_keys($specs));
        $this->assertSame(Element\Textarea::class, $specs['message']['type']);
    }

    public function testStarPrefixInScalarMarksRequired(): void
    {
        $specs = $this->element()->stringToArray('phone: "* Phone"');
        $this->assertTrue($specs['phone']['attributes']['required']);
        $this->assertSame('Phone', $specs['phone']['options']['label']);
    }

    public function testInvalidYamlReturnsStringAndIsInvalid(): void
    {
        $element = $this->element();
        $raw = $element->stringToArray('phone: [unclosed');
        $this->assertIsString($raw);
        $this->assertFalse($element->validateFields($raw));
    }

    public function testEmptyIsAllowed(): void
    {
        $element = $this->element();
        $this->assertSame([], $element->stringToArray("   \n"));
        $this->assertTrue($element->validateFields([]));
    }

    public function testSelectWithoutValuesIsInvalid(): void
    {
        $element = $this->element();
        $specs = $element->stringToArray("t:\n  type: select\n");
        $this->assertFalse($element->validateFields($specs));
    }

    public function testBadNameIsInvalid(): void
    {
        $element = $this->element();
        $specs = $element->stringToArray("'bad name': X");
        $this->assertFalse($element->validateFields($specs));
    }

    public function testValidSpecsAreValid(): void
    {
        $element = $this->element();
        $specs = $element->stringToArray("name:\ntopic:\n  type: select\n  values: [A, B]\n");
        $this->assertTrue($element->validateFields($specs));
    }

    public function testElementOptionsBagIsPassedThrough(): void
    {
        $yaml = "topic:\n  type: select\n  values: [A, B]\n  options:\n    empty_option: 'Choose…'\n    info: Help\n";
        $specs = $this->element()->stringToArray($yaml);
        $this->assertSame('Choose…', $specs['topic']['options']['empty_option']);
        $this->assertSame('Help', $specs['topic']['options']['info']);
        // label and values still live in the options bag.
        $this->assertSame(['A' => 'A', 'B' => 'B'], $specs['topic']['options']['value_options']);
    }

    public function testRoundTripIsStable(): void
    {
        $element = $this->element();
        $src = "phone: Phone\ntopic:\n  label: Subject\n  type: select\n  required: true\n  values: [Question, Bug]\n  options:\n    empty_option: 'Choose…'\n  attributes:\n    placeholder: x\n";
        $specs = $element->stringToArray($src);
        $reparsed = $element->stringToArray($element->arrayToString($specs));
        $this->assertSame($specs, $reparsed);
    }

    public function testArrayInputIsKeptAsSpecs(): void
    {
        $spec = [
            'phone' => [
                'name' => 'phone',
                'type' => Element\Tel::class,
                'options' => ['label' => 'Phone'],
                'attributes' => ['required' => true],
            ],
        ];
        $this->assertSame($spec, $this->element()->stringToArray($spec));
    }

    public function testSystemFieldsAreKeptByDefault(): void
    {
        // Generic element: no system field is dropped unless configured.
        $specs = $this->element()->stringToArray("id: [1, 3, 18]\nphone: Phone");
        $this->assertArrayHasKey('id', $specs);
        $this->assertArrayHasKey('phone', $specs);
    }

    public function testConfiguredSystemFieldsAreDropped(): void
    {
        $element = $this->element();
        $element->setOptions(['system_fields' => ['id']]);
        $fromYaml = $element->stringToArray("id: [1, 3, 18]\nphone: Phone");
        $this->assertArrayNotHasKey('id', $fromYaml);
        $this->assertArrayHasKey('phone', $fromYaml);

        $fromArray = $element->stringToArray(['id' => [1, 3, 18], 'phone' => 'Phone']);
        $this->assertArrayNotHasKey('id', $fromArray);

        $dumped = $element->arrayToString(['id' => [1, 3, 18], 'phone' => 'Phone']);
        $this->assertArrayNotHasKey('id', $element->stringToArray($dumped));
    }

    public function testEditorOptionsDefaultsToDataAttributes(): void
    {
        $element = $this->element();
        $element->setOptions([]);
        $this->assertSame('1', $element->getAttribute('data-enable-form'));
        $this->assertSame('1', $element->getAttribute('data-enable-yaml'));
        $this->assertSame('0', $element->getAttribute('data-enable-preview'));
        $this->assertSame('form', $element->getAttribute('data-default-display'));
    }

    public function testEditorOptionsOverrideDataAttributes(): void
    {
        $element = $this->element();
        $element->setOptions([
            'enable_edit_form' => false,
            'enable_preview' => true,
            'default_display' => 'text',
        ]);
        $this->assertSame('0', $element->getAttribute('data-enable-form'));
        $this->assertSame('1', $element->getAttribute('data-enable-yaml'));
        $this->assertSame('1', $element->getAttribute('data-enable-preview'));
        $this->assertSame('text', $element->getAttribute('data-default-display'));
    }

    public function testLegacyScalarArrayDumpsAndReparses(): void
    {
        $element = $this->element();
        $yaml = $element->arrayToString(['phone' => 'Phone', 'siret' => '* SIRET']);
        $specs = $element->stringToArray($yaml);
        $this->assertSame(Element\Text::class, $specs['phone']['type']);
        $this->assertTrue($specs['siret']['attributes']['required']);
        $this->assertSame('SIRET', $specs['siret']['options']['label']);
    }
}

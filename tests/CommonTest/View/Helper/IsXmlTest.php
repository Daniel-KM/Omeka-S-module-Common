<?php declare(strict_types=1);

namespace CommonTest\View\Helper;

use Common\View\Helper\IsXml;
use PHPUnit\Framework\TestCase;

/**
 * Test for IsXml view helper.
 */
class IsXmlTest extends TestCase
{
    protected IsXml $helper;

    protected function setUp(): void
    {
        $this->helper = new IsXml();
    }

    public function testEmptyString(): void
    {
        $this->assertFalse($this->helper->__invoke(''));
    }

    public function testNullValue(): void
    {
        $this->assertFalse($this->helper->__invoke(null));
    }

    public function testFalseValue(): void
    {
        $this->assertFalse($this->helper->__invoke(false));
    }

    public function testZeroValue(): void
    {
        $this->assertFalse($this->helper->__invoke(0));
    }

    public function testPlainText(): void
    {
        $this->assertFalse($this->helper->__invoke('Just plain text'));
    }

    public function testSimpleXml(): void
    {
        $this->assertTrue($this->helper->__invoke('<root>content</root>'));
    }

    public function testXmlWithAttributes(): void
    {
        $this->assertTrue($this->helper->__invoke('<root attr="value">content</root>'));
    }

    public function testXmlWithNamespace(): void
    {
        $this->assertTrue($this->helper->__invoke('<ns:root xmlns:ns="http://example.org">content</ns:root>'));
    }

    public function testXmlWithNestedElements(): void
    {
        $xml = '<root><child>text</child><other attr="1"/></root>';
        $this->assertTrue($this->helper->__invoke($xml));
    }

    public function testXmlWithDeclaration(): void
    {
        $xml = '<?xml version="1.0"?><root>content</root>';
        $this->assertTrue($this->helper->__invoke($xml));
    }

    public function testXmlWithWhitespace(): void
    {
        $xml = "  <root>content</root>  ";
        $this->assertTrue($this->helper->__invoke($xml));
    }

    public function testNotStartingWithTag(): void
    {
        $this->assertFalse($this->helper->__invoke('text<tag>content</tag>'));
    }

    public function testNotEndingWithTag(): void
    {
        $this->assertFalse($this->helper->__invoke('<tag>content</tag>text'));
    }

    public function testMismatchedTags(): void
    {
        $this->assertFalse($this->helper->__invoke('<open>content</close>'));
    }

    public function testUnclosedTag(): void
    {
        $this->assertFalse($this->helper->__invoke('<root>content'));
    }

    public function testSelfClosingTagOnly(): void
    {
        // Self-closing tags without a wrapper are not valid XML fragment
        $this->assertFalse($this->helper->__invoke('<br/>'));
    }

    public function testSelfClosingTagInRoot(): void
    {
        $this->assertTrue($this->helper->__invoke('<root><br/></root>'));
    }

    public function testXmlWithCdata(): void
    {
        $xml = '<root><![CDATA[Some <data> here]]></root>';
        $this->assertTrue($this->helper->__invoke($xml));
    }

    public function testXmlWithComments(): void
    {
        $xml = '<root><!-- comment --></root>';
        $this->assertTrue($this->helper->__invoke($xml));
    }

    public function testArrayInput(): void
    {
        $this->assertFalse($this->helper->__invoke(['not', 'a', 'string']));
    }

    public function testObjectWithoutToString(): void
    {
        $obj = new \stdClass();
        $this->assertFalse($this->helper->__invoke($obj));
    }

    public function testObjectWithToString(): void
    {
        $obj = new class {
            public function __toString(): string
            {
                return '<root>content</root>';
            }
        };
        $this->assertTrue($this->helper->__invoke($obj));
    }

    public function testXmlWithEntities(): void
    {
        // Note: IsXml uses html_entity_decode before parsing, so &amp; becomes &
        // which breaks XML parsing. This is expected behavior for rdf:XMLLiteral.
        $xml = '<root>&amp; &lt; &gt;</root>';
        $this->assertFalse($this->helper->__invoke($xml));
    }

    public function testXmlWithPlainText(): void
    {
        // Plain text content (no entities) should work
        $xml = '<root>Simple text content</root>';
        $this->assertTrue($this->helper->__invoke($xml));
    }

    public function testXmlWithMultibyte(): void
    {
        $xml = '<root>Zeichenfolge mit Umlauten: äöüß</root>';
        $this->assertTrue($this->helper->__invoke($xml));
    }

    public function testXmlWithEmoji(): void
    {
        $xml = '<root>Text with emoji: 😀🎉</root>';
        $this->assertTrue($this->helper->__invoke($xml));
    }

    public function testXmlTagWithSpaceBeforeClose(): void
    {
        $xml = '<root attr="value" >content</root>';
        $this->assertTrue($this->helper->__invoke($xml));
    }

    public function testMalformedXml(): void
    {
        $xml = '<root><unclosed></root>';
        $this->assertFalse($this->helper->__invoke($xml));
    }

    public function testXmlWithMixedContent(): void
    {
        $xml = '<root>Text <child>nested</child> more text</root>';
        $this->assertTrue($this->helper->__invoke($xml));
    }
}

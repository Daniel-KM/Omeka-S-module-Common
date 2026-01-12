<?php declare(strict_types=1);

namespace CommonTest\View\Helper;

use Common\View\Helper\IsHtml;
use PHPUnit\Framework\TestCase;

/**
 * Test for IsHtml view helper.
 */
class IsHtmlTest extends TestCase
{
    protected IsHtml $helper;

    protected function setUp(): void
    {
        $this->helper = new IsHtml();
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

    public function testTextWithHtmlInMiddle(): void
    {
        // This should be false because it doesn't start with a tag
        $this->assertFalse($this->helper->__invoke('This is <strong>not</strong> valid html for this function.'));
    }

    public function testSimpleHtmlTag(): void
    {
        $this->assertTrue($this->helper->__invoke('<p>Paragraph</p>'));
    }

    public function testSpanTag(): void
    {
        $this->assertTrue($this->helper->__invoke('<span>Text</span>'));
    }

    public function testNestedHtml(): void
    {
        $html = '<span>This is a <strong>valid</strong> html for this function.</span>';
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testDivWithContent(): void
    {
        $html = '<div><p>Paragraph 1</p><p>Paragraph 2</p></div>';
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testHtmlWithAttributes(): void
    {
        $html = '<a href="http://example.com" class="link">Link text</a>';
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testHtmlWithWhitespace(): void
    {
        $html = "   <p>Text</p>   ";
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testSelfClosingBr(): void
    {
        // A self-closing tag passes basic checks (starts with <, ends with >, has tags)
        $this->assertTrue($this->helper->__invoke('<br/>'));
    }

    public function testSelfClosingBrWithSpan(): void
    {
        $this->assertTrue($this->helper->__invoke('<span>Line 1<br/>Line 2</span>'));
    }

    public function testNotStartingWithTag(): void
    {
        $this->assertFalse($this->helper->__invoke('text<p>content</p>'));
    }

    public function testNotEndingWithTag(): void
    {
        $this->assertFalse($this->helper->__invoke('<p>content</p>text'));
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
                return '<p>content</p>';
            }
        };
        $this->assertTrue($this->helper->__invoke($obj));
    }

    public function testHtmlWithEntities(): void
    {
        $html = '<p>&amp; &lt; &gt; &nbsp;</p>';
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testHtmlWithMultibyte(): void
    {
        $html = '<p>Texte en français avec accents: éèêë</p>';
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testHtmlWithEmoji(): void
    {
        $html = '<span>Emoji test: 🎉😀</span>';
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testHtmlList(): void
    {
        $html = '<ul><li>Item 1</li><li>Item 2</li></ul>';
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testHtmlTable(): void
    {
        $html = '<table><tr><td>Cell</td></tr></table>';
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testHtmlWithStyle(): void
    {
        $html = '<span style="color: red;">Styled text</span>';
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testHtmlWithDataAttribute(): void
    {
        $html = '<div data-value="123">Content</div>';
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testEmptyTagPair(): void
    {
        $this->assertTrue($this->helper->__invoke('<span></span>'));
    }

    public function testTagWithOnlyWhitespace(): void
    {
        $this->assertTrue($this->helper->__invoke('<p>   </p>'));
    }

    public function testMultipleRootElements(): void
    {
        // Multiple root elements - starts and ends with tags but multiple roots
        // This should still pass the basic checks
        $html = '<p>First</p><p>Second</p>';
        $this->assertTrue($this->helper->__invoke($html));
    }

    public function testHtml5Tags(): void
    {
        $html = '<article><header>Title</header><section>Content</section></article>';
        $this->assertTrue($this->helper->__invoke($html));
    }
}

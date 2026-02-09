<?php declare(strict_types=1);

namespace CommonTest\Stdlib;

use Common\Stdlib\PsrMessage;
use PHPUnit\Framework\TestCase;

/**
 * Test for PsrMessage class.
 */
class PsrMessageTest extends TestCase
{
    public function testConstructorWithMessage(): void
    {
        $message = new PsrMessage('Test message');
        $this->assertSame('Test message', $message->getMessage());
        $this->assertSame([], $message->getContext());
        $this->assertFalse($message->hasContext());
    }

    public function testConstructorWithPsrContext(): void
    {
        $message = new PsrMessage('Hello {name}', ['name' => 'World']);
        $this->assertSame('Hello {name}', $message->getMessage());
        $this->assertSame(['name' => 'World'], $message->getContext());
        $this->assertTrue($message->hasContext());
        $this->assertFalse($message->isSprintFormat());
    }

    public function testConstructorWithSprintfContext(): void
    {
        $message = new PsrMessage('Hello %s', 'World');
        $this->assertSame('Hello %s', $message->getMessage());
        $this->assertSame(['World'], $message->getContext());
        $this->assertTrue($message->hasContext());
        $this->assertTrue($message->isSprintFormat());
    }

    public function testConstructorWithMultipleSprintfArgs(): void
    {
        $message = new PsrMessage('%s has %d items', 'Cart', 5);
        $this->assertSame(['Cart', 5], $message->getContext());
        $this->assertTrue($message->isSprintFormat());
    }

    public function testToStringWithPsrInterpolation(): void
    {
        $message = new PsrMessage('Hello {name}, welcome to {place}!', [
            'name' => 'Alice',
            'place' => 'Wonderland',
        ]);
        $this->assertSame('Hello Alice, welcome to Wonderland!', (string) $message);
    }

    public function testToStringWithSprintfInterpolation(): void
    {
        $message = new PsrMessage('Hello %s, you have %d messages', 'Bob', 42);
        $this->assertSame('Hello Bob, you have 42 messages', (string) $message);
    }

    public function testToStringWithoutContext(): void
    {
        $message = new PsrMessage('Simple message');
        $this->assertSame('Simple message', (string) $message);
    }

    public function testToStringWithEmptyContext(): void
    {
        $message = new PsrMessage('Message with {placeholder}', []);
        $this->assertSame('Message with {placeholder}', (string) $message);
    }

    public function testToStringWithMissingPlaceholder(): void
    {
        $message = new PsrMessage('Hello {name} and {other}', ['name' => 'Test']);
        $this->assertSame('Hello Test and {other}', (string) $message);
    }

    public function testEscapeHtmlDefault(): void
    {
        $message = new PsrMessage('Test');
        $this->assertTrue($message->getEscapeHtml());
    }

    public function testSetEscapeHtml(): void
    {
        $message = new PsrMessage('Test');
        $message->setEscapeHtml(false);
        $this->assertFalse($message->getEscapeHtml());
    }

    public function testEscapeHtmlDeprecatedAlias(): void
    {
        $message = new PsrMessage('Test');
        $message->setEscapeHtml(false);
        $this->assertFalse($message->escapeHtml());
    }

    public function testGetArgsDeprecated(): void
    {
        $message = new PsrMessage('Hello {name}', ['name' => 'World']);
        $this->assertSame(['World'], $message->getArgs());
    }

    public function testHasArgsDeprecated(): void
    {
        $message = new PsrMessage('Test');
        $this->assertFalse($message->hasArgs());

        $message = new PsrMessage('Hello {name}', ['name' => 'World']);
        $this->assertTrue($message->hasArgs());
    }

    public function testJsonSerialize(): void
    {
        $message = new PsrMessage('Hello {name}!', ['name' => 'JSON']);
        $this->assertSame('"Hello JSON!"', json_encode($message));
    }

    public function testTranslatorAwareInterface(): void
    {
        $message = new PsrMessage('Test');
        $this->assertFalse($message->hasTranslator());
        $this->assertTrue($message->isTranslatorEnabled());
    }

    public function testTranslateWithoutTranslator(): void
    {
        $message = new PsrMessage('Hello {name}!', ['name' => 'Test']);
        $this->assertSame('Hello Test!', $message->translate());
    }

    public function testSprintfTranslateWithoutTranslator(): void
    {
        $message = new PsrMessage('Value: %d', 123);
        $this->assertSame('Value: 123', $message->translate());
    }

    public function testContextWithObjectToString(): void
    {
        $obj = new class {
            public function __toString(): string
            {
                return 'StringableObject';
            }
        };
        $message = new PsrMessage('Object: {obj}', ['obj' => $obj]);
        $this->assertSame('Object: StringableObject', (string) $message);
    }

    public function testContextWithNumericKeys(): void
    {
        // PSR-3 style with numeric keys should still work
        $message = new PsrMessage('Values: {0} and {1}', ['0' => 'first', '1' => 'second']);
        $this->assertSame('Values: first and second', (string) $message);
    }

    public function testNestedBraces(): void
    {
        $message = new PsrMessage('Code: {{name}}', ['name' => 'test']);
        // The inner braces remain, outer braces are not replaced
        $this->assertSame('Code: {test}', (string) $message);
    }

    public function testEmptyMessage(): void
    {
        $message = new PsrMessage('');
        $this->assertSame('', (string) $message);
    }

    public function testSetTranslatorReturnsThis(): void
    {
        $message = new PsrMessage('Test');
        $result = $message->setEscapeHtml(false);
        $this->assertSame($message, $result);
    }

    public function testInstanceOfOmekaPsrMessage(): void
    {
        $message = new PsrMessage('Test');
        $this->assertInstanceOf(\Omeka\Stdlib\PsrMessage::class, $message);
    }

    public function testInstanceOfMessageInterface(): void
    {
        $message = new PsrMessage('Test');
        $this->assertInstanceOf(\Omeka\Stdlib\MessageInterface::class, $message);
    }

    public function testTranslateWithTranslatorInterface(): void
    {
        $translator = $this->createMock(\Laminas\I18n\Translator\TranslatorInterface::class);
        $translator->method('translate')
            ->with('Hello {name}', 'default', null)
            ->willReturn('Bonjour {name}');

        $message = new PsrMessage('Hello {name}', ['name' => 'World']);
        $this->assertSame('Bonjour World', $message->translate($translator));
    }

    public function testTranslateSprintfWithTranslatorInterface(): void
    {
        $translator = $this->createMock(\Laminas\I18n\Translator\TranslatorInterface::class);
        $translator->method('translate')
            ->with('Value: %d', 'default', null)
            ->willReturn('Valeur : %d');

        $message = new PsrMessage('Value: %d', 123);
        $this->assertSame('Valeur : 123', $message->translate($translator));
    }

    public function testTranslateWithInternalTranslator(): void
    {
        $translator = $this->createMock(\Laminas\I18n\Translator\TranslatorInterface::class);
        $translator->method('translate')
            ->with('Hello {name}', 'default', null)
            ->willReturn('Bonjour {name}');

        $message = new PsrMessage('Hello {name}', ['name' => 'World']);
        $message->setTranslator($translator);
        $this->assertSame('Bonjour World', $message->translate());
    }
}

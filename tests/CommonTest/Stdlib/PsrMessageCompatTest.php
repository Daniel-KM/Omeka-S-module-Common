<?php declare(strict_types=1);

namespace CommonTest\Stdlib;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the PsrMessage polyfill compatibility files.
 *
 * The polyfill files in data/compat/ provide Omeka\Stdlib\PsrMessage and its
 * dependencies for Omeka S < 4.2, where these classes don't exist in core.
 *
 * Since on Omeka 4.2+ the core classes are already loaded, these tests verify:
 * - The compat files exist and declare the correct types in the right namespace.
 * - The Omeka\Stdlib API matches what Common expects (validates both core and
 *   polyfill since the polyfill is a copy of core).
 * - Core PsrMessage behavior is correct (same behavior the polyfill provides).
 * - Common\Stdlib\PsrMessage properly extends the core class.
 */
class PsrMessageCompatTest extends TestCase
{
    protected function compatDir(): string
    {
        return dirname(__DIR__, 3) . '/data/compat';
    }

    // Compat files existence.

    public function testCompatDirectoryExists(): void
    {
        $this->assertDirectoryExists($this->compatDir());
    }

    /**
     * @dataProvider compatFileProvider
     */
    public function testCompatFileExists(string $file): void
    {
        $this->assertFileExists($this->compatDir() . '/' . $file);
    }

    public function compatFileProvider(): array
    {
        return [
            'PsrInterpolateInterface' => ['PsrInterpolateInterface.php'],
            'PsrInterpolateTrait' => ['PsrInterpolateTrait.php'],
            'PsrMessage' => ['PsrMessage.php'],
        ];
    }

    // Compat files declare correct Omeka\Stdlib namespace.

    /**
     * @dataProvider compatFileProvider
     */
    public function testCompatFileDeclaresOmekaStdlibNamespace(string $file): void
    {
        $content = file_get_contents($this->compatDir() . '/' . $file);
        $this->assertStringContainsString('namespace Omeka\Stdlib;', $content);
    }

    public function testCompatPsrInterpolateInterfaceDeclaration(): void
    {
        $content = file_get_contents($this->compatDir() . '/PsrInterpolateInterface.php');
        $this->assertMatchesRegularExpression('/interface\s+PsrInterpolateInterface/', $content);
    }

    public function testCompatPsrInterpolateTraitDeclaration(): void
    {
        $content = file_get_contents($this->compatDir() . '/PsrInterpolateTrait.php');
        $this->assertMatchesRegularExpression('/trait\s+PsrInterpolateTrait/', $content);
    }

    public function testCompatPsrMessageClassDeclaration(): void
    {
        $content = file_get_contents($this->compatDir() . '/PsrMessage.php');
        $this->assertMatchesRegularExpression('/class\s+PsrMessage\s+implements\s+MessageInterface/', $content);
    }

    public function testCompatPsrMessageImplementsPsrInterpolateInterface(): void
    {
        $content = file_get_contents($this->compatDir() . '/PsrMessage.php');
        $this->assertStringContainsString('PsrInterpolateInterface', $content);
    }

    public function testCompatPsrMessageUsesPsrInterpolateTrait(): void
    {
        $content = file_get_contents($this->compatDir() . '/PsrMessage.php');
        $this->assertStringContainsString('use PsrInterpolateTrait;', $content);
    }

    // Omeka\Stdlib API verification via reflection.
    // This validates both core classes (on 4.2) and the polyfill (which is a
    // copy), ensuring the API that Common\Stdlib\PsrMessage depends on exists.

    public function testOmekaPsrInterpolateInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(\Omeka\Stdlib\PsrInterpolateInterface::class));
    }

    public function testOmekaPsrInterpolateInterfaceHasInterpolateMethod(): void
    {
        $ref = new \ReflectionClass(\Omeka\Stdlib\PsrInterpolateInterface::class);
        $this->assertTrue($ref->hasMethod('interpolate'));
        $method = $ref->getMethod('interpolate');
        $this->assertTrue($method->isPublic());
        $this->assertCount(2, $method->getParameters());
    }

    public function testOmekaPsrInterpolateTraitExists(): void
    {
        $this->assertTrue(trait_exists(\Omeka\Stdlib\PsrInterpolateTrait::class));
    }

    public function testOmekaPsrMessageExists(): void
    {
        $this->assertTrue(class_exists(\Omeka\Stdlib\PsrMessage::class));
    }

    public function testOmekaPsrMessageImplementsMessageInterface(): void
    {
        $ref = new \ReflectionClass(\Omeka\Stdlib\PsrMessage::class);
        $this->assertTrue($ref->implementsInterface(\Omeka\Stdlib\MessageInterface::class));
    }

    public function testOmekaPsrMessageImplementsPsrInterpolateInterface(): void
    {
        $ref = new \ReflectionClass(\Omeka\Stdlib\PsrMessage::class);
        $this->assertTrue($ref->implementsInterface(\Omeka\Stdlib\PsrInterpolateInterface::class));
    }

    public function testOmekaPsrMessageHasExpectedPublicMethods(): void
    {
        $expectedMethods = [
            'getMessage',
            'getContext',
            'hasContext',
            'setEscapeHtml',
            'escapeHtml',
            'translate',
            'interpolate',
            'jsonSerialize',
            '__toString',
            '__construct',
        ];
        $ref = new \ReflectionClass(\Omeka\Stdlib\PsrMessage::class);
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "Missing method on Omeka\\Stdlib\\PsrMessage: $method"
            );
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "Method $method should be public on Omeka\\Stdlib\\PsrMessage"
            );
        }
    }

    // Core Omeka\Stdlib\PsrMessage behavior.
    // These tests exercise the core class directly (not Common's subclass).
    // Since the polyfill is a copy, this also validates polyfill behavior.

    public function testCorePsrMessageConstruction(): void
    {
        $msg = new \Omeka\Stdlib\PsrMessage('Hello {name}', ['name' => 'World']);
        $this->assertSame('Hello {name}', $msg->getMessage());
        $this->assertSame(['name' => 'World'], $msg->getContext());
        $this->assertTrue($msg->hasContext());
    }

    public function testCorePsrMessageWithoutContext(): void
    {
        $msg = new \Omeka\Stdlib\PsrMessage('Simple');
        $this->assertSame('Simple', (string) $msg);
        $this->assertFalse($msg->hasContext());
        $this->assertSame([], $msg->getContext());
    }

    public function testCorePsrMessageInterpolation(): void
    {
        $msg = new \Omeka\Stdlib\PsrMessage('Hello {name}!', ['name' => 'Test']);
        $this->assertSame('Hello Test!', (string) $msg);
    }

    public function testCorePsrMessageMultiplePlaceholders(): void
    {
        $msg = new \Omeka\Stdlib\PsrMessage('{a} and {b}', ['a' => 'X', 'b' => 'Y']);
        $this->assertSame('X and Y', (string) $msg);
    }

    public function testCorePsrMessageMissingPlaceholder(): void
    {
        $msg = new \Omeka\Stdlib\PsrMessage('{found} and {missing}', ['found' => 'OK']);
        $this->assertSame('OK and {missing}', (string) $msg);
    }

    public function testCorePsrMessageEscapeHtml(): void
    {
        $msg = new \Omeka\Stdlib\PsrMessage('Test');
        $this->assertTrue($msg->escapeHtml());
        $result = $msg->setEscapeHtml(false);
        $this->assertFalse($msg->escapeHtml());
        $this->assertSame($msg, $result, 'setEscapeHtml() should return $this');
    }

    public function testCorePsrMessageJsonSerialize(): void
    {
        $msg = new \Omeka\Stdlib\PsrMessage('{x}!', ['x' => 'JSON']);
        $this->assertSame('"JSON!"', json_encode($msg));
    }

    public function testCorePsrMessageTranslateWithTranslator(): void
    {
        $translator = $this->createMock(\Laminas\I18n\Translator\TranslatorInterface::class);
        $translator->method('translate')
            ->with('Hello {name}', 'default', null)
            ->willReturn('Bonjour {name}');
        $msg = new \Omeka\Stdlib\PsrMessage('Hello {name}', ['name' => 'World']);
        $this->assertSame('Bonjour World', $msg->translate($translator));
    }

    public function testCorePsrMessageTranslateWithTextDomain(): void
    {
        $translator = $this->createMock(\Laminas\I18n\Translator\TranslatorInterface::class);
        $translator->method('translate')
            ->with('msg', 'custom', null)
            ->willReturn('translated');
        $msg = new \Omeka\Stdlib\PsrMessage('msg');
        $this->assertSame('translated', $msg->translate($translator, 'custom'));
    }

    // Common\Stdlib\PsrMessage extends core class.

    public function testCommonPsrMessageExtendsCoreClass(): void
    {
        $ref = new \ReflectionClass(\Common\Stdlib\PsrMessage::class);
        $this->assertSame(
            'Omeka\Stdlib\PsrMessage',
            $ref->getParentClass()->getName()
        );
    }

    public function testCommonPsrMessageIsInstanceOfCoreClass(): void
    {
        $msg = new \Common\Stdlib\PsrMessage('Test');
        $this->assertInstanceOf(\Omeka\Stdlib\PsrMessage::class, $msg);
        $this->assertInstanceOf(\Omeka\Stdlib\MessageInterface::class, $msg);
        $this->assertInstanceOf(\Omeka\Stdlib\PsrInterpolateInterface::class, $msg);
    }

    // Version compare logic used in TraitModule.php and Module.php.

    public function testVersionCompareOlderVersionsLoadPolyfill(): void
    {
        $this->assertTrue(version_compare('4.0.0', '4.2', '<'));
        $this->assertTrue(version_compare('4.0.4', '4.2', '<'));
        $this->assertTrue(version_compare('4.1.0', '4.2', '<'));
        $this->assertTrue(version_compare('4.1.9', '4.2', '<'));
    }

    public function testVersionCompareCurrentVersionSkipsPolyfill(): void
    {
        $this->assertFalse(version_compare('4.2.0', '4.2', '<'));
        $this->assertFalse(version_compare('4.2.1', '4.2', '<'));
        $this->assertFalse(version_compare('4.3.0', '4.2', '<'));
        $this->assertFalse(version_compare('5.0.0', '4.2', '<'));
    }

    public function testCurrentOmekaVersionIsAtLeast42(): void
    {
        $this->assertFalse(
            version_compare(\Omeka\Module::VERSION, '4.2', '<'),
            'Current Omeka version should be >= 4.2; polyfill is for older versions.'
        );
    }
}

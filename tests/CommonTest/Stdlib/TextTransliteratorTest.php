<?php declare(strict_types=1);

namespace CommonTest\Stdlib;

use Common\Stdlib\TextTransliterator;
use Omeka\Test\TestCase;

class TextTransliteratorTest extends TestCase
{
    /**
     * @dataProvider latinProvider
     */
    public function testToAsciiLatin(string $string, string $expected): void
    {
        $this->assertSame($expected, TextTransliterator::toAscii($string));
    }

    public function latinProvider(): array
    {
        return [
            ['café', 'cafe'],
            ['École', 'Ecole'],
            ['Œuvre', 'OEuvre'],
            // The sharp s is folded to "ss", not to "sz".
            ['Straße', 'Strasse'],
            ['ascii', 'ascii'],
            ['', ''],
        ];
    }

    /**
     * @dataProvider scriptProvider
     */
    public function testToAsciiOtherScripts(string $string, string $expected): void
    {
        if (!class_exists(\Transliterator::class)) {
            $this->markTestSkipped('Extension intl is required for non-Latin scripts.');
        }
        $this->assertSame($expected, TextTransliterator::toAscii($string));
    }

    public function scriptProvider(): array
    {
        return [
            ['Привет', 'Privet'],
            ['Ελλάδα', 'Ellada'],
            ['Đặng', 'Dang'],
        ];
    }

    public function testToAsciiKeepsInvalidUtf8(): void
    {
        $invalid = 'photo' . chr(0xA9);
        $this->assertSame($invalid, TextTransliterator::toAscii($invalid));
    }

    public function testToAsciiFilename(): void
    {
        $this->assertSame('Ecole.pdf', TextTransliterator::toAsciiFilename('École.pdf'));
        $this->assertSame('photo_C_.png', TextTransliterator::toAsciiFilename('photo©.png'));
        // Consecutive replaced characters are collapsed.
        $this->assertSame('a_b.png', TextTransliterator::toAsciiFilename('a€£b.png'));
        $this->assertSame('file', TextTransliterator::toAsciiFilename(''));
        $this->assertSame('none', TextTransliterator::toAsciiFilename('', 'none'));
    }
}

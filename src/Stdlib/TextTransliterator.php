<?php declare(strict_types=1);

namespace Common\Stdlib;

/**
 * Fold unicode strings to a plain ascii equivalent.
 *
 * The ICU transliterator is used when available, else iconv, else a last
 * resort mapping of the html entities, that covers Latin-1 only.
 */
final class TextTransliterator
{
    /**
     * Transliteration rules used to fold any script to ascii.
     */
    const RULES = 'Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC';

    /**
     * Fold a utf-8 string to a plain ascii equivalent.
     *
     * The characters that cannot be folded are left as is, so the caller
     * filters or replaces them according to its own needs: "École" is folded
     * to "Ecole", "Привет" to "Privet" and "Straße" to "Strasse".
     */
    public static function toAscii(string $string): string
    {
        // Skip an invalid utf-8 string: on such a string, preg_match() returns
        // false, not 0.
        if ($string === '' || preg_match('//u', $string) !== 1) {
            return $string;
        }

        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create(self::RULES);
            if ($transliterator) {
                $result = $transliterator->transliterate($string);
                if ($result !== false) {
                    return $result;
                }
            }
        }

        $previousLocale = setlocale(LC_CTYPE, '0');
        setlocale(LC_CTYPE, 'C.UTF-8', 'en_US.UTF-8', 'C');
        $result = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        if ($previousLocale !== false) {
            setlocale(LC_CTYPE, $previousLocale);
        }
        if ($result !== false && $result !== null) {
            return $result;
        }

        // Last resort when neither intl nor iconv is available: only Latin-1
        // characters get a proper mapping.
        $string = htmlentities($string, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $string = preg_replace('#\&([A-Za-z])(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron)\;#', '\1', $string);
        $string = preg_replace('#\&([A-Za-z]{2})(?:lig)\;#', '\1', $string);
        return preg_replace('#\&[^;]+\;#', '_', $string);
    }

    /**
     * Fold a utf-8 filename to a plain ascii filename.
     *
     * Alphanumerics, dot, dash, underscore and space are kept; the other
     * characters are replaced by a single underscore, so the result stays a
     * valid, non-empty name.
     */
    public static function toAsciiFilename(string $filename, string $fallback = 'file'): string
    {
        $ascii = preg_replace(['/[^A-Za-z0-9._\- ]/', '/_+/'], ['_', '_'], static::toAscii($filename));
        return $ascii === '' ? $fallback : $ascii;
    }
}

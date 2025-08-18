<?php declare(strict_types=1);

namespace Common\I18n;

use Common\Stdlib\PsrMessage;
use InvalidArgumentException;
use Omeka\Stdlib\Message;

/**
 * Adaptation of Omeka translator to manage PsrMessage.
 *
 * @throw \InvalidArgumentException
 */
class Translator extends \Omeka\I18n\Translator
{
    public function translate($message, $textDomain = 'default', $locale = null)
    {
        if (is_scalar($message)) {
            return $this->translator->translate((string) $message, $textDomain, $locale);
        } elseif ($message === null) {
            return '';
        }

        if (is_object($message)) {
            // Check PsrMessage first because it is more standard and complete.
            if ($message instanceof PsrMessage) {
                // Process translation here to avoid useless sub-call.
                $translation = $this->translator->translate($message->getMessage(), $textDomain, $locale);
                if ($message->hasContext()) {
                    $translation = $message->isSprintFormat()
                        ? sprintf($translation, ...$message->getArgs())
                        : $message->interpolate($translation, $message->getContext());
                }
                return $translation;
            } elseif ($message instanceof Message) {
                $translation = $this->translator->translate($message->getMessage(), $textDomain, $locale);
                return $message->hasArgs()
                    ? sprintf($translation, ...$message->getArgs())
                    : $translation;
            } elseif (method_exists($message, '__toString')) {
                return $this->translator->translate((string) $message, $textDomain, $locale);
            }
        }

        throw new InvalidArgumentException('A message to translate should be stringable.'); // @translate
    }

    public function translatePlural($singular, $plural, $number, $textDomain = 'default', $locale = null)
    {
        /**
         * The process is strange: the singular is generally translated, but not
         * the plural. So there is a risk of double translation for singular and
         * a risk of missing translation for plural. And the process implies to
         * get translations multiple times.
         *
         * @see \Laminas\I18n\Translator\Translator::translatePlural()
         */

        // A quick check for simple strings.
        if ($singular === $plural) {
            return $this->translate($singular, $textDomain, $locale);
        }

        $singularMessage = $singular instanceof Message ? $singular->getMessage() : (string) $singular;
        $pluralMessage = $plural instanceof Message ? $plural->getMessage() : (string) $plural;
        $translation = $this->translator->translatePlural($singularMessage, $pluralMessage, (int) $number, $textDomain, $locale);

        if ($translation === $pluralMessage) {
            if ($plural instanceof PsrMessage) {
                return $plural->setTranslator($this->translator)->translate($textDomain, $locale);
            } elseif ($plural instanceof Message && $plural->hasArgs()) {
                return (string) sprintf($this->translator->translate($plural, $textDomain, $locale), ...$plural->getArgs());
            } else {
                return $this->translator->translate($pluralMessage, $textDomain, $locale);
            }
        } elseif ($translation === $singularMessage) {
            if ($singular instanceof PsrMessage) {
                return $singular->setTranslator($this->translator)->translate($textDomain, $locale);
            } elseif ($singular instanceof Message && $singular->hasArgs()) {
                return (string) sprintf($this->translator->translate($singular, $textDomain, $locale), ...$singular->getArgs());
            } else {
                return $this->translator->translate($singularMessage, $textDomain, $locale);
            }
        } else {
            return $translation;
        }
    }
}

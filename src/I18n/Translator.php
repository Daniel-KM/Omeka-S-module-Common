<?php declare(strict_types=1);

namespace Common\I18n;

use Common\Stdlib\PsrMessage;
use Omeka\Stdlib\Message;

/**
 * Adaptation of Omeka translator to manage PsrMessage.
 */
class Translator extends \Omeka\I18n\Translator
{
    public function translate($message, $textDomain = 'default', $locale = null)
    {
        if ($message instanceof Message) {
            $translation = $this->translator->translate($message->getMessage(), $textDomain, $locale);
            if ($message->hasArgs()) {
                $translation = sprintf($translation, ...$message->getArgs());
            }
            return $translation;
        }

        if ($message instanceof PsrMessage) {
            return $message
                ->setTranslator($this->translator)
                ->translate($textDomain, $locale);
        }

        return $this->translator->translate((string) $message, $textDomain, $locale);
    }
}

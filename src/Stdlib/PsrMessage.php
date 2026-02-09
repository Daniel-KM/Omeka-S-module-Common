<?php declare(strict_types=1);

namespace Common\Stdlib;

use Laminas\I18n\Translator\TranslatorAwareInterface;
use Laminas\I18n\Translator\TranslatorAwareTrait;
use Laminas\I18n\Translator\TranslatorInterface;

/**
 * Manage message with context placeholders formatted as psr-3 or as sprintf.
 *
 * PsrMessage was integrated in Omeka S v4.2, so this new version is no more
 * standalone and extends it to be recognized natively, in particular by the
 * core translator delegator (\Omeka\I18n\Translator).
 *
 * Additional features:
 * - TranslatorAwareInterface: set translator then use translate() without args.
 * - Variadic constructor: supports both PSR-3 array context and sprintf-style
 *   positional arguments for backward compatibility with \Omeka\Stdlib\Message.
 * - Polymorphic translate(): accepts TranslatorInterface as first arg (core) or
 *   translator interface aware signature (no args).
 *
 * ```
 * // PSR-3 style (recommended):
 * $message = new PsrMessage('Hello {name}', ['name' => 'World']);
 *
 * // Sprintf style (backward compatibility):
 * $message = new PsrMessage('Hello %s', 'World');
 *
 * // With internal translator (set translator if needed):
 * echo $message->setTranslator($translator)->translate();
 *
 * // With explicit translator (Omeka core style):
 * echo $message->translate($translator);
 *
 * // To get a translator in a controller:
 * $translator = $this->getEvent()->getApplication()->getServiceManager()->get(\Laminas\I18n\Translator\TranslatorInterface::class);
 * // or (deprecated):
 * $translator = $this->getEvent()->getApplication()->getServiceManager()->get('MvcTranslator');
 * // or:
 * $translator = $this->viewHelpers()->get('translate')->getTranslator();
 * // or:
 * $translator = $this->translator();
 *
 * // To get translator in a view:
 * $translator = $this->plugin('translate')->getTranslator();
 * // or:
 * $translator = $this->translator();
 *
 * // To set the translator:
 * $psrMessage->setTranslator($translator);
 * // To disable the translation when the translator is set:
 * $psrMessage->setTranslatorEnabled(false);
 * ```
 *
 * Warning: When used with messenger, a PsrMessage must not contain another
 * PsrMessage as context, because messages are stored in the session that does
 * not support closures.
 *
 * @fixme When a translator is set to a message during upgrade, it cannot be displayed via messenger because it cannot be serialized in session. See if it is still the case.
 *
 * @see \Omeka\Stdlib\PsrMessage
 * @see \Omeka\Stdlib\Message
 */
class PsrMessage extends \Omeka\Stdlib\PsrMessage implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * @var bool
     */
    protected $isSprintf = false;

    /**
     * Set the message string and its context.
     *
     * The plural is not managed.
     * The message can be set as PSR-3 (recommended) or C-style (for sprintf).
     *
     * Variadic args allows to simplify upgrade from standard messages.
     */
    public function __construct($message, ...$context)
    {
        // Early cast to string.
        $this->message = (string) $message;
        if (count($context)) {
            if (is_array($context[0])) {
                $this->context = $context[0];
            } else {
                $this->isSprintf = true;
                $this->context = $context;
            }
        }
    }

    /**
     * Get the message arguments for compatibility purpose only.
     *
     * @deprecated Use getContext() instead.
     * @return array Non-associative array in order to comply with sprintf.
     */
    public function getArgs()
    {
        // Always use array_values for compatibility.
        return array_values($this->context);
    }

    /**
     * Does this message have arguments? For compatibility purpose only.
     *
     * @deprecated Use hasContext() instead.
     * @return bool
     */
    public function hasArgs()
    {
        return (bool) $this->context;
    }

    /**
     * Get the flag escapeHtml.
     */
    public function getEscapeHtml(): bool
    {
        return $this->escapeHtml;
    }

    /**
     * Check if the message uses `sprintf` (old message) or `interpolate` (PSR).
     */
    public function isSprintFormat(): bool
    {
        return $this->isSprintf;
    }

    /**
     * Get the contextualized final message, translated if translator is set.
     *
     * The translation is not done automatically for non-PSR messages, managed
     * with sprintf(), for compatibility with Message(). Use translate() to
     * force it in that case.
     */
    public function __toString()
    {
        if ($this->isSprintf) {
            return $this->context
                ? (string) vsprintf($this->message, array_values($this->context))
                : (string) $this->message;
        }
        return $this->isTranslatorEnabled() && $this->hasTranslator()
            ? $this->interpolate($this->translator->translate($this->message), $this->context)
            : $this->interpolate($this->message, $this->context);
    }

    /**
     * Translate the message with the context.
     *
     * Supports both signatures:
     * - Omeka core (MessageInterface): translate(TranslatorInterface $translator, $textDomain, $locale)
     * - Common with TranslatorInterface: translate($textDomain, $locale) using translator set via interface
     *
     * @param TranslatorInterface|string $translatorOrTextDomain
     * @param string|null $textDomainOrLocale
     * @param string|null $locale
     */
    public function translate($translatorOrTextDomain = 'default', $textDomainOrLocale = null, $locale = null): string
    {
        // Omeka core signature: first argument is a TranslatorInterface.
        if ($translatorOrTextDomain instanceof TranslatorInterface) {
            $translator = $translatorOrTextDomain;
            $textDomain = $textDomainOrLocale ?? 'default';
            if ($this->isSprintf) {
                return $this->context
                    ? (string) vsprintf($translator->translate($this->message, $textDomain, $locale), array_values($this->context))
                    : (string) $this->message;
            }
            return $this->interpolate($translator->translate($this->message, $textDomain, $locale), $this->context);
        }

        // Internal translator set via setTranslator().
        $textDomain = $translatorOrTextDomain;
        $locale = $textDomainOrLocale;
        if ($this->hasTranslator()) {
            if ($this->isSprintf) {
                return $this->context
                    ? (string) vsprintf($this->translator->translate($this->message, $textDomain, $locale), array_values($this->context))
                    : (string) $this->message;
            }
            return $this->interpolate($this->translator->translate($this->message, $textDomain, $locale), $this->context);
        }

        if ($this->isSprintf) {
            return $this->context
                ? (string) vsprintf($this->message, array_values($this->context))
                : (string) $this->message;
        }
        return $this->interpolate($this->message, $this->context);
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }
}

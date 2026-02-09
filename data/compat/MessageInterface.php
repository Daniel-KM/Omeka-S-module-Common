<?php declare(strict_types=1);

/**
 * Polyfill for Omeka S < 4.2.
 *
 * This is a copy of the core interface \Omeka\Stdlib\MessageInterface.
 * It is loaded only when the core version is lower than 4.2, where this
 * interface was introduced.
 *
 * @see \Omeka\Stdlib\MessageInterface
 */

namespace Omeka\Stdlib;

use JsonSerializable;
use Laminas\I18n\Translator\TranslatorInterface;

/**
 * Message interface.
 */
interface MessageInterface extends JsonSerializable
{
    /**
     * Indicate if the message should be escaped for html.
     *
     * @return bool
     */
    public function escapeHtml();

    /**
     * Get the interpolated message
     */
    public function __toString();

    /**
     * Get the interpolated message, translated
     */
    public function translate(TranslatorInterface $translator, $textDomain = 'default', $locale = null);
}

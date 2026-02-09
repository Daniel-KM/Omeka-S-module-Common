<?php declare(strict_types=1);

/**
 * Polyfill for Omeka S < 4.2.
 *
 * This is a copy of the core class \Omeka\Stdlib\PsrInterpolateInterface.
 * It is loaded only when the core version is lower than 4.2, where this class
 * was introduced.
 *
 * @see \Omeka\Stdlib\PsrInterpolateInterface
 */

namespace Omeka\Stdlib;

/**
 * Interpolate a PSR-3 message with a context into a string.
 */
interface PsrInterpolateInterface
{
    /**
     * Interpolates context values into the PSR-3 message placeholders.
     *
     * Keys that are not stringable are kept as class or type.
     *
     * @param string $message Message with PSR-3 placeholders.
     * @param array $context Associative array with placeholders and strings.
     * @return string
     */
    public function interpolate($message, ?array $context = null): string;
}

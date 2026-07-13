<?php declare(strict_types=1);

namespace Common\Stdlib;

/**
 * Prepare and validate messages (mails, notifications) with placeholder
 * support, independently of the calling context (controller or view).
 *
 * @see \Common\Stdlib\MessagePreparerTrait
 */
interface MessagePreparerInterface
{
    /**
     * Default max length for message body.
     */
    const DEFAULT_BODY_MAX_LENGTH = 10000;

    /**
     * Default max length for message subject (RFC 2822 recommends 78).
     */
    const DEFAULT_SUBJECT_MAX_LENGTH = 78;

    /**
     * Absolute max length for message subject.
     */
    const MAX_SUBJECT_MAX_LENGTH = 190;

    /**
     * Validate a message body. Returns ['valid' => bool, 'error' => ?string].
     */
    public function validateBody(?string $body, int $maxLength = self::DEFAULT_BODY_MAX_LENGTH): array;

    /**
     * Validate a message subject. Returns ['valid' => bool, 'error' =>
     * ?string].
     */
    public function validateSubject(?string $subject, int $maxLength = self::DEFAULT_SUBJECT_MAX_LENGTH): array;

    /**
     * Register additional placeholders merged when filling messages.
     */
    public function addPlaceholders(array $placeholders): self;

    /**
     * Clear the additional placeholders.
     */
    public function clearPlaceholders(): self;

    /**
     * Get the common placeholders for the given context (site, user, owner,
     * resource).
     */
    public function getCommonPlaceholders(array $context = []): array;

    /**
     * Fill a message with placeholders (moustache style {name}).
     */
    public function fillMessage(?string $message, array $placeholders = [], array $context = []): string;

    /**
     * Get a subject from a setting with a default fallback, filled.
     */
    public function getDefaultSubject(
        string $settingKey,
        string $defaultMessage,
        array $placeholders = [],
        array $context = []
    ): string;

    /**
     * Add the current user to cc/bcc/reply-to lists from "myself" options.
     */
    public function processMyselfOptions(
        array $myselfOptions,
        $user,
        array &$cc,
        array &$bcc,
        array &$replyTo
    ): void;
}

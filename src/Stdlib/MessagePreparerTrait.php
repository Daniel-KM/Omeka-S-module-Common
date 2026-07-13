<?php declare(strict_types=1);

namespace Common\Stdlib;

use Laminas\Http\PhpEnvironment\RemoteAddress;

/**
 * Shared implementation of MessagePreparerInterface.
 *
 * The host (a controller plugin or a view helper) provides the api, mailer and
 * settings collaborators and the urlFromRoute() primitive, so the same logic
 * runs on both sides.
 *
 * @see \Common\Stdlib\MessagePreparerInterface
 */
trait MessagePreparerTrait
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Omeka\Stdlib\Mailer
     */
    protected $mailer;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var array
     */
    protected $additionalPlaceholders = [];

    /**
     * Assemble an absolute (or relative) url from a route, in the host context.
     */
    abstract protected function urlFromRoute(string $route, array $params = [], array $options = []): string;

    public function validateBody(?string $body, int $maxLength = MessagePreparerInterface::DEFAULT_BODY_MAX_LENGTH): array
    {
        $body = trim((string) $body);

        if (!strlen($body)) {
            return [
                'valid' => false,
                'error' => 'Empty message.', // @translate
            ];
        }

        if (mb_strlen($body) > $maxLength) {
            return [
                'valid' => false,
                'error' => 'Too long message.', // @translate
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    public function validateSubject(?string $subject, int $maxLength = MessagePreparerInterface::DEFAULT_SUBJECT_MAX_LENGTH): array
    {
        $subject = trim((string) $subject);

        // Enforce absolute maximum.
        $maxLength = min($maxLength, MessagePreparerInterface::MAX_SUBJECT_MAX_LENGTH);

        if (mb_strlen($subject) > $maxLength) {
            return [
                'valid' => false,
                'error' => 'Too long subject.', // @translate
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    public function addPlaceholders(array $placeholders): self
    {
        $this->additionalPlaceholders = array_merge($this->additionalPlaceholders, $placeholders);
        return $this;
    }

    public function clearPlaceholders(): self
    {
        $this->additionalPlaceholders = [];
        return $this;
    }

    public function getCommonPlaceholders(array $context = []): array
    {
        $placeholders = [];

        // IP address.
        $placeholders['ip'] = (new RemoteAddress())->getIpAddress();

        // Main installation info.
        $placeholders['main_title'] = $this->mailer->getInstallationTitle();
        $placeholders['main_url'] = $this->urlFromRoute('top', [], ['force_canonical' => true]);

        // Site info.
        if (!empty($context['site'])) {
            $site = $context['site'];
            $placeholders['site_title'] = $site->title();
            $placeholders['site_url'] = $site->siteUrl(null, true);
            $placeholders['site_slug'] = $site->slug();
        }

        // Current user info.
        if (!empty($context['user'])) {
            $user = $context['user'];
            $placeholders['user_name'] = $user->getName();
            $placeholders['user_email'] = $user->getEmail();
        }

        // Owner/recipient info.
        if (!empty($context['owner'])) {
            $owner = $context['owner'];
            $placeholders['owner_name'] = $owner->name();
            $placeholders['owner_email'] = $owner->email();
            // Aliases for compatibility.
            $placeholders['name'] = $owner->name();
            $placeholders['email'] = $owner->email();
        }

        // Resource info.
        if (!empty($context['resource'])) {
            $resource = $context['resource'];
            $placeholders['resource_id'] = $resource->id();
            $placeholders['resource_title'] = $resource->displayTitle();
            $placeholders['resource_url'] = $resource->siteUrl(null, true);
            $placeholders['resource_url_admin'] = $resource->adminUrl(null, true);
        }

        // Merge with additional placeholders registered by modules.
        $placeholders = array_merge($placeholders, $this->additionalPlaceholders);

        return $placeholders;
    }

    public function fillMessage(?string $message, array $placeholders = [], array $context = []): string
    {
        if (empty($message)) {
            return (string) $message;
        }

        // Fix url-encoded braces that may occur in some configs.
        // TODO Remove this fix (and in other places) earlier.
        $message = strtr($message, ['%7B' => '{', '%7D' => '}']);

        // Build complete placeholder list.
        $allPlaceholders = $this->getCommonPlaceholders($context);
        $allPlaceholders = array_merge($allPlaceholders, $placeholders);

        // Add resource property placeholders if a resource is in context.
        if (!empty($context['resource'])) {
            $propertyPlaceholders = $this->getResourcePropertyPlaceholders($message, $context['resource']);
            $allPlaceholders = array_merge($allPlaceholders, $propertyPlaceholders);
        }

        // Build replacement array with braces.
        $replace = [];
        foreach ($allPlaceholders as $key => $value) {
            // Only include scalar values.
            if (!is_array($value) && !is_object($value)) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        // Add empty defaults for common placeholders to avoid leftover braces.
        $defaultPlaceholders = [
            '{ip}' => '',
            '{main_title}' => '',
            '{main_url}' => '',
            '{site_title}' => '',
            '{site_url}' => '',
            '{site_slug}' => '',
            '{user_name}' => '',
            '{user_email}' => '',
            '{owner_name}' => '',
            '{owner_email}' => '',
            '{name}' => '',
            '{email}' => '',
            '{resource_id}' => '',
            '{resource_title}' => '',
            '{resource_url}' => '',
            '{resource_url_admin}' => '',
        ];
        $replace += $defaultPlaceholders;

        return strtr($message, $replace);
    }

    /**
     * Extract property term placeholders (prefix:localName) from the message
     * and return their values from the resource.
     */
    protected function getResourcePropertyPlaceholders(string $message, $resource): array
    {
        $placeholders = [];

        // Find all placeholders that look like property terms
        // (prefix:localName): exactly one colon, alphanumeric/underscore chars.
        $matches = [];
        if (!preg_match_all('/\{([a-zA-Z][a-zA-Z0-9]*:[a-zA-Z][a-zA-Z0-9_]*)\}/', $message, $matches)) {
            return $placeholders;
        }

        $terms = array_unique($matches[1]);
        foreach ($terms as $term) {
            $value = $resource->value($term);
            // Use the first value if available.
            $placeholders[$term] = $value ? (string) $value : '';
        }

        return $placeholders;
    }

    public function getDefaultSubject(
        string $settingKey,
        string $defaultMessage,
        array $placeholders = [],
        array $context = []
    ): string {
        $subject = $this->settings->get($settingKey);
        if (empty($subject)) {
            $subject = $defaultMessage;
        }
        return $this->fillMessage($subject, $placeholders, $context);
    }

    public function processMyselfOptions(
        array $myselfOptions,
        $user,
        array &$cc,
        array &$bcc,
        array &$replyTo
    ): void {
        if (!$user) {
            return;
        }

        $userEmail = $user->getEmail();
        $userName = $user->getName();

        if (in_array('cc', $myselfOptions)) {
            $cc[$userEmail] = $userName;
        }
        if (in_array('bcc', $myselfOptions)) {
            $bcc[$userEmail] = $userName;
        }
        if (in_array('reply', $myselfOptions)) {
            $replyTo[$userEmail] = $userName;
        }
    }
}

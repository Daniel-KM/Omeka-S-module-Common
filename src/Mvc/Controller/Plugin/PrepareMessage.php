<?php declare(strict_types=1);

namespace Common\Mvc\Controller\Plugin;

use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Mailer;

/**
 * Helper for sending messages with validation and placeholder support.
 *
 * This plugin provides common functionality for sending messages:
 * - Validation of message body and subject
 * - Filling messages with placeholders (moustache style)
 * - Extensible placeholder system
 *
 * Placeholders are filled using {placeholder_name} syntax.
 *
 * Common placeholders:
 * - {ip}: Client ip address
 * - {main_title}: Installation title
 * - {main_url}: Main url
 * - {site_title}: Current site title
 * - {site_url}: Current site url
 * - {user_name}: Current user name
 * - {user_email}: Current user email
 * - {email}: Recipient email
 * - {name}: Recipient name
 *
 * Resource property placeholders (when a resource is in context):
 * - {prefix:localName}: Value of the property (e.g. {dcterms:title}, {dcterms:creator})
 *
 * Module-specific placeholders can be added via addPlaceholders() or
 * by passing them to fillMessage().
 *
 * Adapted from various old versions of many modules, in particular:
 * @see \ContactUs\View\Helper\ContactUs::fillMessage()
 * @see \Contribute\Controller\Admin\ContributionController::fillMessage()
 * @see \Selection\Controller\Admin\SelectionController::fillMessage()
 */
class PrepareMessage extends AbstractPlugin
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
     * Additional placeholders that can be added by modules.
     *
     * @var array
     */
    protected $additionalPlaceholders = [];

    public function __construct(
        ApiManager $api,
        Mailer $mailer,
        Settings $settings
    ) {
        $this->api = $api;
        $this->mailer = $mailer;
        $this->settings = $settings;
    }

    /**
     * Validate message body.
     *
     * @param string $body The message body.
     * @param int $maxLength Maximum allowed length (default: 10000).
     * @return array Array with 'valid' (bool) and 'error' (string|null).
     */
    public function validateBody(?string $body, int $maxLength = self::DEFAULT_BODY_MAX_LENGTH): array
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

    /**
     * Validate message subject.
     *
     * @param string $subject The message subject.
     * @param int $maxLength Maximum allowed length (default: 78, max: 190).
     * @return array Array with 'valid' (bool) and 'error' (string|null).
     */
    public function validateSubject(?string $subject, int $maxLength = self::DEFAULT_SUBJECT_MAX_LENGTH): array
    {
        $subject = trim((string) $subject);

        // Enforce absolute maximum.
        $maxLength = min($maxLength, self::MAX_SUBJECT_MAX_LENGTH);

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

    /**
     * Add additional placeholders.
     *
     * These placeholders will be merged with the default ones when filling
     * messages. This allows modules to register their own placeholders.
     *
     * @param array $placeholders Key-value pairs of placeholder => value.
     */
    public function addPlaceholders(array $placeholders): self
    {
        $this->additionalPlaceholders = array_merge($this->additionalPlaceholders, $placeholders);
        return $this;
    }

    /**
     * Clear additional placeholders.
     */
    public function clearPlaceholders(): self
    {
        $this->additionalPlaceholders = [];
        return $this;
    }

    /**
     * Get common placeholders.
     *
     * @param array $context Optional context data:
     *   - site: \Omeka\Api\Representation\SiteRepresentation
     *   - user: \Omeka\Entity\User
     *   - owner: \Omeka\Api\Representation\UserRepresentation
     *   - resource: \Omeka\Api\Representation\AbstractResourceEntityRepresentation
     * @return array Key-value pairs of placeholder => value.
     */
    public function getCommonPlaceholders(array $context = []): array
    {
        $controller = $this->getController();
        $url = $controller->url();

        $placeholders = [];

        // IP address.
        $placeholders['ip'] = (new RemoteAddress())->getIpAddress();

        // Main installation info.
        $placeholders['main_title'] = $this->mailer->getInstallationTitle();
        $placeholders['main_url'] = $url->fromRoute('top', [], ['force_canonical' => true]);

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

    /**
     * Fill a message with placeholders (moustache style).
     *
     * Placeholders use the format {placeholder_name}.
     * Common placeholders are automatically added from getCommonPlaceholders().
     * Additional placeholders can be passed or registered via addPlaceholders().
     * Resource property placeholders like {dcterms:title} are supported when a
     * resource is provided in context.
     *
     * @param string|null $message The message template.
     * @param array $placeholders Additional placeholders to merge.
     * @param array $context Context for common placeholders (site, user, etc.).
     * @return string The filled message.
     */
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
     * Get resource property placeholders from the message.
     *
     * Extracts placeholders that look like property terms (prefix:localName)
     * and returns their values from the resource.
     *
     * @param string $message The message template.
     * @param \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
     * @return array Key-value pairs of property term => value.
     */
    protected function getResourcePropertyPlaceholders(string $message, $resource): array
    {
        $placeholders = [];

        // Find all placeholders that look like property terms (prefix:localName).
        // Property terms contain exactly one colon and alphanumeric/underscore chars.
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

    /**
     * Get default subject from settings with fallback.
     *
     * @param string $settingKey The setting key to look up.
     * @param string $defaultMessage Default message if setting not found.
     * @param array $placeholders Placeholders to fill in the subject.
     * @param array $context Context for common placeholders.
     * @return string The subject.
     */
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

    /**
     * Prepare "myself" options for cc/bcc/reply-to.
     *
     * Processes the "myself" checkbox values and adds the current user
     * to the appropriate email lists.
     *
     * @param array $myselfOptions Values from "myself" checkbox (e.g., ['cc', 'bcc']).
     * @param \Omeka\Entity\User $user Current user.
     * @param array $cc Existing cc list (modified by reference).
     * @param array $bcc Existing bcc list (modified by reference).
     * @param array $replyTo Existing Reply-To list (modified by reference).
     */
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

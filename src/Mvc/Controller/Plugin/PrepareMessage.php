<?php declare(strict_types=1);

namespace Common\Mvc\Controller\Plugin;

use Common\Stdlib\MessagePreparerInterface;
use Common\Stdlib\MessagePreparerTrait;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Mailer;

/**
 * Prepare and validate messages with placeholder support, from a controller.
 *
 * The shared logic lives in MessagePreparerTrait, so the same features are
 * available from a view via the "prepareMessage" view helper.
 *
 * Placeholders use the {placeholder_name} syntax. Common placeholders: {ip},
 * {main_title}, {main_url}, {site_title}, {site_url}, {user_name},
 * {user_email}, {email}, {name}, and resource property terms {prefix:localName}
 * when a resource is in context.
 *
 * Adapted from various old versions of many modules, in particular:
 * @see \ContactUs\View\Helper\ContactUs::fillMessage()
 * @see \Contribute\Controller\Admin\ContributionController::fillMessage()
 * @see \Selection\Controller\Admin\SelectionController::fillMessage()
 */
class PrepareMessage extends AbstractPlugin implements MessagePreparerInterface
{
    use MessagePreparerTrait;

    public function __construct(
        ApiManager $api,
        Mailer $mailer,
        Settings $settings
    ) {
        $this->api = $api;
        $this->mailer = $mailer;
        $this->settings = $settings;
    }

    protected function urlFromRoute(string $route, array $params = [], array $options = []): string
    {
        return $this->getController()->url()->fromRoute($route, $params, $options);
    }
}

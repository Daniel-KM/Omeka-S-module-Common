<?php declare(strict_types=1);

namespace Common\View\Helper;

use Common\Stdlib\MessagePreparerInterface;
use Common\Stdlib\MessagePreparerTrait;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Manager as ApiManager;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Mailer;

/**
 * Prepare and validate messages with placeholder support, from a view.
 *
 * View-side counterpart of the "prepareMessage" controller plugin; both share
 * MessagePreparerTrait.
 *
 * @see \Common\Mvc\Controller\Plugin\PrepareMessage
 */
class PrepareMessage extends AbstractHelper implements MessagePreparerInterface
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

    /**
     * Return the helper itself so it can be used fluently:
     * $this->prepareMessage().
     */
    public function __invoke(): self
    {
        return $this;
    }

    protected function urlFromRoute(string $route, array $params = [], array $options = []): string
    {
        return $this->getView()->plugin('url')->__invoke($route, $params, $options);
    }
}

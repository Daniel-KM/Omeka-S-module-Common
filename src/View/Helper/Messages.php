<?php declare(strict_types=1);

namespace Common\View\Helper;

use Laminas\I18n\Translator\TranslatorAwareInterface;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;

/**
 * View helper for proxing the messenger controller plugin.
 *
 * Replace Omeka core Messages in order to manage PsrMessage and translations.
 * @see \Omeka\View\Helper\Messages
 *
 * @todo Move PsrMessage in core (pr #1372).
 */
class Messages extends AbstractHelper
{
    /**
     * Get all messages and clear them from the session.
     *
     * @return array
     */
    public function get()
    {
        // Messenger can be used directly, it is used only to get messages.
        $messenger = new Messenger;
        $messages = $messenger->get();
        $messenger->clear();
        return $messages;
    }

    /**
     * Get all messages translated and clear them from the session.
     *
     * This function may be used for example to output messages for json.
     *
     * @return array
     */
    public function getTranslatedMessages(): array
    {
        $allMessages = $this->get();
        if (!count($allMessages)) {
            return [];
        }

        $view = $this->getView();
        $translate = $view->plugin('translate');
        $translator = $translate->getTranslator();

        $typesToClasses = [
            Messenger::ERROR => 'error',
            Messenger::SUCCESS => 'success',
            Messenger::WARNING => 'warning',
            Messenger::NOTICE => 'notice',
        ];

        // Most of the time, the messages are a unique and simple string.
        $output = [];
        foreach ($allMessages as $type => $messages) {
            $class = $typesToClasses[$type] ?? 'notice';
            foreach ($messages as $message) {
                $output[$class][] = $message instanceof TranslatorAwareInterface
                    ? $message->setTranslator($translator)->translate()
                    : $translate($message);
            }
        }

        return $output;
    }

    /**
     * Render the messages.
     *
     * @return string
     */
    public function __invoke()
    {
        $allMessages = $this->get();
        if (!count($allMessages)) {
            return '';
        }

        $view = $this->getView();
        $escape = $view->plugin('escapeHtml');
        $translate = $view->plugin('translate');
        $translator = $translate->getTranslator();

        $typesToClasses = [
            Messenger::ERROR => 'error',
            Messenger::SUCCESS => 'success',
            Messenger::WARNING => 'warning',
            Messenger::NOTICE => 'notice',
        ];

        // Most of the time, the messages are a unique and simple string.
        $output = '<ul class="messages">';
        foreach ($allMessages as $type => $messages) {
            $class = $typesToClasses[$type] ?? 'notice';
            foreach ($messages as $message) {
                // Escape string by default.
                // "instanceof PsrMessage" cannot be used, since it can be another
                // object (Common PsrMessage or old modules), as long as it's not
                // in the core.
                if ($message instanceof TranslatorAwareInterface) {
                    $escapeHtml = $message->getEscapeHtml();
                    $message = $message->setTranslator($translator)->translate();
                } elseif ($message instanceof Message) {
                    $escapeHtml = $message->escapeHtml();
                    $message = $translate($message);
                } else {
                    $escapeHtml = true;
                    $message = $translate($message);
                }
                if ($escapeHtml) {
                    $message = $escape($message);
                }
                $output .= sprintf('<li class="%s">%s</li>', $class, $message);
            }
        }
        $output .= '</ul>';

        return $output;
    }
}

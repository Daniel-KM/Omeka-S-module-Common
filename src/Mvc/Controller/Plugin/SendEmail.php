<?php declare(strict_types=1);

namespace Common\Mvc\Controller\Plugin;

use Laminas\Log\Logger;
use Laminas\Mail\Address\AddressInterface;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part as MimePart;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Mailer;

class SendEmail extends AbstractPlugin
{
    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Stdlib\Mailer
     */
    protected $mailer;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    public function __construct(Logger $logger, Mailer $mailer, Settings $settings)
    {
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->settings = $settings;
    }

    /**
     * Send an email, with some checks. The mail may be raw or basic html.
     *
     * All arguments are optional, except the body.
     * The default subject is the installation title, defined in main settings.
     *
     * Admin email is the default sender in Omeka Mailer, but it can be replaced
     * by a no-reply sender via module EasyAdmin.
     * For sender, don't use the user mail: the server won't be able to send
     * it or the email will be identified as a spam or rejected.
     *
     * The default recipient is the no-reply email set in module EasyAdmin, else
     * the Omeka administrator email. It is set only when $to is null.
     *
     * The format of each address (sender, recipients, etc.) can be:
     * - email
     * - name <email>
     * - [email]
     * - [email => name]
     * - \Laminas\Mail\Address\AddressInterface
     *
     * @see \Laminas\Mail\Address\AddressInterface
     *
     * @todo Check if the format "name <email>" can be used, in particular when (string) null is used.
     */
    public function __invoke(
        string $body,
        $subject = null,
        $to = null,
        $from = null,
        $cc = null,
        $bcc = null,
        $replyTo = null
    ): bool {
        /**
         * The method $mailer->createMessage() does not allow to by-pass default
         * options, in particular "from".
         * @see \Omeka\Service\MailerFactory::__invoke()
         *
         * @var \Omeka\Stdlib\Mailer $mailer
         */

        $body = trim((string) $body);
        if (!strlen($body)) {
            $this->logger->err(
                'Email not sent: the content is missing (subject: {subject}).', // @translate
                ['subject' => $subject]
            );
            return false;
        }

        // Pass null to use the default recipient.
        if ((is_string($to) && !strlen($to))
            || (is_array($to) && !count($to))
            || ($to instanceof AddressInterface && !$to->getEmail())
        ) {
            $this->logger->err(
                'Email not sent: there is no recipient (subject: {subject}).', // @translate
                ['subject' => $subject]
            );
            return false;
        }

        // With name null, laminas will try to extract the name from the email,
        // but will keep it empty if it is an empty string.
        // To use the empty string for name is quicker, because the email won't
        // be parsed.

        $fromEmail = null;
        $fromName = null;
        if ($from) {
            if (is_string($from)) {
                // This "from" will be parsed.
                $fromEmail = $from;
                $fromName = null;
            } elseif (is_array($from)) {
                $fromName = reset($from);
                $fromEmail = key($from);
                if (is_numeric($fromEmail)) {
                    $fromEmail = $fromName;
                    $fromName = null;
                }
            } elseif ($from instanceof AddressInterface) {
                $fromEmail = $from->getEmail();
                $fromName = $from->getName() ?: '';
            } else {
                $this->logger->err('Email not sent: the sender "{sender}" is not valid (subject: {subject}).', // @translate
                    ['sender' => $from, 'subject' => $subject]
                );
                return false;
            }
            if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                $this->logger->err(
                    'Email not sent: the sender email "{sender}" is invalid (subject: {subject}).', // @translate
                    ['sender' => $from, 'subject' => $subject]
                );
                return false;
            }
        }

        $adminEmail = $this->settings->get('administrator_email');
        $adminName = $this->settings->get('administrator_name')
            ?: $this->settings->get('easyadmin_administrator_name')
            ?: '';

        if (!$fromEmail) {
            $fromEmail = $this->settings->get('easyadmin_no_reply_email');
            if ($fromEmail) {
                $fromName = $this->settings->get('easyadmin_no_reply_name') ?: '';
            } else {
                $fromEmail = $adminEmail;
                $fromName = $adminName;
            }
        }

        // Set the admin as default recipient.
        $to ??= [$adminEmail => $adminName];

        // Manage more options.
        $subject = trim((string) $subject) ?: $this->mailer->getInstallationTitle();

        // Manage html message if any.
        // Many html body are not ending with a ">", so check only initial "<".
        if (mb_substr($body, 0, 1) === '<') {
            $body = (new MimeMessage())->addPart((new MimePart($body))->setCharset('UTF-8')->setType('text/html'));
        }

        $message = $this->mailer->createMessage();
        $message
            ->setFrom($fromEmail, $fromName)
            ->setTo($to)
            ->setSubject($subject)
            ->setBody($body);

        if ($cc) {
            try {
                $message->addCc($cc);
            } catch (\Exception $e) {
                $this->logger->err(
                    'Email not sent: the cc emails {list} are invalid (subject: {subject}).', // @translate
                    ['list' => json_encode($cc, 320), 'subject' => $subject]
                );
                return false;
            }
        }

        if ($bcc) {
            try {
                $message->addBcc($bcc);
            } catch (\Exception $e) {
                $this->logger->err(
                    'Email not sent: the bcc emails {list} are invalid (subject: {subject}).', // @translate
                    ['list' => json_encode($bcc, 320), 'subject' => $subject]
                );
                return false;
            }
        }

        if ($replyTo) {
            try {
                $message->addReplyTo($replyTo);
            } catch (\Exception $e) {
                $this->logger->err(
                    'Email not sent: the reply-to emails {list} are invalid (subject: {subject}).', // @translate
                    ['list' => json_encode($replyTo, 320), 'subject' => $subject]
                );
                return false;
            }
        }

        try {
            $this->mailer->send($message);
        } catch (\Exception $e) {
            $this->logger->err((string) $e);
            return false;
        }

        // Log any email sent for security purpose.
        $this->logger->info(
            'An email was sent to {recipients} with subject: {subject}', // @translate
            ['recipients' => json_encode($to, 320), 'subject' => $subject]
        );

        return true;
    }
}

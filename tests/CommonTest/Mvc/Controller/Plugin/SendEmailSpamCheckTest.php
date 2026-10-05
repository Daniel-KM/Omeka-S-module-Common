<?php declare(strict_types=1);

namespace CommonTest\Mvc\Controller\Plugin;

use Common\Mvc\Controller\Plugin\SendEmail;
use Laminas\Log\Logger;
use Laminas\Mail\Message;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Mailer;
use PHPUnit\Framework\TestCase;

/**
 * Tests the check of the spam keywords before sending an email, and its skip
 * on an explicit decision of an admin.
 */
class SendEmailSpamCheckTest extends TestCase
{
    /**
     * @var int Number of emails actually sent.
     */
    protected $sent = 0;

    protected function sendEmail(): SendEmail
    {
        $mailer = $this->createMock(Mailer::class);
        $mailer->method('createMessage')->willReturn(new Message());
        $mailer->method('getInstallationTitle')->willReturn('Test');
        $mailer->method('send')->willReturnCallback(function () {
            ++$this->sent;
        });
        $settings = $this->createMock(Settings::class);
        $settings->method('get')->willReturnCallback(fn ($name) => $name === 'administrator_email' ? 'admin@example.org' : null);
        return new SendEmail($this->createMock(Logger::class), $mailer, $settings);
    }

    public function testSpamIsNotSent(): void
    {
        $result = $this->sendEmail()('Buy viagra now', 'Subject', 'to@example.org', 'from@example.org');
        // The spammer is told that the email was sent.
        $this->assertTrue($result);
        $this->assertSame(0, $this->sent);
    }

    public function testLegitimateEmailIsSent(): void
    {
        $this->assertTrue($this->sendEmail()('Hello, a question.', 'Subject', 'to@example.org', 'from@example.org'));
        $this->assertSame(1, $this->sent);
    }

    /**
     * An email resent on the decision of an admin skips the check, else a
     * false positive would be blocked again.
     */
    public function testCheckCanBeSkipped(): void
    {
        $this->assertTrue($this->sendEmail()('Buy viagra now', 'Subject', 'to@example.org', 'from@example.org', null, null, null, false));
        $this->assertSame(1, $this->sent);
    }
}

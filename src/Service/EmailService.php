<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Resend;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(
        private readonly ?string $resendApiKey,
        private readonly string $fromEmail,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendMagicLinkEmail(string $toEmail, string $userName, string $magicLink, string $emailHtml): bool
    {
        try {
            if (!empty($this->resendApiKey)) {
                $resend = Resend::client($this->resendApiKey);
                $resend->emails->send([
                    'from' => $this->fromEmail,
                    'to' => [$toEmail],
                    'subject' => 'Your Workoflow Login Link',
                    'html' => $emailHtml,
                ]);
            } else {
                $email = (new Email())
                    ->from($this->fromEmail)
                    ->to($toEmail)
                    ->subject('Your Workoflow Login Link')
                    ->html($emailHtml);

                $this->mailer->send($email);
            }

            $this->logger->info('Magic link email sent', ['to' => $toEmail, 'user' => $userName]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send magic link email', [
                'to' => $toEmail,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

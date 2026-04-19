<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Resend;

class ResendEmailService
{
    private string $resendApiKey;
    private string $fromEmail;
    private LoggerInterface $logger;

    public function __construct(
        string $resendApiKey,
        string $fromEmail,
        LoggerInterface $logger,
    ) {
        $this->resendApiKey = $resendApiKey;
        $this->fromEmail = $fromEmail;
        $this->logger = $logger;
    }

    public function sendMagicLinkEmail(string $toEmail, string $userName, string $magicLink, string $emailHtml): bool
    {
        try {
            $resend = Resend::client($this->resendApiKey);

            $resend->emails->send([
                'from' => $this->fromEmail,
                'to' => [$toEmail],
                'subject' => 'Your Workoflow Login Link',
                'html' => $emailHtml,
            ]);

            $this->logger->info('Magic link email sent', [
                'to' => $toEmail,
                'user' => $userName,
            ]);

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

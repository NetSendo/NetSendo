<?php

namespace App\Services\Mail\Providers;

use App\Services\Mail\MailProviderInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Exception;

class SmtpProvider implements MailProviderInterface
{
    private Mailer $mailer;
    private string $fromEmail;
    private string $fromName;

    public function __construct(
        private string $host,
        private int $port,
        private string $encryption,
        private string $username,
        private string $password,
        string $fromEmail,
        string $fromName,
        private ?string $replyTo = null
    ) {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->initializeMailer();
    }

    private function initializeMailer(): void
    {
        $dsn = $this->buildDsn();
        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
    }

    private function buildDsn(): string
    {
        $scheme = match ($this->encryption) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            'none' => 'smtp',
            default => 'smtp',
        };

        $encodedUsername = urlencode($this->username);
        $encodedPassword = urlencode($this->password);

        $dsn = "{$scheme}://{$encodedUsername}:{$encodedPassword}@{$this->host}:{$this->port}";

        // Add TLS verification for non-SSL connections
        if ($this->encryption === 'tls') {
            $dsn .= '?encryption=tls&auth_mode=login';
        } elseif ($this->encryption === 'none') {
            $dsn .= '?verify_peer=0';
        }

        return $dsn;
    }

    public function send(string $to, string $toName, string $subject, string $htmlContent): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName));
            
            if ($this->replyTo) {
                $email->replyTo($this->replyTo);
            }

            $email->to(new Address($to, $toName))
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);
            return true;
        } catch (Exception $e) {
            \Log::error("SmtpProvider send failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function testConnection(?string $toEmail = null): array
    {
        try {
            // Try to send a test email to verify connection
            // We'll just initialize the transport to test credentials
            $dsn = $this->buildDsn();
            $transport = Transport::fromDsn($dsn);
            
            // Create a test email (won't actually send)
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName));

            if ($this->replyTo) {
                $email->replyTo($this->replyTo);
            }

            $recipient = $toEmail ?? $this->fromEmail;
            $email->to(new Address($recipient, 'Test'))
                ->subject('Connection Test')
                ->text('This is a connection test.');

            // Try to send - if credentials are wrong, this will fail
            $mailer = new Mailer($transport);
            $mailer->send($email);

            return [
                'success' => true,
                'message' => 'Connection successful! Test email sent.',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    public function getProviderName(): string
    {
        return 'SMTP';
    }
}

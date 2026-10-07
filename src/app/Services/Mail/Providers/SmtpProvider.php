<?php

namespace App\Services\Mail\Providers;

use App\Helpers\EmailHtmlDocument;
use App\Helpers\EmailPlainText;
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

    public function send(string $to, string $toName, string $subject, string $htmlContent, array $headers = [], array $attachments = [], ?string $textContent = null, ?bool $trackingEnabled = null): bool
    {
        // Ensure a valid HTML document structure (issue #22 — HTML_MIME_NO_HTML_TAG).
        $htmlContent = EmailHtmlDocument::wrap($htmlContent, $subject);
        $textContent = EmailPlainText::forEmail($textContent, $htmlContent);

        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName));

            if ($this->replyTo) {
                $email->replyTo($this->replyTo);
            }

            // Set Return-Path for bounce routing. It is a path header, not a
            // text header: added as text, Symfony throws and the send fails.
            if (!empty($headers['Return-Path'])) {
                $email->returnPath($headers['Return-Path']);
            }
            unset($headers['Return-Path']);

            // SendGrid's SMTP relay tracks clicks and opens on its own; an
            // untracked message switches that off through X-SMTPAPI.
            if ($trackingEnabled === false && $this->isSendGridRelay() && !isset($headers['X-SMTPAPI'])) {
                $headers['X-SMTPAPI'] = json_encode(['filters' => [
                    'clicktrack' => ['settings' => ['enable' => 0, 'enable_text' => 0]],
                    'opentrack' => ['settings' => ['enable' => 0]],
                ]]);
            }

            // Add custom headers
            foreach ($headers as $name => $value) {
                $email->getHeaders()->addTextHeader($name, $value);
            }

            $email->to(new Address($to, $toName))
                ->subject($subject)
                ->html($htmlContent);

            if ($textContent !== null) {
                $email->text($textContent);
            }

            // Add attachments
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $email->attachFromPath(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path']),
                        $attachment['mime_type'] ?? 'application/pdf'
                    );
                }
            }

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

    private function isSendGridRelay(): bool
    {
        $host = strtolower($this->host);

        return $host === 'sendgrid.net' || str_ends_with($host, '.sendgrid.net');
    }
}

<?php

namespace App\Services\Mail\Providers;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Helpers\EmailHtmlDocument;
use App\Helpers\EmailPlainText;
use App\Services\Mail\MailProviderInterface;
use App\Services\Nmi\DkimKeyManager;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * NetSendo Mail Infrastructure (NMI) Provider
 *
 * Sends emails through the built-in Haraka MTA with dedicated IP support,
 * DKIM signing, and warming schedule enforcement.
 */
class NmiProvider implements MailProviderInterface
{
    private Mailer $mailer;
    private ?DkimSigner $dkimSigner = null;

    public function __construct(
        private DomainConfiguration $domain,
        private ?DedicatedIpAddress $dedicatedIp,
        private string $fromEmail,
        private string $fromName,
        private ?string $replyTo = null,
        ?TransportInterface $transport = null
    ) {
        // A transport can be passed in (tests); otherwise the MTA container
        $this->mailer = new Mailer($transport ?? self::createTransport($this->domain));
        $this->initializeDkimSigner();
    }

    /**
     * SMTP connection to the NMI MTA (Haraka), logged in with the domain's NMI credentials.
     * Built directly rather than from a DSN, so a password with characters such as @ : / ?
     * cannot break it.
     */
    public static function createTransport(DomainConfiguration $domain): EsmtpTransport
    {
        $transport = new EsmtpTransport(
            (string) config('nmi.mta_host', 'netsendo-mta'),
            (int) config('nmi.mta_port', 25)
        );

        if ($domain->nmi_smtp_username) {
            $transport->setUsername($domain->nmi_smtp_username);
            $transport->setPassword((string) $domain->getDecryptedNmiPassword());
        }

        return $transport;
    }

    /**
     * Initialize DKIM signer if keys are available
     */
    private function initializeDkimSigner(): void
    {
        if (!$this->dedicatedIp?->dkim_private_key || !$this->dedicatedIp?->dkim_selector) {
            return;
        }

        try {
            $privateKey = $this->dedicatedIp->getDecryptedDkimPrivateKey();
            if (!$privateKey) {
                return;
            }

            $this->dkimSigner = new DkimSigner(
                $privateKey,
                $this->domain->domain,
                $this->dedicatedIp->dkim_selector
            );
        } catch (Exception $e) {
            Log::warning('Failed to initialize DKIM signer', [
                'domain' => $this->domain->domain,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if we can send (respects warming limits)
     */
    public function canSend(): bool
    {
        if (!$this->dedicatedIp) {
            return true; // Use shared pool
        }

        return $this->dedicatedIp->canSendMore();
    }

    /**
     * Get remaining daily capacity
     */
    public function getRemainingCapacity(): int
    {
        if (!$this->dedicatedIp) {
            return PHP_INT_MAX;
        }

        return max(0, $this->dedicatedIp->getCurrentWarmingLimit() - $this->dedicatedIp->sent_today);
    }

    public function send(string $to, string $toName, string $subject, string $htmlContent, array $headers = [], array $attachments = [], ?string $textContent = null, ?bool $trackingEnabled = null): bool
    {
        // Ensure a valid HTML document structure (issue #22 — HTML_MIME_NO_HTML_TAG).
        $htmlContent = EmailHtmlDocument::wrap($htmlContent, $subject);
        $textContent = EmailPlainText::forEmail($textContent, $htmlContent);

        // Check warming limits
        if (!$this->canSend()) {
            throw new Exception('Daily warming limit reached for this IP');
        }

        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName));

            if ($this->replyTo) {
                $email->replyTo($this->replyTo);
            }

            // Set Return-Path for bounce routing
            if (!empty($headers['Return-Path'])) {
                $email->returnPath($headers['Return-Path']);
                unset($headers['Return-Path']);
            }

            // Add NMI-specific headers
            $headers['X-NMI-Domain'] = $this->domain->domain;
            if ($this->dedicatedIp) {
                $headers['X-NMI-IP'] = $this->dedicatedIp->ip_address;
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

            // Sign with DKIM if available
            if ($this->dkimSigner) {
                $email = $this->dkimSigner->sign($email);
            }

            $this->mailer->send($email);

            // Track sending
            if ($this->dedicatedIp) {
                $this->dedicatedIp->incrementSentCount(true);
            }

            Log::debug('NMI email sent', [
                'to' => $to,
                'domain' => $this->domain->domain,
                'ip' => $this->dedicatedIp?->ip_address,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error("NmiProvider send failed: " . $e->getMessage(), [
                'domain' => $this->domain->domain,
                'ip' => $this->dedicatedIp?->ip_address,
            ]);
            throw $e;
        }
    }

    public function testConnection(?string $toEmail = null): array
    {
        try {
            // Check if domain is properly configured
            if (!$this->domain->cname_verified) {
                return [
                    'success' => false,
                    'message' => __('mailboxes.nmi.test.not_verified', ['domain' => $this->domain->domain]),
                ];
            }

            // NMI switched on for the domain, with its SMTP login on the MTA
            if (!$this->domain->nmi_enabled) {
                return [
                    'success' => false,
                    'message' => __('mailboxes.nmi.test.not_enabled', ['domain' => $this->domain->domain]),
                ];
            }

            if (!$this->domain->hasNmiCredentials()) {
                return [
                    'success' => false,
                    'message' => __('mailboxes.nmi.test.no_credentials', ['domain' => $this->domain->domain]),
                ];
            }

            // Check warming status if dedicated IP
            if ($this->dedicatedIp && !$this->canSend()) {
                return [
                    'success' => false,
                    'message' => __('mailboxes.nmi.test.warming_limit'),
                ];
            }

            // Try to send a test email
            $recipient = $toEmail ?? $this->fromEmail;

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($recipient, 'Test'))
                ->subject('NMI Connection Test')
                ->text('This is a connection test from NetSendo Mail Infrastructure.');

            if ($this->dkimSigner) {
                $email = $this->dkimSigner->sign($email);
            }

            $this->mailer->send($email);

            return [
                'success' => true,
                'message' => __('mailboxes.nmi.test.success'),
                'details' => [
                    'domain' => $this->domain->domain,
                    'ip' => $this->dedicatedIp?->ip_address ?? 'Shared Pool',
                    'dkim_enabled' => $this->dkimSigner !== null,
                    'warming_status' => $this->dedicatedIp?->warming_status ?? 'N/A',
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => __('mailboxes.nmi.test.failed', ['error' => $e->getMessage()]),
            ];
        }
    }

    public function getProviderName(): string
    {
        return 'NetSendo Mail Infrastructure';
    }

    /**
     * Get provider status for dashboard
     */
    public function getStatus(): array
    {
        return [
            'provider' => $this->getProviderName(),
            'domain' => $this->domain->domain,
            'dedicated_ip' => $this->dedicatedIp?->ip_address,
            'warming_status' => $this->dedicatedIp?->warming_status ?? 'using_shared_pool',
            'daily_limit' => $this->dedicatedIp?->getCurrentWarmingLimit() ?? null,
            'sent_today' => $this->dedicatedIp?->sent_today ?? 0,
            'remaining' => $this->getRemainingCapacity(),
            'dkim_enabled' => $this->dkimSigner !== null,
            'reputation_score' => $this->dedicatedIp?->reputation_score ?? 100,
            'blacklisted' => $this->dedicatedIp?->isBlacklisted() ?? false,
        ];
    }
}

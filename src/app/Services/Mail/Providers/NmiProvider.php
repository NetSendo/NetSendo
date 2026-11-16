<?php

namespace App\Services\Mail\Providers;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Helpers\EmailHtmlDocument;
use App\Helpers\EmailPlainText;
use App\Services\Mail\MailProviderInterface;
use App\Services\Nmi\MtaStatusService;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
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
        private ?string $replyTo = null
    ) {
        $this->initializeMailer();
        $this->initializeDkimSigner();
    }

    /**
     * Initialize the mailer with NMI MTA connection
     */
    private function initializeMailer(): void
    {
        $host = (string) config('nmi.mta_host', 'netsendo-mta');
        $port = (int) config('nmi.mta_port', 25);
        $encryption = strtolower((string) config('nmi.mta_encryption', 'none'));

        // 'ssl' = TLS from the first byte (port 465). Otherwise the session starts in
        // clear text: 'tls' insists on STARTTLS, 'none' never upgrades (the MTA sits on
        // NetSendo's own Docker network and has no certificate by default).
        $transport = new EsmtpTransport($host, $port, $encryption === 'ssl');

        if ($encryption === 'tls') {
            $transport->setRequireTls(true);
        } elseif ($encryption !== 'ssl') {
            $transport->setAutoTls(false);
        }

        $transport->setUsername((string) $this->domain->nmi_smtp_username);
        $transport->setPassword((string) $this->domain->getDecryptedNmiPassword());

        $this->mailer = new Mailer($transport);
    }

    /**
     * Headers that tell the MTA where a message leaves from (docker/haraka/plugins/
     * netsendo_outbound.js). The MTA removes them before delivery, so they must not be
     * covered by the DKIM signature.
     */
    private const MTA_HEADERS = ['X-NMI-Domain', 'X-NMI-IP', 'X-NMI-Helo'];

    /**
     * Which key signs the mail: 'domain', 'ip' or null (unsigned)
     */
    private ?string $dkimSource = null;

    /**
     * Initialize the DKIM signer.
     *
     * The domain's own key signs once its DNS record has been verified. Without one, the
     * key of the dedicated IP is used, provided the IP is assigned to this domain (its
     * record is published there). Otherwise the mail goes out unsigned.
     */
    private function initializeDkimSigner(): void
    {
        if ($this->domain->nmiDkimReady()) {
            $this->useDkimKey($this->domain->getDecryptedNmiDkimPrivateKey(), $this->domain->nmi_dkim_selector, 'domain');
        }

        if ($this->dkimSigner !== null) {
            return;
        }

        if (!$this->dedicatedIp?->dkim_private_key || !$this->dedicatedIp?->dkim_selector) {
            return;
        }

        // The IP's DKIM record is published under the domain the IP is assigned to:
        // a signature for another domain would point at a record that does not exist
        if ((int) $this->dedicatedIp->domain_configuration_id !== (int) $this->domain->id) {
            return;
        }

        $this->useDkimKey($this->dedicatedIp->getDecryptedDkimPrivateKey(), $this->dedicatedIp->dkim_selector, 'ip');
    }

    private function useDkimKey(?string $privateKey, string $selector, string $source): void
    {
        if (!$privateKey) {
            return;
        }

        try {
            $this->dkimSigner = new DkimSigner(
                $privateKey,
                $this->domain->domain,
                $selector,
                ['headers_to_ignore' => self::MTA_HEADERS]
            );
            $this->dkimSource = $source;
        } catch (\Throwable $e) {
            Log::warning('Failed to initialize DKIM signer', [
                'domain' => $this->domain->domain,
                'key' => $source,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The internal headers of a message: the sending domain and, with a dedicated IP,
     * the address the MTA must send from and the HELO name that belongs to it.
     */
    private function mtaHeaders(): array
    {
        $headers = ['X-NMI-Domain' => $this->domain->domain];

        if ($this->dedicatedIp) {
            $headers['X-NMI-IP'] = $this->dedicatedIp->ip_address;

            $hostname = strtolower(trim((string) $this->dedicatedIp->hostname));
            if ($hostname !== '' && filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && str_contains($hostname, '.')) {
                $headers['X-NMI-Helo'] = $hostname;
            }
        }

        return $headers;
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

            // Where the MTA sends this message from (removed by the MTA before delivery)
            $headers = array_merge($headers, $this->mtaHeaders());

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
                    'message' => 'Domain not verified. Please configure CNAME record first.',
                ];
            }

            // Check NMI credentials
            if (!$this->domain->hasNmiCredentials()) {
                return [
                    'success' => false,
                    'message' => 'NMI credentials not configured for this domain.',
                ];
            }

            // Check warming status if dedicated IP
            if ($this->dedicatedIp && !$this->canSend()) {
                return [
                    'success' => false,
                    'message' => 'Daily warming limit reached. Please wait until tomorrow.',
                ];
            }

            // Try to send a test email
            $recipient = $toEmail ?? $this->fromEmail;

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($recipient, 'Test'))
                ->subject('NMI Connection Test')
                ->text('This is a connection test from NetSendo Mail Infrastructure.');

            foreach ($this->mtaHeaders() as $name => $value) {
                $email->getHeaders()->addTextHeader($name, $value);
            }

            if ($this->dkimSigner) {
                $email = $this->dkimSigner->sign($email);
            }

            $this->mailer->send($email);

            // What the person should know about how this mail left: unsigned, or not
            // from the dedicated IP they chose
            $notes = [];
            $sentFromIp = $this->dedicatedIp
                ? app(MtaStatusService::class)->canSendFrom($this->dedicatedIp->ip_address)
                : null;

            if ($sentFromIp === false) {
                $notes[] = __('mailboxes.nmi.test.ip_not_on_mta', ['ip' => $this->dedicatedIp->ip_address]);
            }
            if ($this->dkimSigner === null) {
                $notes[] = __('mailboxes.nmi.test.unsigned');
            }

            return [
                'success' => true,
                'message' => trim('Connection successful! Test email sent via NMI. ' . implode(' ', $notes)),
                'details' => [
                    'domain' => $this->domain->domain,
                    'ip' => $this->dedicatedIp?->ip_address ?? 'Shared Pool',
                    'sent_from_dedicated_ip' => $sentFromIp,
                    'dkim_enabled' => $this->dkimSigner !== null,
                    'dkim_key' => $this->dkimSource,
                    'warming_status' => $this->dedicatedIp?->warming_status ?? 'N/A',
                ],
            ];
        } catch (Exception $e) {
            $message = 'Connection failed: ' . $e->getMessage();

            // 535 or "no authenticator": the MTA runs but has not read this domain's login
            if (preg_match('/\b535\b|authenticat/i', $e->getMessage())) {
                $message .= ' ' . __('mailboxes.nmi.errors.mta_login_refused');
            }

            return [
                'success' => false,
                'message' => $message,
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
            'dkim_key' => $this->dkimSource,
            'reputation_score' => $this->dedicatedIp?->reputation_score ?? 100,
            'blacklisted' => $this->dedicatedIp?->isBlacklisted() ?? false,
        ];
    }
}

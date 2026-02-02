<?php

namespace App\Services\Mail;

use App\Models\ContactList;
use App\Models\Mailbox;
use App\Models\Webinar;
use App\Services\Mail\Providers\GmailProvider;
use App\Services\Mail\Providers\NmiProvider;
use App\Services\Mail\Providers\SendGridProvider;
use App\Services\Mail\Providers\SmtpProvider;
use App\Services\Mail\GmailOAuthService;
use InvalidArgumentException;

class MailProviderService
{
    public function __construct(
        private GmailOAuthService $gmailService
    ) {}
    /**
     * Get the appropriate provider instance for a mailbox
     */
    public function getProvider(Mailbox $mailbox): MailProviderInterface
    {
        $credentials = $mailbox->getDecryptedCredentials();

        return match ($mailbox->provider) {
            Mailbox::PROVIDER_SMTP => new SmtpProvider(
                host: $credentials['host'] ?? '',
                port: (int) ($credentials['port'] ?? 587),
                encryption: $credentials['encryption'] ?? 'tls',
                username: $credentials['username'] ?? '',
                password: $credentials['password'] ?? '',
                fromEmail: $mailbox->from_email,
                fromName: $mailbox->from_name,
                replyTo: $mailbox->reply_to
            ),

            Mailbox::PROVIDER_SENDGRID => new SendGridProvider(
                apiKey: $credentials['api_key'] ?? '',
                fromEmail: $mailbox->from_email,
                fromName: $mailbox->from_name
            ),

            Mailbox::PROVIDER_GMAIL => new GmailProvider(
                oauthService: $this->gmailService,
                mailbox: $mailbox,
                fromEmail: $mailbox->from_email,
                fromName: $mailbox->from_name
            ),

            Mailbox::PROVIDER_NMI => new NmiProvider(
                domain: $mailbox->domainConfiguration,
                dedicatedIp: $mailbox->dedicatedIp,
                fromEmail: $mailbox->from_email,
                fromName: $mailbox->from_name,
                replyTo: $mailbox->reply_to
            ),

            default => throw new InvalidArgumentException("Unknown provider: {$mailbox->provider}"),
        };
    }

    /**
     * Validate that a mailbox can send to a specific message type
     */
    public function validateMailboxForType(Mailbox $mailbox, string $messageType): bool
    {
        if (!$mailbox->is_active) {
            return false;
        }

        if (!$mailbox->canSendType($messageType)) {
            return false;
        }

        if ($mailbox->hasReachedDailyLimit()) {
            return false;
        }

        return true;
    }

    /**
     * Get the best available mailbox for a user and message type
     */
    public function getBestMailbox(int $userId, string $messageType): ?Mailbox
    {
        // First try the default mailbox
        $defaultMailbox = Mailbox::getDefaultFor($userId);

        if ($defaultMailbox && $this->validateMailboxForType($defaultMailbox, $messageType)) {
            return $defaultMailbox;
        }

        // Try any active mailbox that can handle this type
        return Mailbox::forUser($userId)
            ->active()
            ->canSend($messageType)
            ->whereRaw('daily_limit IS NULL OR sent_today < daily_limit')
            ->first();
    }

    /**
     * Get credential fields required for each provider
     */
    public static function getProviderFields(string $provider): array
    {
        return match ($provider) {
            Mailbox::PROVIDER_SMTP => [
                ['name' => 'host', 'label' => 'SMTP Host', 'type' => 'text', 'required' => true, 'placeholder' => 'smtp.example.com'],
                ['name' => 'port', 'label' => 'Port', 'type' => 'number', 'required' => true, 'default' => 587],
                ['name' => 'encryption', 'label' => 'Encryption', 'type' => 'select', 'required' => true, 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'], 'default' => 'tls'],
                ['name' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true],
                ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true],
            ],

            Mailbox::PROVIDER_SENDGRID => [
                ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'SG.xxxxxxxx'],
            ],

            Mailbox::PROVIDER_GMAIL => [
                // OAuth flow doesn't use manual input fields
            ],

            Mailbox::PROVIDER_NMI => [
                // NMI requires domain configuration, not manual credentials
                // Configuration is done through Domain Settings page
            ],

            default => [],
        };
    }

    /**
     * Get the appropriate mailbox for a webinar.
     * Priority: 1. List's default mailbox, 2. User's default mailbox
     */
    public function getMailboxForWebinar(Webinar $webinar): ?Mailbox
    {
        // 1. Check if webinar has target list with a configured mailbox
        if ($webinar->target_list_id) {
            $list = ContactList::find($webinar->target_list_id);
            if ($list && $list->default_mailbox_id) {
                $mailbox = Mailbox::find($list->default_mailbox_id);
                if ($mailbox && $mailbox->is_active) {
                    return $mailbox;
                }
            }
        }

        // 2. Fallback to user's default mailbox
        return Mailbox::getDefaultFor($webinar->user_id);
    }
}

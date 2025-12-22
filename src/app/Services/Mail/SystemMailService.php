<?php

namespace App\Services\Mail;

use App\Models\Mailbox;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

/**
 * Service for sending system emails (password reset, notifications, etc.)
 * 
 * Uses intelligent fallback:
 * 1. ENV mail configuration (if properly configured)
 * 2. System Mailbox from database (first active SMTP with 'system' type)
 */
class SystemMailService
{
    /**
     * Check if ENV mail configuration is properly set up for sending
     */
    public function isEnvMailConfigured(): bool
    {
        $mailer = config('mail.default');
        
        // Log and array mailers are not for production sending
        if (in_array($mailer, ['log', 'array'])) {
            return false;
        }
        
        // For SMTP, check if host is configured
        if ($mailer === 'smtp') {
            $host = config('mail.mailers.smtp.host');
            return !empty($host) && $host !== '127.0.0.1' && $host !== 'localhost';
        }
        
        // For other mailers (sendgrid, ses, etc.) assume they're configured
        return true;
    }
    
    /**
     * Get a system mailbox from database for fallback
     * Finds first active mailbox that can send 'system' type emails
     */
    public function getSystemMailbox(): ?Mailbox
    {
        return Mailbox::where('is_active', true)
            ->whereJsonContains('allowed_types', Mailbox::TYPE_SYSTEM)
            ->where('provider', Mailbox::PROVIDER_SMTP)
            ->first();
    }
    
    /**
     * Configure Laravel's mail system to use a specific Mailbox
     */
    public function configureMailerFromMailbox(Mailbox $mailbox): void
    {
        $credentials = $mailbox->getDecryptedCredentials();
        
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $credentials['host'] ?? '');
        Config::set('mail.mailers.smtp.port', $credentials['port'] ?? 587);
        Config::set('mail.mailers.smtp.username', $credentials['username'] ?? '');
        Config::set('mail.mailers.smtp.password', $credentials['password'] ?? '');
        Config::set('mail.mailers.smtp.encryption', $credentials['encryption'] ?? 'tls');
        
        Config::set('mail.from.address', $mailbox->from_email);
        Config::set('mail.from.name', $mailbox->from_name ?: config('app.name'));
    }
    
    /**
     * Check if we can send system emails
     */
    public function canSend(): bool
    {
        if ($this->isEnvMailConfigured()) {
            return true;
        }
        
        return $this->getSystemMailbox() !== null;
    }
    
    /**
     * Get the mail configuration source description
     */
    public function getConfigurationSource(): string
    {
        if ($this->isEnvMailConfigured()) {
            return 'ENV';
        }
        
        $mailbox = $this->getSystemMailbox();
        if ($mailbox) {
            return 'Mailbox: ' . $mailbox->name;
        }
        
        return 'Not configured';
    }
    
    /**
     * Prepare the mail system for sending
     * Should be called before sending system emails
     * 
     * @return bool True if mail is configured and ready
     */
    public function prepare(): bool
    {
        // If ENV is configured, we're good to go
        if ($this->isEnvMailConfigured()) {
            Log::debug('SystemMailService: Using ENV mail configuration');
            return true;
        }
        
        // Try to use a system mailbox
        $mailbox = $this->getSystemMailbox();
        if ($mailbox) {
            Log::debug('SystemMailService: Using Mailbox fallback', [
                'mailbox_id' => $mailbox->id,
                'mailbox_name' => $mailbox->name,
            ]);
            $this->configureMailerFromMailbox($mailbox);
            return true;
        }
        
        Log::warning('SystemMailService: No mail configuration available');
        return false;
    }
    
    /**
     * Send a raw email using the configured mailer
     */
    public function sendRaw(string $to, string $subject, string $content): bool
    {
        if (!$this->prepare()) {
            return false;
        }
        
        try {
            Mail::raw($content, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
            return true;
        } catch (\Exception $e) {
            Log::error('SystemMailService: Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

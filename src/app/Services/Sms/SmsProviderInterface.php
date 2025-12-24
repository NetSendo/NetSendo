<?php

namespace App\Services\Sms;

/**
 * Interface for SMS providers.
 *
 * All SMS providers (Twilio, SMS API, etc.) must implement this interface.
 */
interface SmsProviderInterface
{
    /**
     * Send a single SMS message.
     *
     * @param string $to Recipient phone number (E.164 format recommended, e.g., +48123456789)
     * @param string $content SMS message content
     * @param string|null $from Sender ID (phone number or alphanumeric name)
     * @return SmsResult Result of the send operation
     */
    public function send(string $to, string $content, ?string $from = null): SmsResult;

    /**
     * Send SMS to multiple recipients.
     *
     * @param array $recipients Array of phone numbers
     * @param string $content SMS message content
     * @param string|null $from Sender ID
     * @return array<SmsResult> Array of results for each recipient
     */
    public function sendBatch(array $recipients, string $content, ?string $from = null): array;

    /**
     * Test the connection to the SMS provider.
     *
     * @return array{success: bool, message: string, balance?: float}
     */
    public function testConnection(): array;

    /**
     * Get the current account balance/credits.
     *
     * @return float|null Balance in provider's currency, null if not supported
     */
    public function getBalance(): ?float;

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getProviderName(): string;
}

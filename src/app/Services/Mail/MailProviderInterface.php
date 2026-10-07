<?php

namespace App\Services\Mail;

interface MailProviderInterface
{
    /**
     * Send an email
     *
     * @param string $to Recipient email
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $htmlContent HTML content of the email
     * @param array $headers Custom headers (e.g. List-Unsubscribe)
     * @param array $attachments Attachments array with 'path', 'name', 'mime_type' keys
     * @param string|null $textContent text/plain alternative; null = generated from the HTML
     *                                 (see App\Helpers\EmailPlainText::forEmail())
     * @param bool|null $trackingEnabled false = the message must not be tracked: providers
     *                                   that track on their own (SendGrid) switch it off for
     *                                   this message; null/true = the provider's own setting
     * @return bool
     */
    public function send(string $to, string $toName, string $subject, string $htmlContent, array $headers = [], array $attachments = [], ?string $textContent = null, ?bool $trackingEnabled = null): bool;

    /**
     * Test the connection to the mail provider
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(?string $toEmail = null): array;

    /**
     * Get provider name
     *
     * @return string
     */
    public function getProviderName(): string;
}

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
     * @return bool
     */
    public function send(string $to, string $toName, string $subject, string $htmlContent, array $headers = []): bool;

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

<?php

namespace App\Services\Mail\Providers;

use App\Services\Mail\MailProviderInterface;
use Illuminate\Support\Facades\Http;
use Exception;

class SendGridProvider implements MailProviderInterface
{
    private const API_URL = 'https://api.sendgrid.com/v3/mail/send';

    public function __construct(
        private string $apiKey,
        private string $fromEmail,
        private string $fromName
    ) {}

    public function send(string $to, string $toName, string $subject, string $htmlContent, array $headers = [], array $attachments = []): bool
    {
        try {
            $payload = [
                'personalizations' => [
                    [
                        'to' => [
                            [
                                'email' => $to,
                                'name' => $toName,
                            ],
                        ],
                        'subject' => $subject,
                    ],
                ],
                'from' => [
                    'email' => $this->fromEmail,
                    'name' => $this->fromName,
                ],
                'content' => [
                    [
                        'type' => 'text/html',
                        'value' => $htmlContent,
                    ],
                ],
            ];

            if (!empty($headers)) {
                $payload['headers'] = $headers;
            }

            // Add attachments
            if (!empty($attachments)) {
                $payload['attachments'] = [];
                foreach ($attachments as $attachment) {
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        $payload['attachments'][] = [
                            'content' => base64_encode(file_get_contents($attachment['path'])),
                            'filename' => $attachment['name'] ?? basename($attachment['path']),
                            'type' => $attachment['mime_type'] ?? 'application/pdf',
                            'disposition' => 'attachment',
                        ];
                    }
                }
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post(self::API_URL, $payload);

            if ($response->successful() || $response->status() === 202) {
                return true;
            }

            \Log::error('SendGrid API error: ' . $response->body());
            throw new Exception('SendGrid API error: ' . $response->body());
        } catch (Exception $e) {
            \Log::error("SendGridProvider send failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function testConnection(?string $toEmail = null): array
    {
        try {
            // Verify API key by checking scopes
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get('https://api.sendgrid.com/v3/scopes');

            if ($response->successful()) {
                $scopes = $response->json('scopes', []);

                if (in_array('mail.send', $scopes)) {
                    // Scopes are okay, now try to send a test email
                    try {
                        $recipient = $toEmail ?? $this->fromEmail;
                        $this->send(
                            $recipient,
                            'Test User',
                            'NetSendo Connection Test',
                            '<p>This is a test email from NetSendo to verify your SendGrid integration.</p>'
                        );

                        return [
                            'success' => true,
                            'message' => 'Connected to SendGrid and test email sent successfully!',
                        ];
                    } catch (Exception $e) {
                         return [
                            'success' => false,
                            'message' => 'API Key valid, but failed to send email: ' . $e->getMessage(),
                        ];
                    }
                }

                return [
                    'success' => true,
                    'message' => 'Connected to SendGrid, but "mail.send" scope is missing. Please check your API Key permissions.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Invalid API key or connection failed: ' . $response->body(),
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
        return 'SendGrid';
    }
}

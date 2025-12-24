<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Twilio SMS Provider implementation.
 *
 * @see https://www.twilio.com/docs/sms/api/message-resource
 */
class TwilioProvider implements SmsProviderInterface
{
    private const API_BASE_URL = 'https://api.twilio.com/2010-04-01';

    public function __construct(
        private string $accountSid,
        private string $authToken,
        private string $fromNumber
    ) {}

    public function send(string $to, string $content, ?string $from = null): SmsResult
    {
        try {
            $url = self::API_BASE_URL . "/Accounts/{$this->accountSid}/Messages.json";

            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post($url, [
                    'To' => $this->normalizePhoneNumber($to),
                    'From' => $from ?? $this->fromNumber,
                    'Body' => $content,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return SmsResult::success(
                    messageId: $data['sid'] ?? 'unknown',
                    parts: $data['num_segments'] ?? 1,
                    metadata: [
                        'status' => $data['status'] ?? null,
                        'direction' => $data['direction'] ?? null,
                    ]
                );
            }

            $errorData = $response->json();
            Log::error('Twilio API error', [
                'status' => $response->status(),
                'body' => $errorData,
            ]);

            return SmsResult::failure(
                errorMessage: $errorData['message'] ?? 'Unknown Twilio error',
                errorCode: (string) ($errorData['code'] ?? $response->status())
            );
        } catch (Exception $e) {
            Log::error('TwilioProvider send failed', ['error' => $e->getMessage()]);
            return SmsResult::failure(
                errorMessage: $e->getMessage(),
                errorCode: 'EXCEPTION'
            );
        }
    }

    public function sendBatch(array $recipients, string $content, ?string $from = null): array
    {
        $results = [];

        foreach ($recipients as $recipient) {
            $results[$recipient] = $this->send($recipient, $content, $from);
        }

        return $results;
    }

    public function testConnection(): array
    {
        try {
            $url = self::API_BASE_URL . "/Accounts/{$this->accountSid}.json";

            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $balance = $this->getBalance();

                return [
                    'success' => true,
                    'message' => sprintf(
                        'Połączono z Twilio. Konto: %s (%s)',
                        $data['friendly_name'] ?? 'Unknown',
                        $data['status'] ?? 'Unknown'
                    ),
                    'balance' => $balance,
                ];
            }

            return [
                'success' => false,
                'message' => 'Nieprawidłowe dane uwierzytelniające: ' . ($response->json()['message'] ?? 'Unknown error'),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd połączenia: ' . $e->getMessage(),
            ];
        }
    }

    public function getBalance(): ?float
    {
        try {
            $url = self::API_BASE_URL . "/Accounts/{$this->accountSid}/Balance.json";

            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return (float) ($data['balance'] ?? 0);
            }

            return null;
        } catch (Exception $e) {
            Log::warning('Failed to get Twilio balance', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getProviderName(): string
    {
        return 'Twilio';
    }

    /**
     * Normalize phone number to E.164 format.
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-digit characters except leading +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Ensure it starts with +
        if (!str_starts_with($phone, '+')) {
            // Assume Polish number if 9 digits
            if (strlen($phone) === 9) {
                $phone = '+48' . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }
}

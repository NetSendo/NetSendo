<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * SMS API Provider implementation (smsapi.pl / smsapi.com).
 *
 * Supports Polish and international SMS sending.
 *
 * @see https://www.smsapi.pl/docs
 */
class SmsApiProvider implements SmsProviderInterface
{
    private const API_URL_PL = 'https://api.smsapi.pl';
    private const API_URL_COM = 'https://api.smsapi.com';

    public function __construct(
        private string $apiToken,
        private ?string $fromName = null,
        private bool $usePolishEndpoint = true,
        private bool $testMode = false
    ) {}

    private function getBaseUrl(): string
    {
        return $this->usePolishEndpoint ? self::API_URL_PL : self::API_URL_COM;
    }

    public function send(string $to, string $content, ?string $from = null): SmsResult
    {
        try {
            $params = [
                'to' => $this->normalizePhoneNumber($to),
                'message' => $content,
                'format' => 'json',
                'encoding' => 'utf-8',
            ];

            // Set sender name if provided
            $sender = $from ?? $this->fromName;
            if ($sender) {
                $params['from'] = $sender;
            }

            // Test mode (doesn't actually send, for testing)
            if ($this->testMode) {
                $params['test'] = 1;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
            ])->asForm()->post($this->getBaseUrl() . '/sms.do', $params);

            $data = $response->json();

            // SMS API returns error in specific format
            if (isset($data['error'])) {
                Log::error('SMS API error', ['response' => $data]);
                return SmsResult::failure(
                    errorMessage: $data['message'] ?? 'SMS API error',
                    errorCode: (string) ($data['error'] ?? 'UNKNOWN')
                );
            }

            // Success - message sent
            if (isset($data['list']) && is_array($data['list']) && count($data['list']) > 0) {
                $messageInfo = $data['list'][0];
                return SmsResult::success(
                    messageId: $messageInfo['id'] ?? 'unknown',
                    credits: isset($messageInfo['points']) ? (float) $messageInfo['points'] : null,
                    parts: $messageInfo['parts'] ?? 1,
                    metadata: [
                        'status' => $messageInfo['status'] ?? null,
                        'number' => $messageInfo['number'] ?? null,
                    ]
                );
            }

            return SmsResult::failure(
                errorMessage: 'Unexpected response from SMS API',
                errorCode: 'INVALID_RESPONSE'
            );
        } catch (Exception $e) {
            Log::error('SmsApiProvider send failed', ['error' => $e->getMessage()]);
            return SmsResult::failure(
                errorMessage: $e->getMessage(),
                errorCode: 'EXCEPTION'
            );
        }
    }

    public function sendBatch(array $recipients, string $content, ?string $from = null): array
    {
        // SMS API supports batch sending with comma-separated numbers
        try {
            $normalizedRecipients = array_map(
                fn($phone) => $this->normalizePhoneNumber($phone),
                $recipients
            );

            $params = [
                'to' => implode(',', $normalizedRecipients),
                'message' => $content,
                'format' => 'json',
                'encoding' => 'utf-8',
            ];

            $sender = $from ?? $this->fromName;
            if ($sender) {
                $params['from'] = $sender;
            }

            if ($this->testMode) {
                $params['test'] = 1;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
            ])->asForm()->post($this->getBaseUrl() . '/sms.do', $params);

            $data = $response->json();

            if (isset($data['error'])) {
                // All failed
                $failure = SmsResult::failure(
                    errorMessage: $data['message'] ?? 'SMS API error',
                    errorCode: (string) ($data['error'] ?? 'UNKNOWN')
                );
                return array_fill_keys($recipients, $failure);
            }

            // Map results back to recipients
            $results = [];
            if (isset($data['list']) && is_array($data['list'])) {
                foreach ($data['list'] as $messageInfo) {
                    $number = $messageInfo['number'] ?? null;
                    if ($number) {
                        $results[$number] = SmsResult::success(
                            messageId: $messageInfo['id'] ?? 'unknown',
                            credits: isset($messageInfo['points']) ? (float) $messageInfo['points'] : null,
                            parts: $messageInfo['parts'] ?? 1,
                            metadata: ['status' => $messageInfo['status'] ?? null]
                        );
                    }
                }
            }

            return $results;
        } catch (Exception $e) {
            Log::error('SmsApiProvider sendBatch failed', ['error' => $e->getMessage()]);
            $failure = SmsResult::failure($e->getMessage(), 'EXCEPTION');
            return array_fill_keys($recipients, $failure);
        }
    }

    public function testConnection(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
            ])->get($this->getBaseUrl() . '/profile');

            if ($response->successful()) {
                $data = $response->json();
                $balance = $this->getBalance();

                return [
                    'success' => true,
                    'message' => sprintf(
                        'Połączono z SMS API. Konto: %s',
                        $data['username'] ?? 'Unknown'
                    ),
                    'balance' => $balance,
                ];
            }

            $errorData = $response->json();
            return [
                'success' => false,
                'message' => 'Nieprawidłowy token API: ' . ($errorData['message'] ?? 'Unknown error'),
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
            ])->get($this->getBaseUrl() . '/profile');

            if ($response->successful()) {
                $data = $response->json();
                return isset($data['points']) ? (float) $data['points'] : null;
            }

            return null;
        } catch (Exception $e) {
            Log::warning('Failed to get SMS API balance', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getProviderName(): string
    {
        return $this->usePolishEndpoint ? 'SMS API (PL)' : 'SMS API (COM)';
    }

    /**
     * Normalize phone number for SMS API.
     * SMS API accepts numbers with or without country code.
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-digit characters
        $phone = preg_replace('/[^\d]/', '', $phone);

        // For Polish numbers, SMS API prefers 48XXXXXXXXX format
        if (strlen($phone) === 9 && $this->usePolishEndpoint) {
            $phone = '48' . $phone;
        }

        return $phone;
    }
}

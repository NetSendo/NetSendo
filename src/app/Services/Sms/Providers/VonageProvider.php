<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VonageProvider implements SmsProviderInterface
{
    private const API_URL = 'https://rest.nexmo.com/sms/json';
    private const BALANCE_URL = 'https://rest.nexmo.com/account/get-balance';

    public function __construct(
        private string $apiKey,
        private string $apiSecret,
        private ?string $defaultFrom = null
    ) {}

    public function send(string $to, string $content, ?string $from = null): SmsResult
    {
        $sender = $from ?? $this->defaultFrom ?? 'NetSendo';

        try {
            $response = Http::asForm()->post(self::API_URL, [
                'api_key' => $this->apiKey,
                'api_secret' => $this->apiSecret,
                'to' => $this->normalizePhone($to),
                'from' => $sender,
                'text' => $content,
            ]);

            $data = $response->json();
            $message = $data['messages'][0] ?? [];

            if (($message['status'] ?? '1') === '0') {
                return SmsResult::success(
                    $message['message-id'] ?? null,
                    [
                        'remaining_balance' => $message['remaining-balance'] ?? null,
                        'message_price' => $message['message-price'] ?? null,
                        'network' => $message['network'] ?? null,
                    ]
                );
            }

            return SmsResult::failure(
                $message['error-text'] ?? 'Unknown Vonage error',
                $message['status'] ?? 'UNKNOWN'
            );
        } catch (\Exception $e) {
            Log::error('Vonage SMS send failed', [
                'error' => $e->getMessage(),
                'to' => $to,
            ]);

            return SmsResult::failure($e->getMessage(), 'EXCEPTION');
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
            $response = Http::get(self::BALANCE_URL, [
                'api_key' => $this->apiKey,
                'api_secret' => $this->apiSecret,
            ]);

            $data = $response->json();

            if (isset($data['value'])) {
                return [
                    'success' => true,
                    'message' => 'Połączenie z Vonage udane. Saldo: €' . number_format($data['value'], 2),
                    'balance' => $data['value'],
                ];
            }

            return [
                'success' => false,
                'message' => $data['error-text'] ?? 'Błąd połączenia z Vonage',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd: ' . $e->getMessage(),
            ];
        }
    }

    public function getBalance(): ?float
    {
        try {
            $response = Http::get(self::BALANCE_URL, [
                'api_key' => $this->apiKey,
                'api_secret' => $this->apiSecret,
            ]);

            $data = $response->json();
            return $data['value'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getProviderName(): string
    {
        return 'vonage';
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}

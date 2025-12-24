<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessageBirdProvider implements SmsProviderInterface
{
    private const API_URL = 'https://rest.messagebird.com/messages';
    private const BALANCE_URL = 'https://rest.messagebird.com/balance';

    public function __construct(
        private string $accessKey,
        private ?string $defaultFrom = null
    ) {}

    public function send(string $to, string $content, ?string $from = null): SmsResult
    {
        $sender = $from ?? $this->defaultFrom ?? 'NetSendo';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'AccessKey ' . $this->accessKey,
                'Content-Type' => 'application/json',
            ])->post(self::API_URL, [
                'originator' => $sender,
                'recipients' => [$this->normalizePhone($to)],
                'body' => $content,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $recipient = $data['recipients']['items'][0] ?? [];

                return SmsResult::success(
                    $data['id'] ?? null,
                    [
                        'recipient_status' => $recipient['status'] ?? null,
                        'message_parts' => $recipient['messagePartCount'] ?? 1,
                    ]
                );
            }

            $error = $response->json();
            return SmsResult::failure(
                $error['errors'][0]['description'] ?? 'MessageBird error',
                (string) ($error['errors'][0]['code'] ?? 'UNKNOWN')
            );
        } catch (\Exception $e) {
            Log::error('MessageBird SMS send failed', [
                'error' => $e->getMessage(),
                'to' => $to,
            ]);

            return SmsResult::failure($e->getMessage(), 'EXCEPTION');
        }
    }

    public function sendBatch(array $recipients, string $content, ?string $from = null): array
    {
        $sender = $from ?? $this->defaultFrom ?? 'NetSendo';

        try {
            // MessageBird supports batch sending natively
            $response = Http::withHeaders([
                'Authorization' => 'AccessKey ' . $this->accessKey,
                'Content-Type' => 'application/json',
            ])->post(self::API_URL, [
                'originator' => $sender,
                'recipients' => array_map([$this, 'normalizePhone'], $recipients),
                'body' => $content,
            ]);

            $results = [];
            if ($response->successful()) {
                $data = $response->json();
                foreach ($data['recipients']['items'] ?? [] as $item) {
                    $phone = $item['recipient'] ?? '';
                    $results[$phone] = SmsResult::success($data['id'] ?? null);
                }
            } else {
                $error = $response->json();
                $errorMsg = $error['errors'][0]['description'] ?? 'MessageBird batch error';
                foreach ($recipients as $recipient) {
                    $results[$recipient] = SmsResult::failure($errorMsg, 'BATCH_ERROR');
                }
            }

            return $results;
        } catch (\Exception $e) {
            $results = [];
            foreach ($recipients as $recipient) {
                $results[$recipient] = SmsResult::failure($e->getMessage(), 'EXCEPTION');
            }
            return $results;
        }
    }

    public function testConnection(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'AccessKey ' . $this->accessKey,
            ])->get(self::BALANCE_URL);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'Połączenie z MessageBird udane. Saldo: ' .
                        ($data['amount'] ?? 0) . ' ' . ($data['type'] ?? 'credits'),
                    'balance' => $data['amount'] ?? null,
                ];
            }

            $error = $response->json();
            return [
                'success' => false,
                'message' => $error['errors'][0]['description'] ?? 'Błąd połączenia z MessageBird',
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
            $response = Http::withHeaders([
                'Authorization' => 'AccessKey ' . $this->accessKey,
            ])->get(self::BALANCE_URL);

            if ($response->successful()) {
                $data = $response->json();
                return $data['amount'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getProviderName(): string
    {
        return 'messagebird';
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone);
    }
}

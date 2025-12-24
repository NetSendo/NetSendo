<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlivoProvider implements SmsProviderInterface
{
    private const API_BASE = 'https://api.plivo.com/v1/Account';

    public function __construct(
        private string $authId,
        private string $authToken,
        private ?string $defaultFrom = null
    ) {}

    public function send(string $to, string $content, ?string $from = null): SmsResult
    {
        $sender = $from ?? $this->defaultFrom;

        if (!$sender) {
            return SmsResult::failure('Numer nadawcy jest wymagany dla Plivo', 'NO_SENDER');
        }

        try {
            $response = Http::withBasicAuth($this->authId, $this->authToken)
                ->post($this->getApiUrl('/Message/'), [
                    'src' => $sender,
                    'dst' => $this->normalizePhone($to),
                    'text' => $content,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return SmsResult::success(
                    $data['message_uuid'][0] ?? null,
                    [
                        'api_id' => $data['api_id'] ?? null,
                        'message' => $data['message'] ?? null,
                    ]
                );
            }

            $error = $response->json();
            return SmsResult::failure(
                $error['error'] ?? 'Plivo error',
                (string) $response->status()
            );
        } catch (\Exception $e) {
            Log::error('Plivo SMS send failed', [
                'error' => $e->getMessage(),
                'to' => $to,
            ]);

            return SmsResult::failure($e->getMessage(), 'EXCEPTION');
        }
    }

    public function sendBatch(array $recipients, string $content, ?string $from = null): array
    {
        $sender = $from ?? $this->defaultFrom;

        if (!$sender) {
            $results = [];
            foreach ($recipients as $recipient) {
                $results[$recipient] = SmsResult::failure('Numer nadawcy jest wymagany', 'NO_SENDER');
            }
            return $results;
        }

        try {
            // Plivo supports multiple recipients separated by <
            $dst = implode('<', array_map([$this, 'normalizePhone'], $recipients));

            $response = Http::withBasicAuth($this->authId, $this->authToken)
                ->post($this->getApiUrl('/Message/'), [
                    'src' => $sender,
                    'dst' => $dst,
                    'text' => $content,
                ]);

            $results = [];
            if ($response->successful()) {
                $data = $response->json();
                $uuids = $data['message_uuid'] ?? [];

                foreach ($recipients as $index => $recipient) {
                    $results[$recipient] = SmsResult::success($uuids[$index] ?? null);
                }
            } else {
                $error = $response->json();
                $errorMsg = $error['error'] ?? 'Plivo batch error';
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
            $response = Http::withBasicAuth($this->authId, $this->authToken)
                ->get($this->getApiUrl('/'));

            if ($response->successful()) {
                $data = $response->json();
                $balance = $data['cash_credits'] ?? 0;

                return [
                    'success' => true,
                    'message' => 'Połączenie z Plivo udane. Saldo: $' . number_format($balance, 2),
                    'balance' => $balance,
                ];
            }

            $error = $response->json();
            return [
                'success' => false,
                'message' => $error['error'] ?? 'Błąd połączenia z Plivo',
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
            $response = Http::withBasicAuth($this->authId, $this->authToken)
                ->get($this->getApiUrl('/'));

            if ($response->successful()) {
                $data = $response->json();
                return (float) ($data['cash_credits'] ?? 0);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getProviderName(): string
    {
        return 'plivo';
    }

    private function getApiUrl(string $path): string
    {
        return self::API_BASE . '/' . $this->authId . $path;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}

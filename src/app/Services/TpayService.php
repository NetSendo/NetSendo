<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\TpayProduct;
use App\Models\TpayTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TpayService
{
    /**
     * Get Tpay settings from database.
     */
    public static function getTpaySettings(): array
    {
        $clientIdEncrypted = Setting::where('key', 'tpay_client_id')->first()?->value;
        $clientSecretEncrypted = Setting::where('key', 'tpay_client_secret')->first()?->value;
        $securityCodeEncrypted = Setting::where('key', 'tpay_security_code')->first()?->value;
        $sandboxMode = Setting::where('key', 'tpay_sandbox_mode')->first()?->value;

        $clientId = null;
        $clientSecret = null;
        $securityCode = null;

        if ($clientIdEncrypted) {
            try {
                $clientId = Crypt::decryptString($clientIdEncrypted);
            } catch (\Exception $e) {
                // Decryption failed
            }
        }

        if ($clientSecretEncrypted) {
            try {
                $clientSecret = Crypt::decryptString($clientSecretEncrypted);
            } catch (\Exception $e) {
                // Decryption failed
            }
        }

        if ($securityCodeEncrypted) {
            try {
                $securityCode = Crypt::decryptString($securityCodeEncrypted);
            } catch (\Exception $e) {
                // Decryption failed
            }
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'security_code' => $securityCode,
            'sandbox_mode' => $sandboxMode === '1' || $sandboxMode === 'true',
        ];
    }

    /**
     * Check if Tpay is configured.
     */
    public function isConfigured(): bool
    {
        $settings = self::getTpaySettings();
        return !empty($settings['client_id']) && !empty($settings['client_secret']);
    }

    /**
     * Get the API base URL based on mode.
     */
    private function getApiUrl(): string
    {
        $settings = self::getTpaySettings();
        return $settings['sandbox_mode']
            ? config('tpay.sandbox_api_url')
            : config('tpay.api_url');
    }

    /**
     * Get OAuth access token (cached until expiry).
     */
    public function getAccessToken(): ?string
    {
        $settings = self::getTpaySettings();

        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            return null;
        }

        $cacheKey = 'tpay_access_token_' . md5($settings['client_id']);

        return Cache::remember($cacheKey, config('tpay.token_ttl', 7200) - 60, function () use ($settings) {
            return $this->requestAccessToken($settings['client_id'], $settings['client_secret']);
        });
    }

    /**
     * Request a new OAuth access token from Tpay.
     */
    private function requestAccessToken(string $clientId, string $clientSecret): ?string
    {
        $apiUrl = $this->getApiUrl();

        try {
            $response = Http::asForm()->post("{$apiUrl}/oauth/auth", [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            Log::error('Tpay OAuth token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Tpay OAuth token request exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Test connection by requesting an OAuth token.
     */
    public function testConnection(string $clientId, string $clientSecret, bool $sandboxMode = false): array
    {
        $apiUrl = $sandboxMode
            ? config('tpay.sandbox_api_url')
            : config('tpay.api_url');

        try {
            $response = Http::asForm()->post("{$apiUrl}/oauth/auth", [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['access_token'])) {
                    return ['success' => true, 'message' => 'Connection successful'];
                }
            }

            return [
                'success' => false,
                'message' => 'Authentication failed: ' . ($response->json('error_description') ?? $response->body()),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create a transaction in Tpay and return the payment URL.
     */
    public function createTransaction(TpayProduct $product, array $payer, array $callbacks = []): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            throw new \RuntimeException('Unable to authenticate with Tpay API');
        }

        $apiUrl = $this->getApiUrl();

        // Generate unique CRC for verification
        $crc = uniqid('ns_', true);

        $transactionData = [
            'amount' => $product->price / 100, // Tpay expects amount in main currency units
            'description' => $product->name,
            'hiddenDescription' => $crc,
            'payer' => [
                'email' => $payer['email'],
                'name' => $payer['name'] ?? $payer['email'],
            ],
            'callbacks' => [
                'notification' => [
                    'url' => $callbacks['notification_url'] ?? route('webhooks.tpay'),
                ],
                'payerUrls' => [
                    'success' => $callbacks['success_url'] ?? config('app.url') . '/checkout/success',
                    'error' => $callbacks['error_url'] ?? config('app.url') . '/checkout/error',
                ],
            ],
        ];

        try {
            $response = Http::withToken($token)
                ->post("{$apiUrl}/transactions", $transactionData);

            if ($response->successful()) {
                $data = $response->json();

                // Create pending transaction record
                $transaction = TpayTransaction::create([
                    'user_id' => $product->user_id,
                    'tpay_product_id' => $product->id,
                    'tpay_transaction_id' => $data['transactionId'] ?? null,
                    'tpay_title' => $data['title'] ?? null,
                    'customer_email' => $payer['email'],
                    'customer_name' => $payer['name'] ?? null,
                    'amount' => $product->price,
                    'currency' => $product->currency,
                    'status' => 'pending',
                    'tr_crc' => $crc,
                    'metadata' => [
                        'tpay_response' => $data,
                    ],
                ]);

                Log::info('Tpay transaction created', [
                    'transaction_id' => $transaction->id,
                    'tpay_transaction_id' => $data['transactionId'] ?? null,
                    'product_id' => $product->id,
                ]);

                return [
                    'transaction' => $transaction,
                    'payment_url' => $data['transactionPaymentUrl'] ?? null,
                    'tpay_data' => $data,
                ];
            }

            Log::error('Tpay transaction creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'product_id' => $product->id,
            ]);

            throw new \RuntimeException('Tpay transaction creation failed: ' . $response->body());

        } catch (\Exception $e) {
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            Log::error('Tpay transaction creation exception', [
                'error' => $e->getMessage(),
                'product_id' => $product->id,
            ]);
            throw new \RuntimeException('Tpay API error: ' . $e->getMessage());
        }
    }

    /**
     * Get checkout URL for a product.
     */
    public function getCheckoutUrl(TpayProduct $product, array $options = []): string
    {
        $payer = [
            'email' => $options['customer_email'] ?? 'customer@example.com',
            'name' => $options['customer_name'] ?? null,
        ];

        $callbacks = [];
        if (isset($options['success_url'])) {
            $callbacks['success_url'] = $options['success_url'];
        }
        if (isset($options['error_url'])) {
            $callbacks['error_url'] = $options['error_url'];
        }

        $result = $this->createTransaction($product, $payer, $callbacks);

        if (empty($result['payment_url'])) {
            throw new \RuntimeException('Tpay did not return a payment URL');
        }

        return $result['payment_url'];
    }

    /**
     * Verify JWS signature from Tpay webhook notification.
     */
    public function verifyJwsSignature(string $payload, string $jwsHeader): bool
    {
        if (empty($jwsHeader)) {
            return false;
        }

        try {
            // Extract JWS header properties
            $jwsData = explode('.', $jwsHeader);
            $headers = $jwsData[0] ?? null;
            $signature = $jwsData[2] ?? null;

            if (!$headers || !$signature) {
                Log::warning('Tpay JWS: invalid header structure');
                return false;
            }

            // Decode received headers JSON string from base64_url_safe
            $headersJson = base64_decode(strtr($headers, '-_', '+/'));
            $headersData = json_decode($headersJson, true);
            $x5u = $headersData['x5u'] ?? null;

            if (!$x5u) {
                Log::warning('Tpay JWS: missing x5u header');
                return false;
            }

            // Verify certificate URL prefix
            $prefix = 'https://secure.tpay.com';
            if (substr($x5u, 0, strlen($prefix)) !== $prefix) {
                Log::warning('Tpay JWS: wrong x5u URL', ['x5u' => $x5u]);
                return false;
            }

            // Get JWS signing certificate
            $certificate = file_get_contents($x5u);
            if (!$certificate) {
                Log::error('Tpay JWS: unable to fetch signing certificate');
                return false;
            }

            // Get Tpay CA certificate
            $trusted = Cache::remember('tpay_ca_certificate', 86400, function () {
                return file_get_contents(config('tpay.jws_ca_url'));
            });

            if (!$trusted) {
                Log::error('Tpay JWS: unable to fetch CA certificate');
                return false;
            }

            // Verify signing certificate against CA
            if (function_exists('openssl_x509_verify')) {
                if (1 !== openssl_x509_verify($certificate, $trusted)) {
                    Log::warning('Tpay JWS: certificate not signed by Tpay CA');
                    return false;
                }
            }

            // Encode body to base64_url_safe
            $encodedPayload = str_replace('=', '', strtr(base64_encode($payload), '+/', '-_'));

            // Decode signature from base64_url_safe
            $decodedSignature = base64_decode(strtr($signature, '-_', '+/'));

            // Verify JWS signature
            $publicKey = openssl_pkey_get_public($certificate);
            if (!$publicKey) {
                Log::error('Tpay JWS: unable to extract public key from certificate');
                return false;
            }

            $verified = openssl_verify(
                $headers . '.' . $encodedPayload,
                $decodedSignature,
                $publicKey,
                OPENSSL_ALGO_SHA256
            );

            return $verified === 1;

        } catch (\Exception $e) {
            Log::error('Tpay JWS verification error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Verify md5sum from Tpay notification.
     */
    public function verifyMd5Sum(array $params): bool
    {
        $settings = self::getTpaySettings();
        $securityCode = $settings['security_code'] ?? '';

        if (empty($securityCode)) {
            Log::warning('Tpay md5sum verification: missing security code');
            return false;
        }

        $id = $params['id'] ?? '';
        $trId = $params['tr_id'] ?? '';
        $trAmount = $params['tr_amount'] ?? '';
        $trCrc = $params['tr_crc'] ?? '';
        $md5sum = $params['md5sum'] ?? '';

        $expectedMd5 = md5($id . $trId . $trAmount . $trCrc . $securityCode);

        return $md5sum === $expectedMd5;
    }

    /**
     * Handle transaction notification from Tpay.
     */
    public function handleTransactionNotification(array $data): ?TpayTransaction
    {
        $trId = $data['tr_id'] ?? null;
        $trCrc = $data['tr_crc'] ?? null;
        $trStatus = $data['tr_status'] ?? null;
        $trAmount = $data['tr_amount'] ?? null;
        $trEmail = $data['tr_email'] ?? null;

        // Try to find existing transaction by CRC (our reference) or Tpay transaction ID
        $transaction = null;

        if ($trCrc) {
            $transaction = TpayTransaction::where('tr_crc', $trCrc)->first();
        }

        if (!$transaction && $trId) {
            $transaction = TpayTransaction::where('tpay_transaction_id', $trId)->first();
        }

        if (!$transaction) {
            Log::warning('Tpay notification: transaction not found', [
                'tr_id' => $trId,
                'tr_crc' => $trCrc,
            ]);
            return null;
        }

        // Map Tpay status to our status
        $status = match ($trStatus) {
            'TRUE', 'PAID' => 'completed',
            'CHARGEBACK' => 'chargeback',
            default => $transaction->status, // Keep current status
        };

        // Update transaction
        $transaction->update([
            'tpay_transaction_id' => $trId ?? $transaction->tpay_transaction_id,
            'status' => $status,
            'payment_method' => $data['tr_channel'] ?? $transaction->payment_method,
            'customer_email' => $trEmail ?? $transaction->customer_email,
            'metadata' => array_merge($transaction->metadata ?? [], [
                'notification' => $data,
                'notified_at' => now()->toISOString(),
            ]),
        ]);

        // Link to subscriber if possible
        if ($trEmail && !$transaction->subscriber_id) {
            $subscriber = Subscriber::where('email', $trEmail)->first();
            if ($subscriber) {
                $transaction->update(['subscriber_id' => $subscriber->id]);
            }
        }

        Log::info('Tpay transaction updated via notification', [
            'transaction_id' => $transaction->id,
            'tpay_tr_id' => $trId,
            'status' => $status,
        ]);

        return $transaction->fresh();
    }
}

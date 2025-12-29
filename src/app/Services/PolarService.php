<?php

namespace App\Services;

use App\Http\Controllers\PolarSettingsController;
use App\Models\PolarProduct;
use App\Models\PolarTransaction;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PolarService
{
    private ?string $accessToken = null;
    private ?string $webhookSecret = null;
    private string $environment = 'sandbox';
    private string $baseUrl;

    public function __construct()
    {
        $settings = PolarSettingsController::getPolarSettings();
        $this->accessToken = $settings['access_token'] ?? null;
        $this->webhookSecret = $settings['webhook_secret'] ?? null;
        $this->environment = $settings['environment'] ?? 'sandbox';

        $this->baseUrl = $this->environment === 'production'
            ? 'https://api.polar.sh'
            : 'https://sandbox-api.polar.sh';
    }

    /**
     * Check if Polar is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->accessToken);
    }

    /**
     * Get the configured environment.
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Make an authenticated request to Polar API.
     */
    private function apiRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $response = Http::withToken($this->accessToken)
            ->accept('application/json')
            ->$method($url, $data);

        if (!$response->successful()) {
            Log::error('Polar API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Polar API error: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Create a product in Polar and save it locally.
     */
    public function createProduct(int $userId, array $data): PolarProduct
    {
        // Build product payload for Polar API
        $productPayload = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ];

        // Create product in Polar
        $polarProduct = $this->apiRequest('post', '/v1/products', $productPayload);

        // Build price payload
        $pricePayload = [
            'product_id' => $polarProduct['id'],
            'amount_type' => 'fixed',
            'price_amount' => $data['price'],
            'price_currency' => strtolower($data['currency'] ?? 'usd'),
        ];

        if ($data['type'] === 'recurring') {
            $pricePayload['type'] = 'recurring';
            $pricePayload['recurring_interval'] = $data['billing_interval'] ?? 'month';
        } else {
            $pricePayload['type'] = 'one_time';
        }

        // Create price in Polar
        $polarPrice = $this->apiRequest('post', '/v1/prices', $pricePayload);

        // Save locally
        return PolarProduct::create([
            'user_id' => $userId,
            'polar_product_id' => $polarProduct['id'],
            'polar_price_id' => $polarPrice['id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'type' => $data['type'] ?? 'one_time',
            'billing_interval' => $data['billing_interval'] ?? null,
            'is_active' => true,
            'metadata' => [
                'polar_product' => $polarProduct,
                'polar_price' => $polarPrice,
            ],
        ]);
    }

    /**
     * Update a product in Polar and locally.
     */
    public function updateProduct(PolarProduct $product, array $data): PolarProduct
    {
        // Update in Polar if we have the product ID
        if ($product->polar_product_id) {
            $updatePayload = [];

            if (isset($data['name'])) {
                $updatePayload['name'] = $data['name'];
            }
            if (isset($data['description'])) {
                $updatePayload['description'] = $data['description'];
            }

            if (!empty($updatePayload)) {
                $this->apiRequest('patch', '/v1/products/' . $product->polar_product_id, $updatePayload);
            }
        }

        // Update locally
        $product->update([
            'name' => $data['name'] ?? $product->name,
            'description' => $data['description'] ?? $product->description,
            'is_active' => $data['is_active'] ?? $product->is_active,
        ]);

        return $product->fresh();
    }

    /**
     * Archive a product (soft delete locally, archive in Polar).
     */
    public function archiveProduct(PolarProduct $product): bool
    {
        try {
            if ($product->polar_product_id) {
                $this->apiRequest('patch', '/v1/products/' . $product->polar_product_id, [
                    'is_archived' => true,
                ]);
            }

            $product->update(['is_active' => false]);
            $product->delete();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to archive Polar product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create a checkout session for a product.
     */
    public function createCheckoutSession(PolarProduct $product, array $options = []): array
    {
        $checkoutPayload = [
            'product_price_id' => $product->polar_price_id,
            'success_url' => $options['success_url'] ?? url('/checkout/success'),
            'cancel_url' => $options['cancel_url'] ?? url('/checkout/cancel'),
        ];

        if (!empty($options['customer_email'])) {
            $checkoutPayload['customer_email'] = $options['customer_email'];
        }

        if (!empty($options['customer_name'])) {
            $checkoutPayload['customer_name'] = $options['customer_name'];
        }

        if (!empty($options['metadata'])) {
            $checkoutPayload['metadata'] = $options['metadata'];
        }

        $checkout = $this->apiRequest('post', '/v1/checkouts', $checkoutPayload);

        Log::info('Polar checkout session created', [
            'product_id' => $product->id,
            'checkout_id' => $checkout['id'] ?? null,
        ]);

        return $checkout;
    }

    /**
     * Get checkout URL for a product.
     */
    public function getCheckoutUrl(PolarProduct $product, array $options = []): ?string
    {
        try {
            $checkout = $this->createCheckoutSession($product, $options);
            return $checkout['url'] ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to get Polar checkout URL', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            Log::warning('Polar webhook secret not configured');
            return false;
        }

        // Polar uses HMAC-SHA256 for webhook signatures
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle checkout.completed event.
     */
    public function handleCheckoutCompleted(array $eventData): ?PolarTransaction
    {
        $checkout = $eventData['data'] ?? [];

        // Find the product by polar_price_id
        $product = PolarProduct::where('polar_price_id', $checkout['product_price_id'] ?? null)->first();

        // Find subscriber by email if exists
        $subscriber = null;
        if (!empty($checkout['customer_email'])) {
            $subscriber = Subscriber::where('email', $checkout['customer_email'])->first();
        }

        $transaction = PolarTransaction::create([
            'user_id' => $product?->user_id ?? 1, // Default to first user if product not found
            'polar_product_id' => $product?->id,
            'polar_checkout_id' => $checkout['id'] ?? null,
            'polar_order_id' => $checkout['order_id'] ?? null,
            'polar_customer_id' => $checkout['customer_id'] ?? null,
            'customer_email' => $checkout['customer_email'] ?? null,
            'customer_name' => $checkout['customer_name'] ?? null,
            'amount' => $checkout['amount'] ?? 0,
            'currency' => strtoupper($checkout['currency'] ?? 'USD'),
            'status' => 'completed',
            'type' => $product?->type === 'recurring' ? 'subscription' : 'one_time',
            'subscriber_id' => $subscriber?->id,
            'metadata' => $checkout,
        ]);

        Log::info('Polar checkout completed', [
            'transaction_id' => $transaction->id,
            'checkout_id' => $checkout['id'] ?? null,
        ]);

        return $transaction;
    }

    /**
     * Handle order.paid event.
     */
    public function handleOrderPaid(array $eventData): ?PolarTransaction
    {
        $order = $eventData['data'] ?? [];

        // Check if transaction already exists
        $existingTransaction = PolarTransaction::where('polar_order_id', $order['id'] ?? null)->first();
        if ($existingTransaction) {
            $existingTransaction->update(['status' => 'completed']);
            return $existingTransaction;
        }

        // Find the product
        $product = PolarProduct::where('polar_product_id', $order['product_id'] ?? null)->first();

        // Find subscriber by email
        $subscriber = null;
        if (!empty($order['customer']['email'])) {
            $subscriber = Subscriber::where('email', $order['customer']['email'])->first();
        }

        $transaction = PolarTransaction::create([
            'user_id' => $product?->user_id ?? 1,
            'polar_product_id' => $product?->id,
            'polar_order_id' => $order['id'] ?? null,
            'polar_customer_id' => $order['customer_id'] ?? null,
            'customer_email' => $order['customer']['email'] ?? null,
            'customer_name' => $order['customer']['name'] ?? null,
            'amount' => $order['amount'] ?? 0,
            'currency' => strtoupper($order['currency'] ?? 'USD'),
            'status' => 'completed',
            'type' => 'one_time',
            'subscriber_id' => $subscriber?->id,
            'metadata' => $order,
        ]);

        Log::info('Polar order paid', [
            'transaction_id' => $transaction->id,
            'order_id' => $order['id'] ?? null,
        ]);

        return $transaction;
    }

    /**
     * Handle subscription.created event.
     */
    public function handleSubscriptionCreated(array $eventData): ?PolarTransaction
    {
        $subscription = $eventData['data'] ?? [];

        $product = PolarProduct::where('polar_product_id', $subscription['product_id'] ?? null)->first();

        $subscriber = null;
        if (!empty($subscription['customer']['email'])) {
            $subscriber = Subscriber::where('email', $subscription['customer']['email'])->first();
        }

        $transaction = PolarTransaction::create([
            'user_id' => $product?->user_id ?? 1,
            'polar_product_id' => $product?->id,
            'polar_subscription_id' => $subscription['id'] ?? null,
            'polar_customer_id' => $subscription['customer_id'] ?? null,
            'customer_email' => $subscription['customer']['email'] ?? null,
            'customer_name' => $subscription['customer']['name'] ?? null,
            'amount' => $subscription['amount'] ?? 0,
            'currency' => strtoupper($subscription['currency'] ?? 'USD'),
            'status' => 'completed',
            'type' => 'subscription',
            'subscriber_id' => $subscriber?->id,
            'metadata' => $subscription,
        ]);

        Log::info('Polar subscription created', [
            'transaction_id' => $transaction->id,
            'subscription_id' => $subscription['id'] ?? null,
        ]);

        return $transaction;
    }

    /**
     * Handle order.refunded event.
     */
    public function handleOrderRefunded(array $eventData): ?PolarTransaction
    {
        $refund = $eventData['data'] ?? [];

        $transaction = PolarTransaction::where('polar_order_id', $refund['order_id'] ?? null)->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);

            Log::info('Polar order refunded', [
                'transaction_id' => $transaction->id,
                'order_id' => $refund['order_id'] ?? null,
            ]);
        }

        return $transaction;
    }

    /**
     * List products from Polar API.
     */
    public function listProducts(): array
    {
        return $this->apiRequest('get', '/v1/products');
    }

    /**
     * Get organization info from Polar.
     */
    public function getOrganization(): array
    {
        return $this->apiRequest('get', '/v1/organizations');
    }

    /**
     * Test the API connection.
     */
    public function testConnection(): bool
    {
        try {
            $this->getOrganization();
            return true;
        } catch (\Exception $e) {
            Log::error('Polar connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

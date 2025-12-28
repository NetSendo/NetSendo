<?php

namespace App\Services;

use App\Http\Controllers\StripeSettingsController;
use App\Models\StripeProduct;
use App\Models\StripeTransaction;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\WebhookSignature;

class StripeService
{
    private array $settings;

    public function __construct()
    {
        $this->settings = StripeSettingsController::getStripeSettings();

        if (!empty($this->settings['secret_key'])) {
            Stripe::setApiKey($this->settings['secret_key']);
        }
    }

    /**
     * Create a product in Stripe and save it locally.
     */
    public function createProduct(int $userId, array $data): StripeProduct
    {
        try {
            // Create product in Stripe
            $stripeProduct = Product::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'metadata' => [
                    'netsendo_user_id' => $userId,
                ],
            ]);

            // Create price in Stripe
            $priceData = [
                'product' => $stripeProduct->id,
                'unit_amount' => $data['price'],
                'currency' => strtolower($data['currency'] ?? 'pln'),
            ];

            if (($data['type'] ?? 'one_time') === 'subscription') {
                $priceData['recurring'] = [
                    'interval' => $data['interval'] ?? 'month',
                ];
            }

            $stripePrice = Price::create($priceData);

            // Create local product
            return StripeProduct::create([
                'user_id' => $userId,
                'stripe_product_id' => $stripeProduct->id,
                'stripe_price_id' => $stripePrice->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'currency' => strtoupper($data['currency'] ?? 'PLN'),
                'type' => $data['type'] ?? 'one_time',
                'is_active' => true,
                'metadata' => $data['metadata'] ?? null,
            ]);

        } catch (ApiErrorException $e) {
            Log::error('Stripe product creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Update a product in Stripe and locally.
     */
    public function updateProduct(StripeProduct $product, array $data): StripeProduct
    {
        try {
            // Update product in Stripe
            if ($product->stripe_product_id) {
                Product::update($product->stripe_product_id, [
                    'name' => $data['name'] ?? $product->name,
                    'description' => $data['description'] ?? $product->description,
                ]);
            }

            // If price changed, create a new price (Stripe prices are immutable)
            if (isset($data['price']) && $data['price'] !== $product->price) {
                $newPrice = Price::create([
                    'product' => $product->stripe_product_id,
                    'unit_amount' => $data['price'],
                    'currency' => strtolower($data['currency'] ?? $product->currency),
                ]);

                // Deactivate old price
                if ($product->stripe_price_id) {
                    Price::update($product->stripe_price_id, ['active' => false]);
                }

                $data['stripe_price_id'] = $newPrice->id;
            }

            // Update local product
            $product->update([
                'name' => $data['name'] ?? $product->name,
                'description' => $data['description'] ?? $product->description,
                'price' => $data['price'] ?? $product->price,
                'currency' => isset($data['currency']) ? strtoupper($data['currency']) : $product->currency,
                'is_active' => $data['is_active'] ?? $product->is_active,
                'stripe_price_id' => $data['stripe_price_id'] ?? $product->stripe_price_id,
                'metadata' => $data['metadata'] ?? $product->metadata,
            ]);

            return $product->fresh();

        } catch (ApiErrorException $e) {
            Log::error('Stripe product update failed', [
                'error' => $e->getMessage(),
                'product_id' => $product->id,
            ]);
            throw $e;
        }
    }

    /**
     * Archive a product (soft delete locally, archive in Stripe).
     */
    public function archiveProduct(StripeProduct $product): bool
    {
        try {
            // Archive in Stripe
            if ($product->stripe_product_id) {
                Product::update($product->stripe_product_id, ['active' => false]);
            }

            // Soft delete locally
            $product->update(['is_active' => false]);
            $product->delete();

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Stripe product archive failed', [
                'error' => $e->getMessage(),
                'product_id' => $product->id,
            ]);
            throw $e;
        }
    }

    /**
     * Create a checkout session for a product.
     */
    public function createCheckoutSession(StripeProduct $product, array $options = []): Session
    {
        try {
            $sessionData = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $product->stripe_price_id,
                    'quantity' => 1,
                ]],
                'mode' => $product->type === 'subscription' ? 'subscription' : 'payment',
                'success_url' => $options['success_url'] ?? config('app.url') . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $options['cancel_url'] ?? config('app.url') . '/checkout/cancel',
                'metadata' => [
                    'netsendo_product_id' => $product->id,
                    'netsendo_user_id' => $product->user_id,
                ],
            ];

            if (isset($options['customer_email'])) {
                $sessionData['customer_email'] = $options['customer_email'];
            }

            return Session::create($sessionData);

        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout session creation failed', [
                'error' => $e->getMessage(),
                'product_id' => $product->id,
            ]);
            throw $e;
        }
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): \Stripe\Event
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            $this->settings['webhook_secret'] ?? ''
        );
    }

    /**
     * Handle checkout.session.completed event.
     */
    public function handleCheckoutCompleted(\Stripe\Event $event): ?StripeTransaction
    {
        $session = $event->data->object;

        $metadata = $session->metadata ?? [];
        $productId = $metadata['netsendo_product_id'] ?? null;
        $userId = $metadata['netsendo_user_id'] ?? null;

        if (!$productId || !$userId) {
            Log::warning('Stripe webhook missing metadata', [
                'session_id' => $session->id,
                'metadata' => $metadata,
            ]);
            return null;
        }

        $product = StripeProduct::find($productId);
        if (!$product) {
            Log::warning('Stripe webhook product not found', [
                'product_id' => $productId,
            ]);
            return null;
        }

        // Try to link to subscriber by email
        $subscriberId = null;
        $customerEmail = $session->customer_details->email ?? $session->customer_email ?? null;

        if ($customerEmail) {
            $subscriber = Subscriber::where('email', $customerEmail)->first();
            $subscriberId = $subscriber?->id;
        }

        // Create transaction
        $transaction = StripeTransaction::create([
            'user_id' => $userId,
            'stripe_product_id' => $productId,
            'subscriber_id' => $subscriberId,
            'stripe_session_id' => $session->id,
            'stripe_payment_intent_id' => $session->payment_intent ?? null,
            'customer_email' => $customerEmail,
            'customer_name' => $session->customer_details->name ?? null,
            'amount' => $session->amount_total,
            'currency' => strtoupper($session->currency),
            'status' => 'completed',
            'metadata' => [
                'payment_status' => $session->payment_status,
                'mode' => $session->mode,
            ],
        ]);

        Log::info('Stripe transaction created', [
            'transaction_id' => $transaction->id,
            'product_id' => $productId,
            'amount' => $session->amount_total,
        ]);

        return $transaction;
    }

    /**
     * Handle charge.refunded event.
     */
    public function handleChargeRefunded(\Stripe\Event $event): ?StripeTransaction
    {
        $charge = $event->data->object;

        // Find transaction by payment intent
        $transaction = StripeTransaction::where('stripe_payment_intent_id', $charge->payment_intent)->first();

        if (!$transaction) {
            Log::warning('Stripe refund: transaction not found', [
                'payment_intent' => $charge->payment_intent,
            ]);
            return null;
        }

        $transaction->update([
            'status' => 'refunded',
            'metadata' => array_merge($transaction->metadata ?? [], [
                'refund_id' => $charge->refunds->data[0]->id ?? null,
                'refunded_at' => now()->toISOString(),
            ]),
        ]);

        Log::info('Stripe transaction refunded', [
            'transaction_id' => $transaction->id,
        ]);

        return $transaction;
    }

    /**
     * Get checkout URL for a product.
     */
    public function getCheckoutUrl(StripeProduct $product, array $options = []): string
    {
        $session = $this->createCheckoutSession($product, $options);
        return $session->url;
    }

    /**
     * Check if Stripe is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->settings['publishable_key']) && !empty($this->settings['secret_key']);
    }

    /**
     * Get the publishable key for frontend use.
     */
    public function getPublishableKey(): ?string
    {
        return $this->settings['publishable_key'] ?? null;
    }
}

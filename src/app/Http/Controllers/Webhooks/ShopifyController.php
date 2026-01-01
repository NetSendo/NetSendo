<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ContactList;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyController extends Controller
{
    /**
     * Handle Shopify webhook
     *
     * Shopify sends webhooks for various events. We support:
     * - orders/paid: When an order payment is completed
     * - orders/create: When an order is created
     * - customers/create: When a new customer is created
     *
     * Expected headers:
     * - X-Shopify-Topic: The webhook topic (e.g., orders/paid)
     * - X-Shopify-Hmac-SHA256: HMAC signature for verification
     * - X-Shopify-Shop-Domain: The shop domain
     */
    public function handle(Request $request)
    {
        // Get topic from header
        $topic = $request->header('X-Shopify-Topic');

        if (!$topic) {
            return response()->json(['error' => 'Missing topic header'], 400);
        }

        // Validate API key (Bearer token)
        $apiKey = $request->bearerToken();
        if (!$apiKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validKey = \App\Models\ApiKey::where('key', hash('sha256', $apiKey))
            ->where('is_active', true)
            ->first();

        if (!$validKey) {
            Log::warning('Shopify webhook: Invalid API key', [
                'ip' => $request->ip(),
                'topic' => $topic,
            ]);
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        // Optional: Verify HMAC if webhook secret is configured
        $hmacHeader = $request->header('X-Shopify-Hmac-SHA256');
        if ($hmacHeader) {
            $webhookSecret = config('services.shopify.webhook_secret');
            if ($webhookSecret && !$this->verifyHmac($request->getContent(), $hmacHeader, $webhookSecret)) {
                Log::warning('Shopify webhook: Invalid HMAC signature', [
                    'ip' => $request->ip(),
                    'topic' => $topic,
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->all();

        try {
            $result = match ($topic) {
                'orders/paid', 'orders/create' => $this->handleOrder($payload, $validKey->user_id, $topic),
                'customers/create' => $this->handleCustomer($payload, $validKey->user_id),
                default => ['message' => 'Topic not handled'],
            };

            return response()->json([
                'success' => true,
                'topic' => $topic,
                ...$result,
            ]);
        } catch (\Exception $e) {
            Log::error('Shopify webhook error', [
                'error' => $e->getMessage(),
                'topic' => $topic,
                'payload' => $payload,
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify Shopify HMAC signature
     */
    protected function verifyHmac(string $data, string $hmacHeader, string $secret): bool
    {
        $calculatedHmac = base64_encode(hash_hmac('sha256', $data, $secret, true));
        return hash_equals($calculatedHmac, $hmacHeader);
    }

    /**
     * Handle order events (orders/paid, orders/create)
     */
    protected function handleOrder(array $data, int $userId, string $topic): array
    {
        // Extract customer data from order
        $customer = $data['customer'] ?? [];
        $email = $data['email'] ?? $customer['email'] ?? null;

        if (!$email) {
            throw new \Exception('No email found in order data');
        }

        // Get list_id from request meta or use default
        $listId = $data['netsendo_list_id'] ?? null;

        if (!$listId) {
            throw new \Exception('No list_id provided. Include netsendo_list_id in webhook configuration.');
        }

        $list = ContactList::where('id', $listId)
            ->where('user_id', $userId)
            ->first();

        if (!$list) {
            throw new \Exception('List not found or access denied');
        }

        // Extract name
        $firstName = $data['billing_address']['first_name']
            ?? $customer['first_name']
            ?? $data['shipping_address']['first_name']
            ?? '';
        $lastName = $data['billing_address']['last_name']
            ?? $customer['last_name']
            ?? $data['shipping_address']['last_name']
            ?? '';

        // Check if subscriber exists
        $subscriber = Subscriber::where('email', $email)
            ->where('contact_list_id', $list->id)
            ->first();

        $isNew = false;

        if ($subscriber) {
            $subscriber->update([
                'first_name' => $firstName ?: $subscriber->first_name,
                'last_name' => $lastName ?: $subscriber->last_name,
                'status' => 'active',
            ]);
        } else {
            $subscriber = Subscriber::create([
                'contact_list_id' => $list->id,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'status' => 'active',
                'source' => 'shopify',
                'subscribed_at' => now(),
            ]);
            $isNew = true;
        }

        // Store Shopify-specific data in custom fields
        $customFields = [];
        if (!empty($data['id'])) {
            $customFields['shopify_order_id'] = $data['id'];
        }
        if (!empty($data['order_number'])) {
            $customFields['shopify_order_number'] = $data['order_number'];
        }
        if (!empty($data['total_price'])) {
            $customFields['shopify_order_total'] = $data['total_price'];
        }
        if (!empty($data['currency'])) {
            $customFields['shopify_currency'] = $data['currency'];
        }
        if (!empty($customer['id'])) {
            $customFields['shopify_customer_id'] = $customer['id'];
        }

        // Save custom fields if model supports it
        if (!empty($customFields) && method_exists($subscriber, 'saveCustomFields')) {
            $subscriber->saveCustomFields($customFields);
        }

        Log::info('Shopify subscriber processed', [
            'topic' => $topic,
            'email' => $email,
            'list_id' => $list->id,
            'subscriber_id' => $subscriber->id,
            'is_new' => $isNew,
            'order_id' => $data['id'] ?? null,
        ]);

        return [
            'subscriber_id' => $subscriber->id,
            'is_new' => $isNew,
        ];
    }

    /**
     * Handle customer create event
     */
    protected function handleCustomer(array $data, int $userId): array
    {
        $email = $data['email'] ?? null;

        if (!$email) {
            throw new \Exception('No email found in customer data');
        }

        $listId = $data['netsendo_list_id'] ?? null;

        if (!$listId) {
            throw new \Exception('No list_id provided. Include netsendo_list_id in webhook configuration.');
        }

        $list = ContactList::where('id', $listId)
            ->where('user_id', $userId)
            ->first();

        if (!$list) {
            throw new \Exception('List not found or access denied');
        }

        // Check if subscriber exists
        $subscriber = Subscriber::where('email', $email)
            ->where('contact_list_id', $list->id)
            ->first();

        $isNew = false;

        if ($subscriber) {
            $subscriber->update([
                'first_name' => $data['first_name'] ?? $subscriber->first_name,
                'last_name' => $data['last_name'] ?? $subscriber->last_name,
                'status' => 'active',
            ]);
        } else {
            $subscriber = Subscriber::create([
                'contact_list_id' => $list->id,
                'email' => $email,
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'status' => 'active',
                'source' => 'shopify',
                'subscribed_at' => now(),
            ]);
            $isNew = true;
        }

        // Store Shopify customer ID
        if (!empty($data['id']) && method_exists($subscriber, 'saveCustomFields')) {
            $subscriber->saveCustomFields([
                'shopify_customer_id' => $data['id'],
            ]);
        }

        Log::info('Shopify customer processed', [
            'email' => $email,
            'list_id' => $list->id,
            'subscriber_id' => $subscriber->id,
            'is_new' => $isNew,
            'customer_id' => $data['id'] ?? null,
        ]);

        return [
            'subscriber_id' => $subscriber->id,
            'is_new' => $isNew,
        ];
    }
}

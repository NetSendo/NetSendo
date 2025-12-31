<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ContactList;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WooCommerceController extends Controller
{
    /**
     * Handle WooCommerce webhook
     *
     * Expected payload:
     * {
     *   "event": "order_completed" | "order_pending",
     *   "email": "customer@example.com",
     *   "first_name": "John",
     *   "last_name": "Doe",
     *   "list_id": 123,
     *   "order_id": 456,
     *   "product_id": 789,
     *   "product_name": "Product Name",
     *   "order_total": "99.00",
     *   "redirect_url": "https://example.com/thank-you" (optional)
     * }
     */
    public function handle(Request $request)
    {
        // Validate API key
        $apiKey = $request->bearerToken();
        if (!$apiKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validKey = \App\Models\ApiKey::where('key', hash('sha256', $apiKey))
            ->where('is_active', true)
            ->first();

        if (!$validKey) {
            Log::warning('WooCommerce webhook: Invalid API key', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        // Validate request
        $validated = $request->validate([
            'event' => 'required|in:order_completed,order_pending',
            'email' => 'required|email',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'list_id' => 'required|integer',
            'order_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'product_name' => 'nullable|string|max:255',
            'order_total' => 'nullable|string|max:50',
            'order_status' => 'nullable|string|max:50',
        ]);

        try {
            $result = $this->processWebhook($validated, $validKey->user_id);

            return response()->json([
                'success' => true,
                'message' => 'Subscriber processed successfully',
                'subscriber_id' => $result['subscriber_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('WooCommerce webhook error', [
                'error' => $e->getMessage(),
                'payload' => $validated,
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process the webhook and add subscriber to list
     */
    protected function processWebhook(array $data, int $userId): array
    {
        // Find the list
        $list = ContactList::where('id', $data['list_id'])
            ->where('user_id', $userId)
            ->first();

        if (!$list) {
            throw new \Exception('List not found or access denied');
        }

        // Check if subscriber already exists on this list
        $subscriber = Subscriber::where('email', $data['email'])
            ->where('contact_list_id', $list->id)
            ->first();

        $isNew = false;

        if ($subscriber) {
            // Update existing subscriber
            $subscriber->update([
                'first_name' => $data['first_name'] ?? $subscriber->first_name,
                'last_name' => $data['last_name'] ?? $subscriber->last_name,
                'status' => 'active',
            ]);
        } else {
            // Create new subscriber
            $subscriber = Subscriber::create([
                'contact_list_id' => $list->id,
                'email' => $data['email'],
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'status' => 'active',
                'source' => 'woocommerce',
                'subscribed_at' => now(),
            ]);
            $isNew = true;
        }

        // Store WooCommerce-specific data in custom fields if available
        $customFields = [];
        if (!empty($data['order_id'])) {
            $customFields['wc_order_id'] = $data['order_id'];
        }
        if (!empty($data['product_id'])) {
            $customFields['wc_product_id'] = $data['product_id'];
        }
        if (!empty($data['product_name'])) {
            $customFields['wc_product_name'] = $data['product_name'];
        }
        if (!empty($data['order_total'])) {
            $customFields['wc_order_total'] = $data['order_total'];
        }
        if (!empty($data['order_status'])) {
            $customFields['wc_order_status'] = $data['order_status'];
        }

        // Save custom fields if model supports it
        if (!empty($customFields) && method_exists($subscriber, 'saveCustomFields')) {
            $subscriber->saveCustomFields($customFields);
        }

        Log::info('WooCommerce subscriber processed', [
            'event' => $data['event'],
            'email' => $data['email'],
            'list_id' => $list->id,
            'subscriber_id' => $subscriber->id,
            'is_new' => $isNew,
        ]);

        return [
            'subscriber_id' => $subscriber->id,
            'is_new' => $isNew,
        ];
    }
}

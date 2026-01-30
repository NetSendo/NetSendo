<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Automation\AutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for handling purchase webhook events from external e-commerce platforms.
 * This enables AutoTag Pro to trigger automations based on actual purchases.
 */
class PurchaseWebhookController extends Controller
{
    protected AutomationService $automationService;

    public function __construct(AutomationService $automationService)
    {
        $this->automationService = $automationService;
    }

    /**
     * Handle an incoming purchase webhook.
     *
     * POST /api/v1/webhooks/purchase
     *
     * Expected payload:
     * {
     *     "event": "purchase",
     *     "subscriber_email": "client@example.com",
     *     "data": {
     *         "order_id": "12345",
     *         "value": 997.00,
     *         "product_id": "netsendo_pro",
     *         "product_category": "software",
     *         "quantity": 1,
     *         "currency": "PLN"
     *     }
     * }
     */
    public function __invoke(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event' => 'required|string|in:purchase',
            'subscriber_email' => 'required|email',
            'data' => 'required|array',
            'data.order_id' => 'required|string',
            'data.value' => 'required|numeric|min:0',
            'data.product_id' => 'nullable|string',
            'data.product_category' => 'nullable|string',
            'data.quantity' => 'nullable|integer|min:1',
            'data.currency' => 'nullable|string|max:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $email = $request->input('subscriber_email');
        $data = $request->input('data');

        // Find subscriber by email
        $subscriber = Subscriber::where('email', $email)->first();

        if (!$subscriber) {
            Log::info("Purchase webhook: subscriber not found for email: {$email}");

            return response()->json([
                'success' => false,
                'error' => 'Subscriber not found',
                'email' => $email,
            ], 404);
        }

        // Build context for automation
        $context = [
            'subscriber_id' => $subscriber->id,
            'subscriber' => $subscriber,
            'user_id' => $subscriber->user_id,
            'order_id' => $data['order_id'],
            'value' => (float) $data['value'],
            'product_id' => $data['product_id'] ?? null,
            'product_category' => $data['product_category'] ?? null,
            'quantity' => (int) ($data['quantity'] ?? 1),
            'currency' => $data['currency'] ?? 'PLN',
            'raw_data' => $data,
        ];

        try {
            // Trigger automation for purchase event
            $this->automationService->processEvent('purchase', $context);

            Log::info("Purchase webhook processed for subscriber: {$subscriber->id}", [
                'order_id' => $data['order_id'],
                'value' => $data['value'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase event processed',
                'subscriber_id' => $subscriber->id,
            ]);

        } catch (\Exception $e) {
            Log::error("Purchase webhook error: " . $e->getMessage(), [
                'email' => $email,
                'order_id' => $data['order_id'],
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Processing failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

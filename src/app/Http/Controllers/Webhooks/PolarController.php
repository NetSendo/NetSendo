<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\PolarService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PolarController extends Controller
{
    public function __construct(
        private PolarService $polarService,
        private WebhookDispatcher $webhookDispatcher
    ) {}

    /**
     * Handle incoming Polar webhook.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Polar-Signature') ?? $request->header('X-Polar-Signature');

        if (!$signature) {
            Log::warning('Polar webhook: missing signature header');
            return response('Missing signature', 400);
        }

        if (!$this->polarService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Polar webhook: invalid signature');
            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);

        if (!$event) {
            Log::warning('Polar webhook: invalid JSON payload');
            return response('Invalid payload', 400);
        }

        $eventType = $event['type'] ?? null;

        Log::info('Polar webhook received', [
            'type' => $eventType,
            'id' => $event['id'] ?? null,
        ]);

        try {
            $this->processEvent($eventType, $event);
        } catch (\Exception $e) {
            Log::error('Polar webhook: processing failed', [
                'type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            // Still return 200 to prevent Polar from retrying
        }

        return response('OK', 200);
    }

    /**
     * Process the Polar event.
     */
    private function processEvent(string $eventType, array $event): void
    {
        switch ($eventType) {
            case 'checkout.created':
                Log::info('Polar checkout created', ['checkout_id' => $event['data']['id'] ?? null]);
                break;

            case 'checkout.updated':
                $transaction = $this->polarService->handleCheckoutCompleted($event);
                if ($transaction) {
                    $this->dispatchNetSendoWebhook('polar.checkout_completed', $transaction);
                }
                break;

            case 'order.created':
                Log::info('Polar order created', ['order_id' => $event['data']['id'] ?? null]);
                break;

            case 'order.paid':
                $transaction = $this->polarService->handleOrderPaid($event);
                if ($transaction) {
                    $this->dispatchNetSendoWebhook('polar.order_paid', $transaction);
                }
                break;

            case 'order.refunded':
                $transaction = $this->polarService->handleOrderRefunded($event);
                if ($transaction) {
                    $this->dispatchNetSendoWebhook('polar.order_refunded', $transaction);
                }
                break;

            case 'subscription.created':
                $transaction = $this->polarService->handleSubscriptionCreated($event);
                if ($transaction) {
                    $this->dispatchNetSendoWebhook('polar.subscription_created', $transaction);
                }
                break;

            case 'subscription.updated':
                Log::info('Polar subscription updated', ['subscription_id' => $event['data']['id'] ?? null]);
                break;

            case 'subscription.active':
                Log::info('Polar subscription active', ['subscription_id' => $event['data']['id'] ?? null]);
                break;

            case 'subscription.canceled':
                Log::info('Polar subscription canceled', ['subscription_id' => $event['data']['id'] ?? null]);
                $this->dispatchNetSendoWebhook('polar.subscription_canceled', $event['data'] ?? []);
                break;

            case 'subscription.revoked':
                Log::info('Polar subscription revoked', ['subscription_id' => $event['data']['id'] ?? null]);
                $this->dispatchNetSendoWebhook('polar.subscription_revoked', $event['data'] ?? []);
                break;

            case 'customer.created':
                Log::info('Polar customer created', ['customer_id' => $event['data']['id'] ?? null]);
                break;

            case 'customer.updated':
                Log::info('Polar customer updated', ['customer_id' => $event['data']['id'] ?? null]);
                break;

            case 'benefit.grant.created':
                Log::info('Polar benefit grant created', ['benefit_id' => $event['data']['id'] ?? null]);
                $this->dispatchNetSendoWebhook('polar.benefit_granted', $event['data'] ?? []);
                break;

            case 'benefit.grant.revoked':
                Log::info('Polar benefit grant revoked', ['benefit_id' => $event['data']['id'] ?? null]);
                $this->dispatchNetSendoWebhook('polar.benefit_revoked', $event['data'] ?? []);
                break;

            default:
                Log::info('Polar webhook: unhandled event type', ['type' => $eventType]);
                break;
        }
    }

    /**
     * Dispatch event to NetSendo webhooks.
     */
    private function dispatchNetSendoWebhook(string $eventName, $transaction): void
    {
        $data = [];

        if ($transaction instanceof \App\Models\PolarTransaction) {
            $data = [
                'transaction_id' => $transaction->id,
                'product' => $transaction->product ? [
                    'id' => $transaction->product->id,
                    'name' => $transaction->product->name,
                    'polar_product_id' => $transaction->product->polar_product_id,
                ] : null,
                'customer_email' => $transaction->customer_email,
                'customer_name' => $transaction->customer_name,
                'amount' => $transaction->amount,
                'formatted_amount' => $transaction->formatted_amount,
                'currency' => $transaction->currency,
                'status' => $transaction->status,
                'subscriber' => $transaction->subscriber ? [
                    'id' => $transaction->subscriber->id,
                    'email' => $transaction->subscriber->email,
                    'first_name' => $transaction->subscriber->first_name,
                    'last_name' => $transaction->subscriber->last_name,
                ] : null,
                'polar_checkout_id' => $transaction->polar_checkout_id,
                'polar_order_id' => $transaction->polar_order_id,
                'polar_subscription_id' => $transaction->polar_subscription_id,
                'created_at' => $transaction->created_at->toISOString(),
            ];

            $this->webhookDispatcher->dispatch($transaction->user_id, $eventName, $data);
        } elseif (is_array($transaction)) {
            // For events without transaction record
            $data = $transaction;
            // Dispatch to all users (or could be based on product owner)
            Log::info('Polar webhook dispatched', ['event' => $eventName, 'data' => $data]);
        }
    }
}

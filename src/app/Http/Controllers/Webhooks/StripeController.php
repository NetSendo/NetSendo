<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\AffiliateConversionService;
use App\Services\SalesFunnelService;
use App\Services\StripeService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private WebhookDispatcher $webhookDispatcher,
        private SalesFunnelService $salesFunnelService,
        private AffiliateConversionService $affiliateConversionService
    ) {}

    /**
     * Handle incoming Stripe webhook.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (!$sigHeader) {
            Log::warning('Stripe webhook: missing signature header');
            return response('Missing signature', 400);
        }

        try {
            $event = $this->stripeService->verifyWebhookSignature($payload, $sigHeader);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook: invalid signature', [
                'error' => $e->getMessage(),
            ]);
            return response('Invalid signature', 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook: verification failed', [
                'error' => $e->getMessage(),
            ]);
            return response('Verification failed', 400);
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        try {
            $this->processEvent($event);
        } catch (\Exception $e) {
            Log::error('Stripe webhook: processing failed', [
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);
            // Still return 200 to prevent Stripe from retrying
        }

        return response('OK', 200);
    }

    /**
     * Process the Stripe event.
     */
    private function processEvent(\Stripe\Event $event): void
    {
        switch ($event->type) {
            case 'checkout.session.completed':
                $transaction = $this->stripeService->handleCheckoutCompleted($event);
                if ($transaction) {
                    $this->dispatchNetSendoWebhook('stripe.purchase_completed', $transaction);

                    // Process sales funnel actions (subscribe to list, add tag)
                    if ($transaction->product?->sales_funnel_id) {
                        $this->salesFunnelService->processPurchase(
                            $transaction->product->salesFunnel,
                            $transaction->customer_email,
                            $transaction->customer_name
                        );
                    }

                    // Track affiliate conversion (purchase)
                    try {
                        $session = $event->data->object;
                        $this->affiliateConversionService->processStripeCheckoutSession(
                            $session,
                            $transaction->user_id
                        );
                    } catch (\Exception $e) {
                        Log::warning('Affiliate conversion tracking failed', [
                            'error' => $e->getMessage(),
                            'session_id' => $event->data->object->id ?? null,
                        ]);
                    }
                }
                break;

            case 'charge.refunded':
                $transaction = $this->stripeService->handleChargeRefunded($event);
                if ($transaction) {
                    $this->dispatchNetSendoWebhook('stripe.payment_refunded', $transaction);

                    // Record affiliate refund
                    try {
                        $this->affiliateConversionService->recordRefund(
                            $transaction->stripe_session_id,
                            $transaction->amount
                        );
                    } catch (\Exception $e) {
                        Log::warning('Affiliate refund tracking failed', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                break;

            case 'payment_intent.succeeded':
                // Handled by checkout.session.completed
                Log::info('Stripe payment_intent.succeeded (handled via checkout)', [
                    'payment_intent' => $event->data->object->id,
                ]);
                break;

            default:
                Log::info('Stripe webhook: unhandled event type', [
                    'type' => $event->type,
                ]);
                break;
        }
    }

    /**
     * Dispatch event to NetSendo webhooks.
     */
    private function dispatchNetSendoWebhook(string $eventName, $transaction): void
    {
        $data = [
            'transaction_id' => $transaction->id,
            'product' => $transaction->product ? [
                'id' => $transaction->product->id,
                'name' => $transaction->product->name,
                'stripe_product_id' => $transaction->product->stripe_product_id,
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
            'stripe_session_id' => $transaction->stripe_session_id,
            'stripe_payment_intent_id' => $transaction->stripe_payment_intent_id,
            'created_at' => $transaction->created_at->toISOString(),
        ];

        $this->webhookDispatcher->dispatch($transaction->user_id, $eventName, $data);
    }
}

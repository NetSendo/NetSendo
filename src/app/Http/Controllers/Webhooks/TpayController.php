<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\SalesFunnelService;
use App\Services\TpayService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TpayController extends Controller
{
    public function __construct(
        private TpayService $tpayService,
        private WebhookDispatcher $webhookDispatcher,
        private SalesFunnelService $salesFunnelService
    ) {}

    /**
     * Handle incoming Tpay webhook notification.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $jwsSignature = $request->header('X-JWS-Signature');

        // Verify JWS signature
        if ($jwsSignature) {
            if (!$this->tpayService->verifyJwsSignature($payload, $jwsSignature)) {
                Log::warning('Tpay webhook: invalid JWS signature');
                return response('FALSE', 400);
            }
        }

        // Parse notification data
        $data = $request->all();

        if (empty($data)) {
            Log::warning('Tpay webhook: empty payload');
            return response('FALSE', 400);
        }

        // Verify md5sum
        if (isset($data['md5sum'])) {
            if (!$this->tpayService->verifyMd5Sum($data)) {
                Log::warning('Tpay webhook: invalid md5sum', [
                    'tr_id' => $data['tr_id'] ?? null,
                ]);
                return response('FALSE', 400);
            }
        }

        Log::info('Tpay webhook received', [
            'tr_id' => $data['tr_id'] ?? null,
            'tr_status' => $data['tr_status'] ?? null,
            'tr_crc' => $data['tr_crc'] ?? null,
        ]);

        try {
            $this->processNotification($data);
        } catch (\Exception $e) {
            Log::error('Tpay webhook: processing failed', [
                'tr_id' => $data['tr_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            // Still return TRUE to prevent Tpay from retrying
        }

        // Tpay expects "TRUE" with HTTP 200
        return response('TRUE', 200);
    }

    /**
     * Process the Tpay notification.
     */
    private function processNotification(array $data): void
    {
        $trStatus = $data['tr_status'] ?? null;

        switch ($trStatus) {
            case 'TRUE':
            case 'PAID':
                $transaction = $this->tpayService->handleTransactionNotification($data);
                if ($transaction && $transaction->isCompleted()) {
                    $this->dispatchNetSendoWebhook('tpay.purchase_completed', $transaction);

                    // Process sales funnel actions (subscribe to list, add tag)
                    if ($transaction->product?->sales_funnel_id) {
                        $this->salesFunnelService->processPurchase(
                            $transaction->product->salesFunnel,
                            $transaction->customer_email,
                            $transaction->customer_name
                        );
                    }
                }
                break;

            case 'CHARGEBACK':
                $transaction = $this->tpayService->handleTransactionNotification($data);
                if ($transaction) {
                    $this->dispatchNetSendoWebhook('tpay.payment_chargeback', $transaction);
                }
                break;

            default:
                // Other statuses (e.g., pending updates)
                $transaction = $this->tpayService->handleTransactionNotification($data);
                if ($transaction) {
                    Log::info('Tpay webhook: status update', [
                        'tr_id' => $data['tr_id'] ?? null,
                        'status' => $trStatus,
                    ]);
                }
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
                'tpay_product_id' => $transaction->product->tpay_product_id,
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
            'tpay_transaction_id' => $transaction->tpay_transaction_id,
            'payment_method' => $transaction->payment_method,
            'created_at' => $transaction->created_at->toISOString(),
        ];

        $this->webhookDispatcher->dispatch($transaction->user_id, $eventName, $data);
    }
}

<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliateOffer;
use App\Models\AffiliateCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AffiliateConversionService
{
    public function __construct(
        private AffiliateTrackingService $trackingService,
        private AffiliateCommissionService $commissionService
    ) {}

    /**
     * Record a lead conversion from a form submission or lead magnet.
     */
    public function recordLead(
        Request $request,
        string $entityType,
        int $entityId,
        ?string $customerEmail = null,
        ?string $customerName = null,
        ?array $meta = null
    ): ?AffiliateConversion {
        $affiliateData = $this->getAffiliateFromRequest($request);

        if (!$affiliateData) {
            return null;
        }

        $conversion = AffiliateConversion::recordLead(
            affiliateId: $affiliateData['affiliate_id'],
            offerId: $affiliateData['offer_id'],
            clickId: $affiliateData['click_id'] ?? null,
            entityType: $entityType,
            entityId: $entityId,
            customerEmail: $customerEmail,
            customerName: $customerName,
            meta: $meta
        );

        Log::info('Affiliate lead conversion recorded', [
            'conversion_id' => $conversion->id,
            'affiliate_id' => $affiliateData['affiliate_id'],
        ]);

        return $conversion;
    }

    /**
     * Record a lead from form submission service.
     * Simplified wrapper for FormSubmissionService integration.
     */
    public function recordLeadFromRequest(
        Request $request,
        int $userId,
        string $customerEmail,
        array $meta = []
    ): ?AffiliateConversion {
        $affiliateData = $this->getAffiliateFromRequest($request);

        if (!$affiliateData) {
            return null;
        }

        // Verify the affiliate belongs to this user's program
        $affiliate = Affiliate::with('program')->find($affiliateData['affiliate_id']);
        if (!$affiliate || $affiliate->program->user_id !== $userId) {
            return null;
        }

        return AffiliateConversion::recordLead(
            affiliateId: $affiliateData['affiliate_id'],
            offerId: $affiliateData['offer_id'],
            clickId: $affiliateData['click_id'] ?? null,
            entityType: 'form_submission',
            entityId: $meta['form_id'] ?? 0,
            customerEmail: $customerEmail,
            customerName: null,
            meta: $meta
        );
    }

    /**
     * Process Stripe checkout session for affiliate tracking.
     * Called from StripeController webhook.
     */
    public function processStripeCheckoutSession(
        object $session,
        int $userId
    ): ?AffiliateConversion {
        // Try to get affiliate data from session metadata
        $affiliateId = $session->metadata->affiliate_id ?? null;
        $offerId = $session->metadata->offer_id ?? null;
        $clickId = $session->metadata->click_id ?? null;

        // If not in metadata, try to resolve from coupon code used
        if (!$affiliateId && !empty($session->discount?->coupon?->id)) {
            $promoCode = $session->discount->coupon->id;
            $couponData = $this->trackingService->resolveCouponToAffiliate($promoCode);
            if ($couponData) {
                $affiliateId = $couponData['affiliate_id'];
                $offerId = $couponData['offer_id'];
            }
        }

        if (!$affiliateId) {
            return null;
        }

        // Verify affiliate belongs to this user
        $affiliate = Affiliate::with('program')->find($affiliateId);
        if (!$affiliate || $affiliate->program->user_id !== $userId) {
            return null;
        }

        // Find or use first active offer
        if (!$offerId) {
            $offer = AffiliateOffer::where('program_id', $affiliate->program_id)
                ->where('is_active', true)
                ->first();
            $offerId = $offer?->id;
        }

        if (!$offerId) {
            return null;
        }

        $amount = ($session->amount_total ?? 0) / 100;
        $currency = strtoupper($session->currency ?? 'PLN');

        $conversion = AffiliateConversion::recordPurchase(
            affiliateId: $affiliateId,
            offerId: $offerId,
            clickId: $clickId ? (int) $clickId : null,
            amount: $amount,
            currency: $currency,
            entityType: 'stripe_checkout',
            entityId: 0,
            orderId: $session->id,
            customerEmail: $session->customer_email ?? $session->customer_details?->email,
            customerName: $session->customer_details?->name,
            meta: [
                'stripe_session_id' => $session->id,
                'stripe_payment_intent' => $session->payment_intent,
            ]
        );

        $this->commissionService->calculateCommissions($conversion);

        Log::info('Affiliate Stripe checkout conversion recorded', [
            'conversion_id' => $conversion->id,
            'stripe_session' => $session->id,
            'affiliate_id' => $affiliateId,
            'amount' => $amount,
        ]);

        return $conversion;
    }

    /**
     * Record a purchase conversion and generate commissions.
     */
    public function recordPurchase(
        Request $request,
        float $amount,
        string $currency,
        string $entityType,
        int $entityId,
        ?string $orderId = null,
        ?string $customerEmail = null,
        ?string $customerName = null,
        ?array $meta = null
    ): ?AffiliateConversion {
        $affiliateData = $this->getAffiliateFromRequest($request);

        if (!$affiliateData) {
            return null;
        }

        $conversion = AffiliateConversion::recordPurchase(
            affiliateId: $affiliateData['affiliate_id'],
            offerId: $affiliateData['offer_id'],
            clickId: $affiliateData['click_id'] ?? null,
            amount: $amount,
            currency: $currency,
            entityType: $entityType,
            entityId: $entityId,
            orderId: $orderId,
            customerEmail: $customerEmail,
            customerName: $customerName,
            meta: $meta
        );

        // Generate commissions for this conversion
        $this->commissionService->calculateCommissions($conversion);

        // If coupon was used, increment its usage
        if (!empty($affiliateData['coupon_id'])) {
            AffiliateCoupon::find($affiliateData['coupon_id'])?->incrementUsage();
        }

        Log::info('Affiliate purchase conversion recorded', [
            'conversion_id' => $conversion->id,
            'affiliate_id' => $affiliateData['affiliate_id'],
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return $conversion;
    }

    /**
     * Process a purchase from Stripe checkout session.
     * Called from StripeController webhook.
     */
    public function processPurchaseFromStripeSession(
        \Stripe\Event $event,
        $transaction
    ): ?AffiliateConversion {
        $session = $event->data->object;

        // Try to get affiliate data from session metadata
        $affiliateId = $session->metadata->affiliate_id ?? null;
        $offerId = $session->metadata->offer_id ?? null;
        $clickId = $session->metadata->click_id ?? null;
        $couponCode = $session->metadata->coupon_code ?? null;

        // If not in metadata, try to resolve from coupon code used
        if (!$affiliateId && !empty($session->discount?->coupon?->id)) {
            $promoCode = $session->discount->coupon->id;
            $couponData = $this->trackingService->resolveCouponToAffiliate($promoCode);
            if ($couponData) {
                $affiliateId = $couponData['affiliate_id'];
                $offerId = $couponData['offer_id'];
            }
        }

        if (!$affiliateId) {
            return null;
        }

        // Determine offer from product if not set
        if (!$offerId && $transaction->product) {
            $offer = AffiliateOffer::where('type', 'stripe_product')
                ->where('entity_id', $transaction->product->id)
                ->where('is_active', true)
                ->first();

            if ($offer) {
                $offerId = $offer->id;
            }
        }

        if (!$offerId) {
            // Try to find any offer for this affiliate's program
            $affiliate = Affiliate::find($affiliateId);
            if ($affiliate) {
                $offer = AffiliateOffer::where('program_id', $affiliate->program_id)
                    ->where('is_active', true)
                    ->first();
                $offerId = $offer?->id;
            }
        }

        if (!$offerId) {
            return null;
        }

        $conversion = AffiliateConversion::recordPurchase(
            affiliateId: $affiliateId,
            offerId: $offerId,
            clickId: $clickId ? (int) $clickId : null,
            amount: $transaction->amount / 100, // Stripe amounts are in cents
            currency: strtoupper($transaction->currency),
            entityType: 'stripe_transaction',
            entityId: $transaction->id,
            orderId: $session->id,
            customerEmail: $transaction->customer_email,
            customerName: $transaction->customer_name,
            meta: [
                'stripe_payment_intent' => $session->payment_intent,
                'stripe_session_id' => $session->id,
            ]
        );

        $this->commissionService->calculateCommissions($conversion);

        Log::info('Affiliate Stripe conversion recorded', [
            'conversion_id' => $conversion->id,
            'stripe_session' => $session->id,
            'affiliate_id' => $affiliateId,
        ]);

        return $conversion;
    }

    /**
     * Record a refund (reverses commissions).
     */
    public function recordRefund(
        int|string $originalConversionIdOrSessionId,
        float $refundAmount,
        ?string $reason = null
    ): ?AffiliateConversion {
        // Support both conversion ID and Stripe session ID lookup
        if (is_string($originalConversionIdOrSessionId)) {
            $originalConversion = AffiliateConversion::where('order_id', $originalConversionIdOrSessionId)
                ->orWhere('meta->stripe_session_id', $originalConversionIdOrSessionId)
                ->first();
        } else {
            $originalConversion = AffiliateConversion::find($originalConversionIdOrSessionId);
        }

        if (!$originalConversion) {
            return null;
        }

        // Create refund conversion
        $refundConversion = AffiliateConversion::create([
            'affiliate_id' => $originalConversion->affiliate_id,
            'offer_id' => $originalConversion->offer_id,
            'click_id' => $originalConversion->click_id,
            'type' => 'refund',
            'entity_type' => $originalConversion->entity_type,
            'entity_id' => $originalConversion->entity_id,
            'amount' => -$refundAmount,
            'currency' => $originalConversion->currency,
            'customer_email' => $originalConversion->customer_email,
            'customer_name' => $originalConversion->customer_name,
            'meta' => [
                'original_conversion_id' => $originalConversion->id,
                'reason' => $reason,
            ],
            'created_at' => now(),
        ]);

        // Reverse all commissions from original conversion
        $originalConversion->commissions()->each(function ($commission) use ($reason) {
            $commission->reverse($reason ?? 'Refund');
        });

        Log::info('Affiliate refund recorded', [
            'refund_conversion_id' => $refundConversion->id,
            'original_conversion_id' => $originalConversion->id,
            'amount' => $refundAmount,
        ]);

        return $refundConversion;
    }

    /**
     * Get affiliate data from request (cookies or query parameters).
     */
    private function getAffiliateFromRequest(Request $request): ?array
    {
        // First try cookies
        $cookieData = $this->trackingService->getCookieData($request);

        if ($cookieData) {
            return $cookieData;
        }

        // Try query parameters (for direct link attributions)
        $refCode = $request->get('ref');
        $couponCode = $request->get('coupon');

        if ($refCode) {
            $affiliate = Affiliate::where('referral_code', $refCode)
                ->where('status', 'approved')
                ->first();

            if ($affiliate) {
                $offer = AffiliateOffer::where('program_id', $affiliate->program_id)
                    ->where('is_active', true)
                    ->first();

                if ($offer) {
                    return [
                        'affiliate_id' => $affiliate->id,
                        'offer_id' => $offer->id,
                        'click_id' => null,
                        'coupon_id' => null,
                    ];
                }
            }
        }

        if ($couponCode) {
            $couponData = $this->trackingService->resolveCouponToAffiliate($couponCode);
            if ($couponData) {
                return [
                    'affiliate_id' => $couponData['affiliate_id'],
                    'offer_id' => $couponData['offer_id'],
                    'click_id' => null,
                    'coupon_id' => $couponData['coupon_id'],
                ];
            }
        }

        return null;
    }
}

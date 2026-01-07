<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCoupon;
use App\Models\AffiliateLink;
use App\Models\AffiliateOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class AffiliateTrackingService
{
    // Cookie names
    public const COOKIE_AFFILIATE_REF = 'ns_aff_ref';
    public const COOKIE_AFFILIATE_OFFER = 'ns_aff_offer';
    public const COOKIE_AFFILIATE_LINK = 'ns_aff_link';
    public const COOKIE_AFFILIATE_CLICK = 'ns_aff_click';
    public const COOKIE_VISITOR_ID = 'ns_aff_visitor';

    /**
     * Record a click from an affiliate link.
     */
    public function recordClick(string $linkCode, Request $request): ?array
    {
        $link = AffiliateLink::where('code', $linkCode)->first();

        if (!$link) {
            return null;
        }

        $affiliate = $link->affiliate;
        $offer = $link->offer;
        $program = $offer->program;

        if ($affiliate->status !== 'approved') {
            return null;
        }

        // Generate hashes for privacy-compliant tracking
        $ipHash = hash('sha256', $request->ip() . config('app.key'));
        $uaHash = hash('sha256', $request->userAgent() ?? '');

        // Get or create visitor ID
        $visitorId = $request->cookie(self::COOKIE_VISITOR_ID) ?? Str::uuid()->toString();

        // Extract UTM parameters
        $utmData = [
            'utm_source' => $request->get('utm_source'),
            'utm_medium' => $request->get('utm_medium'),
            'utm_campaign' => $request->get('utm_campaign'),
            'utm_term' => $request->get('utm_term'),
            'utm_content' => $request->get('utm_content'),
        ];

        // Record the click
        $click = AffiliateClick::recordClick(
            linkId: $link->id,
            affiliateId: $affiliate->id,
            offerId: $offer->id,
            ipHash: $ipHash,
            uaHash: $uaHash,
            referrer: $request->header('Referer'),
            landingUrl: $request->fullUrl(),
            sessionId: $request->session()->getId(),
            cookieId: $visitorId,
            utmData: array_filter($utmData)
        );

        // Increment link click counter
        $link->incrementClicks();

        // Calculate cookie expiration
        $cookieDays = $program->cookie_days ?? 30;
        $cookieMinutes = $cookieDays * 24 * 60;

        // Queue cookies
        Cookie::queue(self::COOKIE_AFFILIATE_REF, $affiliate->referral_code, $cookieMinutes);
        Cookie::queue(self::COOKIE_AFFILIATE_OFFER, $offer->id, $cookieMinutes);
        Cookie::queue(self::COOKIE_AFFILIATE_LINK, $link->id, $cookieMinutes);
        Cookie::queue(self::COOKIE_AFFILIATE_CLICK, $click->id, $cookieMinutes);
        Cookie::queue(self::COOKIE_VISITOR_ID, $visitorId, $cookieMinutes);

        return [
            'click_id' => $click->id,
            'affiliate_id' => $affiliate->id,
            'offer_id' => $offer->id,
            'link_id' => $link->id,
            'redirect_url' => $offer->offer_url,
        ];
    }

    /**
     * Track a click from URL parameters without redirect.
     */
    public function trackClickFromParams(Request $request): ?array
    {
        $refCode = $request->get('ref');
        $offerId = $request->get('offer');
        $couponCode = $request->get('coupon');

        // Try to find affiliate from ref code
        $affiliate = null;
        $offer = null;

        if ($refCode) {
            $affiliate = Affiliate::where('referral_code', $refCode)
                ->where('status', 'approved')
                ->first();
        }

        // If no ref, try from coupon
        if (!$affiliate && $couponCode) {
            $coupon = AffiliateCoupon::where('code', $couponCode)
                ->where('is_active', true)
                ->first();

            if ($coupon) {
                $affiliate = $coupon->affiliate;
                $offer = $coupon->offer;
            }
        }

        if (!$affiliate) {
            return null;
        }

        // Get offer
        if (!$offer && $offerId) {
            $offer = AffiliateOffer::where('id', $offerId)
                ->where('program_id', $affiliate->program_id)
                ->where('is_active', true)
                ->first();
        }

        // If no specific offer, use the first active public offer
        if (!$offer) {
            $offer = AffiliateOffer::where('program_id', $affiliate->program_id)
                ->where('is_active', true)
                ->where('is_public', true)
                ->first();
        }

        if (!$offer) {
            return null;
        }

        // Get or create link for this affiliate/offer combo
        $link = $affiliate->getLinkForOffer($offer);

        // Record the click using the link
        return $this->recordClickFromLink($link, $request);
    }

    /**
     * Record click from an existing link object.
     */
    public function recordClickFromLink(AffiliateLink $link, Request $request): array
    {
        $affiliate = $link->affiliate;
        $offer = $link->offer;
        $program = $offer->program;

        $ipHash = hash('sha256', $request->ip() . config('app.key'));
        $uaHash = hash('sha256', $request->userAgent() ?? '');
        $visitorId = $request->cookie(self::COOKIE_VISITOR_ID) ?? Str::uuid()->toString();

        $click = AffiliateClick::recordClick(
            linkId: $link->id,
            affiliateId: $affiliate->id,
            offerId: $offer->id,
            ipHash: $ipHash,
            uaHash: $uaHash,
            referrer: $request->header('Referer'),
            landingUrl: $request->fullUrl(),
            sessionId: $request->session()->getId(),
            cookieId: $visitorId,
            utmData: null
        );

        $link->incrementClicks();

        $cookieDays = $program->cookie_days ?? 30;
        $cookieMinutes = $cookieDays * 24 * 60;

        Cookie::queue(self::COOKIE_AFFILIATE_REF, $affiliate->referral_code, $cookieMinutes);
        Cookie::queue(self::COOKIE_AFFILIATE_OFFER, $offer->id, $cookieMinutes);
        Cookie::queue(self::COOKIE_AFFILIATE_LINK, $link->id, $cookieMinutes);
        Cookie::queue(self::COOKIE_AFFILIATE_CLICK, $click->id, $cookieMinutes);
        Cookie::queue(self::COOKIE_VISITOR_ID, $visitorId, $cookieMinutes);

        return [
            'click_id' => $click->id,
            'affiliate_id' => $affiliate->id,
            'offer_id' => $offer->id,
            'link_id' => $link->id,
        ];
    }

    /**
     * Get affiliate data from cookies.
     */
    public function getCookieData(Request $request): ?array
    {
        $refCode = $request->cookie(self::COOKIE_AFFILIATE_REF);

        if (!$refCode) {
            return null;
        }

        $affiliate = Affiliate::where('referral_code', $refCode)
            ->where('status', 'approved')
            ->first();

        if (!$affiliate) {
            return null;
        }

        return [
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $refCode,
            'offer_id' => $request->cookie(self::COOKIE_AFFILIATE_OFFER),
            'link_id' => $request->cookie(self::COOKIE_AFFILIATE_LINK),
            'click_id' => $request->cookie(self::COOKIE_AFFILIATE_CLICK),
            'visitor_id' => $request->cookie(self::COOKIE_VISITOR_ID),
        ];
    }

    /**
     * Resolve coupon code to affiliate.
     */
    public function resolveCouponToAffiliate(string $code): ?array
    {
        $coupon = AffiliateCoupon::where('code', $code)
            ->where('is_active', true)
            ->valid()
            ->first();

        if (!$coupon) {
            return null;
        }

        return [
            'coupon_id' => $coupon->id,
            'affiliate_id' => $coupon->affiliate_id,
            'offer_id' => $coupon->offer_id,
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
        ];
    }

    /**
     * Clear affiliate cookies (after conversion processed).
     */
    public function clearCookies(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE_AFFILIATE_REF));
        Cookie::queue(Cookie::forget(self::COOKIE_AFFILIATE_OFFER));
        Cookie::queue(Cookie::forget(self::COOKIE_AFFILIATE_LINK));
        Cookie::queue(Cookie::forget(self::COOKIE_AFFILIATE_CLICK));
        // Keep visitor ID for cross-session tracking
    }
}

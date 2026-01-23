<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliateLink;
use App\Models\AffiliateOffer;
use App\Services\AffiliateTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class AffiliateTrackingController extends Controller
{
    public function __construct(
        private AffiliateTrackingService $trackingService
    ) {}

    /**
     * Referral redirect for user registration.
     * Sets affiliate cookie and redirects to register page.
     * GET /ref/{code}
     */
    public function referralRedirect(string $code, Request $request)
    {
        // Find affiliate by referral code
        $affiliate = Affiliate::where('referral_code', $code)
            ->where('status', 'approved')
            ->first();

        if (!$affiliate) {
            Log::warning('Invalid referral code', ['code' => $code]);
            return redirect()->route('register');
        }

        // Get cookie duration from program settings (default 30 days)
        $cookieDays = $affiliate->program->cookie_days ?? 30;

        // Set affiliate cookie
        $cookie = Cookie::make(
            'ns_affiliate',
            $affiliate->id,
            $cookieDays * 24 * 60, // Convert days to minutes
            '/',
            null,
            request()->secure(),
            true, // httpOnly
            false,
            'lax'
        );

        return redirect()
            ->route('register', ['ref' => $code])
            ->withCookie($cookie);
    }

    /**
     * Redirect endpoint for affiliate links.
     * GET /t/r/{code}
     */
    public function redirect(string $code, Request $request)
    {
        $result = $this->trackingService->recordClick($code, $request);

        if (!$result) {
            // Invalid code or inactive affiliate - redirect to home
            Log::warning('Invalid affiliate link code', ['code' => $code]);
            return redirect('/');
        }

        $redirectUrl = $result['redirect_url'];

        if (!$redirectUrl) {
            return redirect('/');
        }

        // Append affiliate parameters to redirect URL
        $separator = str_contains($redirectUrl, '?') ? '&' : '?';
        $redirectUrl .= $separator . http_build_query([
            'ref' => $result['affiliate_id'],
            'aff_click' => $result['click_id'],
        ]);

        return redirect($redirectUrl);
    }

    /**
     * Track click via AJAX (for single-page apps).
     * POST /api/affiliate/track-click
     */
    public function trackClick(Request $request)
    {
        $result = $this->trackingService->trackClickFromParams($request);

        if (!$result) {
            return response()->json(['tracked' => false]);
        }

        return response()->json([
            'tracked' => true,
            'click_id' => $result['click_id'],
        ]);
    }

    /**
     * Get tracking JavaScript snippet for embedding.
     * GET /api/affiliate/tracking-script/{programId}
     */
    public function trackingScript(int $programId)
    {
        $script = <<<JS
(function() {
    var params = new URLSearchParams(window.location.search);
    var ref = params.get('ref');
    var coupon = params.get('coupon');
    var offer = params.get('offer');

    if (ref || coupon) {
        fetch('/api/affiliate/track-click', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                ref: ref,
                coupon: coupon,
                offer: offer,
                landing_url: window.location.href,
                referrer: document.referrer
            }),
            credentials: 'include'
        }).then(function(response) {
            return response.json();
        }).then(function(data) {
            if (data.tracked) {
                console.log('Affiliate click tracked:', data.click_id);
            }
        }).catch(function(err) {
            console.error('Affiliate tracking error:', err);
        });
    }
})();
JS;

        return response($script)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Verify a coupon code returns affiliate info.
     * GET /api/affiliate/verify-coupon/{code}
     */
    public function verifyCoupon(string $code)
    {
        $data = $this->trackingService->resolveCouponToAffiliate($code);

        if (!$data) {
            return response()->json(['valid' => false]);
        }

        return response()->json([
            'valid' => true,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
        ]);
    }
}

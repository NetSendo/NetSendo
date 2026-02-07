<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\PolarProduct;
use App\Models\SalesFunnel;
use App\Models\StripeProduct;
use App\Models\TpayProduct;
use App\Models\Subscriber;
use App\Services\PolarService;
use App\Services\StripeService;
use App\Services\TpayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SalesFunnelCheckoutController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private PolarService $polarService,
        private TpayService $tpayService
    ) {}

    /**
     * Redirect to checkout for a product.
     */
    public function checkout(Request $request, string $type, int $product): RedirectResponse
    {
        if ($type === 'stripe') {
            return $this->stripeCheckout($request, $product);
        } elseif ($type === 'polar') {
            return $this->polarCheckout($request, $product);
        } elseif ($type === 'tpay') {
            return $this->tpayCheckout($request, $product);
        }

        abort(404, 'Invalid product type');
    }

    /**
     * Handle Stripe checkout redirect.
     */
    private function stripeCheckout(Request $request, int $productId): RedirectResponse
    {
        $product = StripeProduct::with('salesFunnel')->findOrFail($productId);

        if (!$product->is_active) {
            abort(404, 'Product is not available');
        }

        $options = [];

        // If product has sales funnel, use its success URL
        if ($product->salesFunnel) {
            $options['success_url'] = route('sales-funnel.success', [
                'funnel' => $product->salesFunnel->id,
            ]) . '?session_id={CHECKOUT_SESSION_ID}&type=stripe';
        }

        // Pre-fill email if provided
        if ($request->has('email')) {
            $options['customer_email'] = $request->input('email');
        }

        try {
            $checkoutUrl = $this->stripeService->getCheckoutUrl($product, $options);
            return redirect()->away($checkoutUrl);
        } catch (\Exception $e) {
            Log::error('Stripe checkout failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Checkout failed. Please try again.');
        }
    }

    /**
     * Handle Polar checkout redirect.
     */
    private function polarCheckout(Request $request, int $productId): RedirectResponse
    {
        $product = PolarProduct::with('salesFunnel')->findOrFail($productId);

        if (!$product->is_active) {
            abort(404, 'Product is not available');
        }

        $options = [];

        // If product has sales funnel, use its success URL
        if ($product->salesFunnel) {
            $options['success_url'] = route('sales-funnel.success', [
                'funnel' => $product->salesFunnel->id,
            ]) . '?session_id={CHECKOUT_SESSION_ID}&type=polar';
        }

        // Pre-fill email if provided
        if ($request->has('email')) {
            $options['customer_email'] = $request->input('email');
        }

        try {
            $checkoutUrl = $this->polarService->getCheckoutUrl($product, $options);
            return redirect()->away($checkoutUrl);
        } catch (\Exception $e) {
            Log::error('Polar checkout failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Checkout failed. Please try again.');
        }
    }

    /**
     * Handle Tpay checkout redirect.
     */
    private function tpayCheckout(Request $request, int $productId): RedirectResponse
    {
        $product = TpayProduct::with('salesFunnel')->findOrFail($productId);

        if (!$product->is_active) {
            abort(404, 'Product is not available');
        }

        $options = [];

        // If product has sales funnel, use its success URL
        if ($product->salesFunnel) {
            $options['success_url'] = route('sales-funnel.success', [
                'funnel' => $product->salesFunnel->id,
            ]) . '?type=tpay';
        }

        // Pre-fill email if provided
        if ($request->has('email')) {
            $options['customer_email'] = $request->input('email');
        }
        if ($request->has('name')) {
            $options['customer_name'] = $request->input('name');
        }

        try {
            $checkoutUrl = $this->tpayService->getCheckoutUrl($product, $options);
            return redirect()->away($checkoutUrl);
        } catch (\Exception $e) {
            Log::error('Tpay checkout failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Checkout failed. Please try again.');
        }
    }

    /**
     * Handle success redirect from payment provider.
     */
    public function success(Request $request, SalesFunnel $funnel): View|RedirectResponse
    {
        $sessionId = $request->input('session_id');
        $paymentType = $request->input('type', 'stripe');

        Log::info('Sales funnel success callback', [
            'funnel_id' => $funnel->id,
            'session_id' => $sessionId,
            'type' => $paymentType,
        ]);

        // The actual subscription to list happens in the webhook handler
        // Here we just redirect the user to thank you page

        // If funnel has a custom URL, redirect there
        if ($funnel->thank_you_url) {
            return redirect()->away($funnel->thank_you_url);
        }

        // If funnel has an external page, redirect there
        if ($funnel->thankYouPage) {
            return redirect()->route('external-page.show', $funnel->thankYouPage);
        }

        // Default: show a generic thank you page
        return view('sales-funnel.thank-you', [
            'funnel' => $funnel,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\StripeProduct;
use App\Models\StripeTransaction;
use App\Services\StripeService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class StripeProductController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private WebhookDispatcher $webhookDispatcher
    ) {}

    /**
     * Display a listing of Stripe products.
     */
    public function index(): Response
    {
        $products = StripeProduct::forUser(Auth::id())
            ->withCount('transactions')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'formatted_price' => $product->formatted_price,
                    'currency' => $product->currency,
                    'type' => $product->type,
                    'is_active' => $product->is_active,
                    'stripe_product_id' => $product->stripe_product_id,
                    'stripe_price_id' => $product->stripe_price_id,
                    'transactions_count' => $product->transactions_count,
                    'total_revenue' => $product->total_revenue,
                    'sales_count' => $product->sales_count,
                    'created_at' => $product->created_at->toISOString(),
                ];
            });

        $isConfigured = $this->stripeService->isConfigured();

        return Inertia::render('Settings/StripeProducts/Index', [
            'products' => $products,
            'isConfigured' => $isConfigured,
        ]);
    }

    /**
     * Store a newly created Stripe product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|integer|min:1', // in cents/grosze
            'currency' => 'required|string|size:3',
            'type' => 'required|in:one_time,subscription',
        ]);

        try {
            $product = $this->stripeService->createProduct(Auth::id(), $validated);

            return redirect()->route('settings.stripe-products.index')
                ->with('success', __('stripe.product_created'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified Stripe product.
     */
    public function update(Request $request, StripeProduct $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|integer|min:1',
            'currency' => 'required|string|size:3',
            'is_active' => 'boolean',
        ]);

        try {
            $this->stripeService->updateProduct($product, $validated);

            return redirect()->route('settings.stripe-products.index')
                ->with('success', __('stripe.product_updated'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified Stripe product.
     */
    public function destroy(StripeProduct $product)
    {
        $this->authorize('delete', $product);

        try {
            $this->stripeService->archiveProduct($product);

            return redirect()->route('settings.stripe-products.index')
                ->with('success', __('stripe.product_deleted'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get transactions for a specific product.
     */
    public function transactions(StripeProduct $product)
    {
        $this->authorize('view', $product);

        $transactions = $product->transactions()
            ->with('subscriber:id,email,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'transactions' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get checkout URL for a product.
     */
    public function checkoutUrl(StripeProduct $product, Request $request)
    {
        $this->authorize('view', $product);

        try {
            $options = [];
            if ($request->has('success_url')) {
                $options['success_url'] = $request->input('success_url');
            }
            if ($request->has('cancel_url')) {
                $options['cancel_url'] = $request->input('cancel_url');
            }

            $url = $this->stripeService->getCheckoutUrl($product, $options);

            return response()->json(['url' => $url]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all transactions for the user.
     */
    public function allTransactions(Request $request)
    {
        $transactions = StripeTransaction::forUser(Auth::id())
            ->with(['product:id,name', 'subscriber:id,email,first_name,last_name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'transactions' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}

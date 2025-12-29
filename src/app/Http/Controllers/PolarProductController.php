<?php

namespace App\Http\Controllers;

use App\Models\PolarProduct;
use App\Services\PolarService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PolarProductController extends Controller
{
    public function __construct(private PolarService $polarService) {}

    /**
     * Display a listing of Polar products.
     */
    public function index()
    {
        $products = PolarProduct::forUser(auth()->id())
            ->withCount('transactions')
            ->latest()
            ->paginate(20);

        return Inertia::render('Settings/PolarProducts/Index', [
            'products' => $products,
            'isConfigured' => $this->polarService->isConfigured(),
        ]);
    }

    /**
     * Store a newly created Polar product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:1'], // in cents
            'currency' => ['required', 'string', 'size:3'],
            'type' => ['required', 'in:one_time,recurring'],
            'billing_interval' => ['nullable', 'in:month,year'],
        ]);

        if (!$this->polarService->isConfigured()) {
            return back()->withErrors(['polar' => __('polar.not_configured')]);
        }

        try {
            $product = $this->polarService->createProduct(auth()->id(), $validated);

            return back()->with('success', __('polar.product_created'));
        } catch (\Exception $e) {
            return back()->withErrors(['polar' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified Polar product.
     */
    public function update(Request $request, PolarProduct $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        try {
            $this->polarService->updateProduct($product, $validated);

            return back()->with('success', __('polar.product_updated'));
        } catch (\Exception $e) {
            return back()->withErrors(['polar' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified Polar product.
     */
    public function destroy(PolarProduct $product)
    {
        $this->authorize('delete', $product);

        try {
            $this->polarService->archiveProduct($product);

            return back()->with('success', __('polar.product_deleted'));
        } catch (\Exception $e) {
            return back()->withErrors(['polar' => $e->getMessage()]);
        }
    }

    /**
     * Get transactions for a specific product.
     */
    public function transactions(PolarProduct $product)
    {
        $this->authorize('view', $product);

        $transactions = $product->transactions()
            ->latest()
            ->paginate(50);

        return response()->json($transactions);
    }

    /**
     * Generate checkout URL for a product.
     */
    public function checkoutUrl(Request $request, PolarProduct $product)
    {
        $this->authorize('view', $product);

        $validated = $request->validate([
            'customer_email' => ['nullable', 'email'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'success_url' => ['nullable', 'url'],
            'cancel_url' => ['nullable', 'url'],
        ]);

        try {
            $url = $this->polarService->getCheckoutUrl($product, $validated);

            if (!$url) {
                return response()->json([
                    'success' => false,
                    'message' => __('polar.checkout_url_failed'),
                ], 400);
            }

            return response()->json([
                'success' => true,
                'url' => $url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all transactions for the current user.
     */
    public function allTransactions()
    {
        $transactions = auth()->user()->polarTransactions()
            ->with('product')
            ->latest()
            ->paginate(50);

        return response()->json($transactions);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\TpayProduct;
use App\Models\TpayTransaction;
use App\Services\TpayService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TpayProductController extends Controller
{
    public function __construct(
        private TpayService $tpayService,
        private WebhookDispatcher $webhookDispatcher
    ) {}

    /**
     * Display a listing of Tpay products.
     */
    public function index(): Response
    {
        $products = TpayProduct::forUser(Auth::id())
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
                    'tpay_product_id' => $product->tpay_product_id,
                    'sales_funnel_id' => $product->sales_funnel_id,
                    'transactions_count' => $product->transactions_count,
                    'total_revenue' => $product->total_revenue,
                    'sales_count' => $product->sales_count,
                    'created_at' => $product->created_at->toISOString(),
                ];
            });

        $isConfigured = $this->tpayService->isConfigured();

        return Inertia::render('Settings/TpayProducts/Index', [
            'products' => $products,
            'isConfigured' => $isConfigured,
        ]);
    }

    /**
     * Store a newly created Tpay product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|integer|min:1', // in grosze
            'currency' => 'required|string|size:3',
            'type' => 'required|in:one_time,subscription',
            'sales_funnel_id' => 'nullable|exists:sales_funnels,id',
        ]);

        try {
            TpayProduct::create([
                'user_id' => Auth::id(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'currency' => strtoupper($validated['currency']),
                'type' => $validated['type'],
                'is_active' => true,
                'sales_funnel_id' => $validated['sales_funnel_id'] ?? null,
            ]);

            return redirect()->route('settings.tpay-products.index')
                ->with('success', __('tpay.product_created'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified Tpay product.
     */
    public function update(Request $request, TpayProduct $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|integer|min:1',
            'currency' => 'required|string|size:3',
            'is_active' => 'boolean',
            'sales_funnel_id' => 'nullable|exists:sales_funnels,id',
        ]);

        try {
            $product->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? $product->description,
                'price' => $validated['price'],
                'currency' => strtoupper($validated['currency']),
                'is_active' => $validated['is_active'] ?? $product->is_active,
                'sales_funnel_id' => $validated['sales_funnel_id'] ?? $product->sales_funnel_id,
            ]);

            return redirect()->route('settings.tpay-products.index')
                ->with('success', __('tpay.product_updated'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified Tpay product.
     */
    public function destroy(TpayProduct $product)
    {
        $this->authorize('delete', $product);

        try {
            $product->update(['is_active' => false]);
            $product->delete();

            return redirect()->route('settings.tpay-products.index')
                ->with('success', __('tpay.product_deleted'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get transactions for a specific product.
     */
    public function transactions(TpayProduct $product)
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
    public function checkoutUrl(TpayProduct $product, Request $request)
    {
        $this->authorize('view', $product);

        try {
            $options = [];
            if ($request->has('success_url')) {
                $options['success_url'] = $request->input('success_url');
            }
            if ($request->has('error_url')) {
                $options['error_url'] = $request->input('error_url');
            }
            if ($request->has('customer_email')) {
                $options['customer_email'] = $request->input('customer_email');
            }

            $url = $this->tpayService->getCheckoutUrl($product, $options);

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
        $transactions = TpayTransaction::forUser(Auth::id())
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

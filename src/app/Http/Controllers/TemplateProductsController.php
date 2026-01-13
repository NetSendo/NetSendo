<?php

namespace App\Http\Controllers;

use App\Models\PixelEvent;
use App\Models\WooCommerceSettings;
use App\Services\WooCommerceApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TemplateProductsController extends Controller
{
    /**
     * Get WooCommerce products for template builder
     */
    public function woocommerceProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
            'search' => 'nullable|string|max:100',
            'category' => 'nullable|integer',
            'store_id' => 'nullable|integer',
        ]);

        $storeId = $validated['store_id'] ?? null;
        $service = WooCommerceApiService::forUser(Auth::id(), $storeId);

        if (!$service) {
            return response()->json([
                'success' => false,
                'connected' => false,
                'message' => __('template_builder.woocommerce_not_connected'),
            ], 200);
        }

        try {
            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 20;
            $params = [];

            if (!empty($validated['category'])) {
                $params['category'] = $validated['category'];
            }

            if (!empty($validated['search'])) {
                $result = $service->searchProductsWithMeta($validated['search'], $page, $perPage);
            } else {
                $result = $service->getProductsWithMeta($page, $perPage, $params);
            }

            // Format products for template builder
            $formatted = array_map(
                fn($product) => $service->formatProductForTemplate($product),
                $result['products']
            );

            return response()->json([
                'success' => true,
                'connected' => true,
                'products' => $formatted,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'total_pages' => $result['total_pages'],
                'has_more' => $page < $result['total_pages'],
                'store_id' => $service->getStoreId(),
            ]);
        } catch (\Exception $e) {
            Log::error('WooCommerce products fetch failed', [
                'user_id' => Auth::id(),
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'connected' => true,
                'message' => __('template_builder.error_loading_products'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get WooCommerce categories
     */
    public function woocommerceCategories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'nullable|integer',
        ]);

        $storeId = $validated['store_id'] ?? null;
        $service = WooCommerceApiService::forUser(Auth::id(), $storeId);

        if (!$service) {
            return response()->json([
                'success' => false,
                'categories' => [],
            ]);
        }

        try {
            $categories = $service->getCategories();

            return response()->json([
                'success' => true,
                'categories' => array_map(fn($cat) => [
                    'id' => $cat['id'],
                    'name' => $cat['name'],
                    'count' => $cat['count'] ?? 0,
                ], $categories),
            ]);
        } catch (\Exception $e) {
            Log::error('WooCommerce categories fetch failed', [
                'user_id' => Auth::id(),
                'store_id' => $storeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'categories' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recently viewed products from Pixel data
     */
    public function recentlyViewed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'days' => 'nullable|integer|min:1|max:90',
        ]);

        $limit = $validated['limit'] ?? 20;
        $days = $validated['days'] ?? 30;

        // Get unique product IDs from pixel events
        $productEvents = PixelEvent::forUser(Auth::id())
            ->ofType(PixelEvent::TYPE_PRODUCT_VIEW)
            ->withinDays($days)
            ->whereNotNull('product_id')
            ->selectRaw('product_id, product_name, MAX(product_price) as product_price, MAX(occurred_at) as last_viewed, COUNT(*) as view_count')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('last_viewed')
            ->limit($limit)
            ->get();

        // Try to enrich with WooCommerce data if connected
        $service = WooCommerceApiService::forUser(Auth::id());
        $products = [];

        if ($service && $productEvents->isNotEmpty()) {
            $wcProductIds = $productEvents->pluck('product_id')
                ->filter(fn($id) => is_numeric($id))
                ->values()
                ->toArray();

            try {
                if (!empty($wcProductIds)) {
                    $wcProducts = $service->getProductsByIds(array_slice($wcProductIds, 0, 20));
                    $wcProductsIndexed = collect($wcProducts)->keyBy('id');

                    foreach ($productEvents as $event) {
                        $wcProduct = $wcProductsIndexed->get($event->product_id);

                        if ($wcProduct) {
                            $products[] = array_merge(
                                $service->formatProductForTemplate($wcProduct),
                                [
                                    'view_count' => $event->view_count,
                                    'last_viewed' => $event->last_viewed,
                                ]
                            );
                        } else {
                            // Fallback to pixel data
                            $products[] = [
                                'id' => $event->product_id,
                                'name' => $event->product_name ?? 'Product #' . $event->product_id,
                                'price' => $event->product_price,
                                'image' => null,
                                'view_count' => $event->view_count,
                                'last_viewed' => $event->last_viewed,
                                'from_pixel' => true,
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                // If WooCommerce fetch fails, use pixel data only
                foreach ($productEvents as $event) {
                    $products[] = [
                        'id' => $event->product_id,
                        'name' => $event->product_name ?? 'Product #' . $event->product_id,
                        'price' => $event->product_price,
                        'image' => null,
                        'view_count' => $event->view_count,
                        'last_viewed' => $event->last_viewed,
                        'from_pixel' => true,
                    ];
                }
            }
        } else {
            // No WooCommerce connection, use pixel data only
            foreach ($productEvents as $event) {
                $products[] = [
                    'id' => $event->product_id,
                    'name' => $event->product_name ?? 'Product #' . $event->product_id,
                    'price' => $event->product_price,
                    'image' => null,
                    'view_count' => $event->view_count,
                    'last_viewed' => $event->last_viewed,
                    'from_pixel' => true,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'products' => $products,
            'woocommerce_connected' => $service !== null,
        ]);
    }

    /**
     * Get variations for a specific product
     */
    public function getProductVariations(Request $request, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'nullable|integer',
        ]);

        $storeId = $validated['store_id'] ?? null;
        $service = WooCommerceApiService::forUser(Auth::id(), $storeId);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => __('template_builder.woocommerce_not_connected'),
            ], 400);
        }

        try {
            $product = $service->getProduct($productId);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => __('template_builder.product_not_found'),
                ], 404);
            }

            if (($product['type'] ?? 'simple') !== 'variable') {
                return response()->json([
                    'success' => true,
                    'variations' => [],
                    'is_variable' => false,
                ]);
            }

            $variations = $service->getProductVariations($productId);
            $formatted = array_map(
                fn($v) => $service->formatVariationForTemplate($v, $product),
                $variations
            );

            return response()->json([
                'success' => true,
                'variations' => $formatted,
                'is_variable' => true,
                'attributes' => $product['attributes'] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error('WooCommerce variations fetch failed', [
                'user_id' => Auth::id(),
                'product_id' => $productId,
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single product details
     */
    public function getProduct(Request $request, int $productId): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'nullable|integer',
        ]);

        $storeId = $validated['store_id'] ?? null;
        $service = WooCommerceApiService::forUser(Auth::id(), $storeId);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => __('template_builder.woocommerce_not_connected'),
            ], 400);
        }

        try {
            $product = $service->getProduct($productId);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => __('template_builder.product_not_found'),
                ], 404);
            }

            return response()->json([
                'success' => true,
                'product' => $service->formatProductForTemplate($product),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check WooCommerce connection status - returns all connected stores
     */
    public function connectionStatus(): JsonResponse
    {
        $stores = WooCommerceSettings::forUser(Auth::id());
        $connectedStores = $stores->filter(fn($store) => $store->isConnected());

        // Return stores info for the ProductPickerModal
        return response()->json([
            'connected' => $connectedStores->isNotEmpty(),
            'stores' => $connectedStores->map(fn($store) => [
                'id' => $store->id,
                'name' => $store->name,
                'display_name' => $store->display_name,
                'store_url' => $store->store_url,
                'is_default' => $store->is_default,
                'store_info' => $store->store_info,
            ])->values(),
            // Backwards compatibility: return default store info
            'store_url' => $connectedStores->first()?->store_url,
            'store_info' => $connectedStores->first()?->store_info,
        ]);
    }
}

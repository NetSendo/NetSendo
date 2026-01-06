<?php

namespace App\Http\Controllers;

use App\Models\PixelEvent;
use App\Models\WooCommerceSettings;
use App\Services\WooCommerceApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        ]);

        $service = WooCommerceApiService::forUser(Auth::id());

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
            ]);
        } catch (\Exception $e) {
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
    public function woocommerceCategories(): JsonResponse
    {
        $service = WooCommerceApiService::forUser(Auth::id());

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
            return response()->json([
                'success' => false,
                'categories' => [],
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
     * Get single product details
     */
    public function getProduct(int $productId): JsonResponse
    {
        $service = WooCommerceApiService::forUser(Auth::id());

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
     * Check WooCommerce connection status
     */
    public function connectionStatus(): JsonResponse
    {
        $settings = WooCommerceSettings::forUser(Auth::id());

        return response()->json([
            'connected' => $settings?->isConnected() ?? false,
            'store_url' => $settings?->store_url,
            'store_info' => $settings?->store_info,
        ]);
    }
}

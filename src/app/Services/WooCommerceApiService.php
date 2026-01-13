<?php

namespace App\Services;

use App\Models\WooCommerceSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WooCommerceApiService
{
    protected WooCommerceSettings $settings;
    protected int $timeout = 30;

    public function __construct(WooCommerceSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Create instance from user ID
     * @param int $userId User ID
     * @param int|null $storeId Optional specific store ID, otherwise uses default
     */
    public static function forUser(int $userId, ?int $storeId = null): ?self
    {
        if ($storeId) {
            $settings = WooCommerceSettings::getByIdForUser($storeId, $userId);
        } else {
            $settings = WooCommerceSettings::getDefaultForUser($userId);
        }

        if (!$settings || !$settings->isConnected()) {
            return null;
        }

        return new self($settings);
    }

    /**
     * Get the store ID
     */
    public function getStoreId(): int
    {
        return $this->settings->id;
    }

    /**
     * Test connection to WooCommerce API
     */
    public function testConnection(): array
    {
        try {
            $response = $this->makeRequest('');

            if (isset($response['store_id']) || isset($response['routes'])) {
                // Get store info
                $storeInfo = $this->getStoreInfo();

                return [
                    'success' => true,
                    'store_info' => $storeInfo,
                ];
            }

            return [
                'success' => false,
                'error' => 'Unexpected API response',
            ];
        } catch (\Exception $e) {
            Log::warning('WooCommerce connection test failed', [
                'error' => $e->getMessage(),
                'store_url' => $this->settings->store_url,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get store information
     */
    public function getStoreInfo(): array
    {
        try {
            $systemStatus = $this->makeRequest('system_status');

            return [
                'name' => $systemStatus['environment']['site_url'] ?? $this->settings->store_url,
                'currency' => $systemStatus['settings']['currency'] ?? 'PLN',
                'currency_symbol' => $systemStatus['settings']['currency_symbol'] ?? 'zł',
                'wc_version' => $systemStatus['environment']['version'] ?? null,
                'wp_version' => $systemStatus['environment']['wp_version'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'name' => $this->settings->store_url,
                'currency' => 'PLN',
                'currency_symbol' => 'zł',
            ];
        }
    }

    /**
     * Fetch products from WooCommerce
     */
    public function getProducts(int $page = 1, int $perPage = 20, array $params = []): array
    {
        $result = $this->getProductsWithMeta($page, $perPage, $params);
        return $result['products'];
    }

    /**
     * Fetch products from WooCommerce with pagination metadata
     */
    public function getProductsWithMeta(int $page = 1, int $perPage = 20, array $params = []): array
    {
        $cacheKey = "wc_products_meta_{$this->settings->id}_{$page}_{$perPage}_" . md5(json_encode($params));

        return Cache::remember($cacheKey, 300, function () use ($page, $perPage, $params) {
            $queryParams = array_merge([
                'page' => $page,
                'per_page' => min($perPage, 100),
                'status' => 'publish',
                'orderby' => 'date',
                'order' => 'desc',
            ], $params);

            $result = $this->makeRequestWithHeaders('products', $queryParams);

            return [
                'products' => $result['data'],
                'total' => $result['headers']['total'],
                'total_pages' => $result['headers']['total_pages'],
            ];
        });
    }

    /**
     * Search products
     */
    public function searchProducts(string $query, int $limit = 20): array
    {
        $result = $this->searchProductsWithMeta($query, 1, $limit);
        return $result['products'];
    }

    /**
     * Search products with pagination metadata
     */
    public function searchProductsWithMeta(string $query, int $page = 1, int $perPage = 20): array
    {
        $result = $this->makeRequestWithHeaders('products', [
            'search' => $query,
            'page' => $page,
            'per_page' => min($perPage, 100),
            'status' => 'publish',
        ]);

        return [
            'products' => $result['data'],
            'total' => $result['headers']['total'],
            'total_pages' => $result['headers']['total_pages'],
        ];
    }

    /**
     * Get single product by ID
     */
    public function getProduct(int $productId): ?array
    {
        $cacheKey = "wc_product_{$this->settings->id}_{$productId}";

        return Cache::remember($cacheKey, 600, function () use ($productId) {
            try {
                return $this->makeRequest("products/{$productId}");
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    /**
     * Get products by IDs
     */
    public function getProductsByIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        return $this->makeRequest('products', [
            'include' => implode(',', $productIds),
            'per_page' => count($productIds),
        ]);
    }

    /**
     * Get product categories
     */
    public function getCategories(int $perPage = 100): array
    {
        $cacheKey = "wc_categories_{$this->settings->id}";

        return Cache::remember($cacheKey, 3600, function () use ($perPage) {
            return $this->makeRequest('products/categories', [
                'per_page' => $perPage,
                'hide_empty' => true,
            ]);
        });
    }

    /**
     * Make authenticated request to WooCommerce API
     */
    protected function makeRequest(string $endpoint, array $params = []): array
    {
        $result = $this->makeRequestWithHeaders($endpoint, $params);
        return $result['data'];
    }

    /**
     * Make authenticated request to WooCommerce API and return with headers
     */
    protected function makeRequestWithHeaders(string $endpoint, array $params = []): array
    {
        $url = $this->settings->getApiUrl() . '/' . ltrim($endpoint, '/');

        // Validate credentials are available
        $consumerKey = $this->settings->consumer_key;
        $consumerSecret = $this->settings->consumer_secret;

        if (empty($consumerKey) || empty($consumerSecret)) {
            Log::error('WooCommerce API: Missing credentials', [
                'store_id' => $this->settings->id,
                'store_url' => $this->settings->store_url,
                'has_consumer_key' => !empty($consumerKey),
                'has_consumer_secret' => !empty($consumerSecret),
            ]);
            throw new \Exception('WooCommerce API Error: Invalid or missing credentials. Please re-enter your API credentials.');
        }

        $response = Http::withBasicAuth($consumerKey, $consumerSecret)
            ->timeout($this->timeout)
            ->get($url, $params);

        if ($response->failed()) {
            $error = $response->json('message') ?? $response->body();
            Log::warning('WooCommerce API request failed', [
                'store_id' => $this->settings->id,
                'store_url' => $this->settings->store_url,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'error' => $error,
            ]);
            throw new \Exception("WooCommerce API Error: {$error}");
        }

        return [
            'data' => $response->json() ?? [],
            'headers' => [
                'total' => (int) $response->header('X-WP-Total', 0),
                'total_pages' => (int) $response->header('X-WP-TotalPages', 0),
            ],
        ];
    }

    /**
     * Format product data for template builder
     */
    public function formatProductForTemplate(array $product): array
    {
        $image = $product['images'][0]['src'] ?? null;
        $regularPrice = (float) ($product['regular_price'] ?? 0);
        $salePrice = (float) ($product['sale_price'] ?? 0);
        $isVariable = ($product['type'] ?? 'simple') === 'variable';

        $formatted = [
            'id' => $product['id'],
            'sku' => $product['sku'] ?? '',
            'name' => $product['name'],
            'description' => strip_tags($product['short_description'] ?? $product['description'] ?? ''),
            'price' => $salePrice > 0 ? $salePrice : $regularPrice,
            'regular_price' => $regularPrice,
            'sale_price' => $salePrice > 0 ? $salePrice : null,
            'currency' => $this->settings->store_info['currency'] ?? 'PLN',
            'image' => $image,
            'url' => $product['permalink'] ?? '',
            'in_stock' => $product['in_stock'] ?? true,
            'categories' => array_map(fn($cat) => $cat['name'], $product['categories'] ?? []),
            'type' => $product['type'] ?? 'simple',
            'has_variations' => $isVariable,
        ];

        // Add variation-specific data for variable products
        if ($isVariable) {
            $variations = $product['variations'] ?? [];
            $formatted['variations_count'] = count($variations);
            $formatted['attributes'] = $product['attributes'] ?? [];

            // Calculate price range from variation prices if available
            if (!empty($product['price'])) {
                // WooCommerce returns price as the minimum price for variable products
                $minPrice = (float) ($product['price'] ?? 0);
                $maxPrice = (float) ($product['price'] ?? 0);

                // Try to get actual range from price_html or calculate
                if (isset($product['variations']) && is_array($product['variations'])) {
                    $prices = array_filter(array_map(fn($v) => (float) ($v['price'] ?? 0), $product['variations']));
                    if (!empty($prices)) {
                        $minPrice = min($prices);
                        $maxPrice = max($prices);
                    }
                }

                $formatted['price_range'] = [
                    'min' => $minPrice,
                    'max' => $maxPrice,
                ];
            }
        }

        return $formatted;
    }

    /**
     * Get variations for a variable product
     */
    public function getProductVariations(int $productId, int $perPage = 100): array
    {
        $cacheKey = "wc_variations_{$this->settings->id}_{$productId}";

        return Cache::remember($cacheKey, 600, function () use ($productId, $perPage) {
            return $this->makeRequest("products/{$productId}/variations", [
                'per_page' => $perPage,
                'status' => 'publish',
            ]);
        });
    }

    /**
     * Format a single variation for template use
     */
    public function formatVariationForTemplate(array $variation, array $parentProduct): array
    {
        $image = $variation['image']['src'] ?? ($parentProduct['images'][0]['src'] ?? null);
        $regularPrice = (float) ($variation['regular_price'] ?? 0);
        $salePrice = (float) ($variation['sale_price'] ?? 0);

        return [
            'id' => $variation['id'],
            'parent_id' => $parentProduct['id'],
            'sku' => $variation['sku'] ?? '',
            'name' => $this->buildVariationName($parentProduct['name'], $variation['attributes'] ?? []),
            'attributes' => $variation['attributes'] ?? [],
            'price' => $salePrice > 0 ? $salePrice : $regularPrice,
            'regular_price' => $regularPrice,
            'sale_price' => $salePrice > 0 ? $salePrice : null,
            'currency' => $this->settings->store_info['currency'] ?? 'PLN',
            'image' => $image,
            'url' => $variation['permalink'] ?? $parentProduct['permalink'] ?? '',
            'in_stock' => $variation['in_stock'] ?? ($variation['stock_status'] ?? 'instock') === 'instock',
            'is_variation' => true,
        ];
    }

    /**
     * Build variation name from parent name and attributes
     */
    protected function buildVariationName(string $parentName, array $attributes): string
    {
        if (empty($attributes)) {
            return $parentName;
        }

        $attrStr = implode(', ', array_map(fn($a) => $a['option'] ?? '', $attributes));
        return $attrStr ? "{$parentName} - {$attrStr}" : $parentName;
    }

    /**
     * Clear product cache for user
     */
    public function clearCache(): void
    {
        Cache::forget("wc_categories_{$this->settings->id}");
        // Note: Individual product caches will expire naturally
    }
}

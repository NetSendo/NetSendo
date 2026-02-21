<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PixelEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscriber_device_id',
        'subscriber_id',
        'user_id',
        'event_type',
        'event_category',
        'page_url',
        'page_title',
        'referrer',
        'product_id',
        'product_name',
        'product_category',
        'product_price',
        'product_currency',
        'cart_value',
        'time_on_page_seconds',
        'scroll_depth_percent',
        'custom_data',
        'session_id',
        'ip_address',
        'occurred_at',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'cart_value' => 'decimal:2',
        'custom_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Event type constants
     */
    public const TYPE_PAGE_VIEW = 'page_view';
    public const TYPE_PRODUCT_VIEW = 'product_view';
    public const TYPE_ADD_TO_CART = 'add_to_cart';
    public const TYPE_REMOVE_FROM_CART = 'remove_from_cart';
    public const TYPE_CHECKOUT_STARTED = 'checkout_started';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_ENGAGEMENT = 'engagement';
    public const TYPE_CUSTOM = 'custom';

    /**
     * Event category constants
     */
    public const CATEGORY_ECOMMERCE = 'ecommerce';
    public const CATEGORY_NAVIGATION = 'navigation';
    public const CATEGORY_ENGAGEMENT = 'engagement';

    /**
     * E-commerce event types
     */
    public const ECOMMERCE_EVENTS = [
        self::TYPE_PRODUCT_VIEW,
        self::TYPE_ADD_TO_CART,
        self::TYPE_REMOVE_FROM_CART,
        self::TYPE_CHECKOUT_STARTED,
        self::TYPE_PURCHASE,
    ];

    // Relationships

    public function subscriberDevice(): BelongsTo
    {
        return $this->belongsTo(SubscriberDevice::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSubscriber($query, int $subscriberId)
    {
        return $query->where('subscriber_id', $subscriberId);
    }

    public function scopeForDevice($query, int $deviceId)
    {
        return $query->where('subscriber_device_id', $deviceId);
    }

    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeEcommerce($query)
    {
        return $query->whereIn('event_type', self::ECOMMERCE_EVENTS);
    }

    public function scopeWithinDays($query, int $days)
    {
        return $query->where('occurred_at', '>=', now()->subDays($days));
    }

    public function scopeForProduct($query, string $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeUrlContains($query, string $pattern)
    {
        return $query->where('page_url', 'like', '%' . $pattern . '%');
    }

    public function scopeUrlMatches($query, string $pattern)
    {
        // Convert wildcard pattern to SQL LIKE pattern
        $likePattern = str_replace('*', '%', $pattern);
        return $query->where('page_url', 'like', $likePattern);
    }

    // Static methods

    /**
     * Log a new event
     */
    public static function logEvent(
        SubscriberDevice $device,
        string $eventType,
        array $data = []
    ): self {
        $category = self::determineCategory($eventType);

        return static::create([
            'subscriber_device_id' => $device->id,
            'subscriber_id' => $device->subscriber_id,
            'user_id' => $device->user_id,
            'event_type' => $eventType,
            'event_category' => $category,
            'page_url' => $data['page_url'] ?? '',
            'page_title' => $data['page_title'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'product_name' => $data['product_name'] ?? null,
            'product_category' => $data['product_category'] ?? null,
            'product_price' => $data['product_price'] ?? null,
            'product_currency' => $data['product_currency'] ?? 'PLN',
            'cart_value' => $data['cart_value'] ?? null,
            'time_on_page_seconds' => $data['time_on_page'] ?? null,
            'scroll_depth_percent' => $data['scroll_depth'] ?? null,
            'custom_data' => $data['custom_data'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Determine event category based on type
     */
    protected static function determineCategory(string $eventType): string
    {
        if (in_array($eventType, self::ECOMMERCE_EVENTS)) {
            return self::CATEGORY_ECOMMERCE;
        }

        if ($eventType === self::TYPE_PAGE_VIEW) {
            return self::CATEGORY_NAVIGATION;
        }

        if ($eventType === self::TYPE_ENGAGEMENT) {
            return self::CATEGORY_ENGAGEMENT;
        }

        return self::CATEGORY_ENGAGEMENT;
    }

    /**
     * Get statistics for a user
     */
    public static function getStatsForUser(int $userId, int $days = 7): array
    {
        $since = now()->subDays($days);

        $baseQuery = static::forUser($userId)->where('occurred_at', '>=', $since);

        return [
            'total_events' => (clone $baseQuery)->count(),
            'page_views' => (clone $baseQuery)->ofType(self::TYPE_PAGE_VIEW)->count(),
            'product_views' => (clone $baseQuery)->ofType(self::TYPE_PRODUCT_VIEW)->count(),
            'add_to_cart' => (clone $baseQuery)->ofType(self::TYPE_ADD_TO_CART)->count(),
            'checkouts' => (clone $baseQuery)->ofType(self::TYPE_CHECKOUT_STARTED)->count(),
            'unique_visitors' => (clone $baseQuery)
                ->distinct('subscriber_device_id')
                ->count('subscriber_device_id'),
            'identified_visitors' => (clone $baseQuery)
                ->whereNotNull('subscriber_id')
                ->distinct('subscriber_id')
                ->count('subscriber_id'),
            'events_by_day' => static::forUser($userId)
                ->where('occurred_at', '>=', $since)
                ->selectRaw('DATE(occurred_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray(),
            'events_by_type' => static::forUser($userId)
                ->where('occurred_at', '>=', $since)
                ->selectRaw('event_type, COUNT(*) as count')
                ->groupBy('event_type')
                ->orderByDesc('count')
                ->pluck('count', 'event_type')
                ->toArray(),
            'top_pages' => static::forUser($userId)
                ->where('occurred_at', '>=', $since)
                ->ofType(self::TYPE_PAGE_VIEW)
                ->selectRaw('page_url, page_title, COUNT(*) as views')
                ->groupBy('page_url', 'page_title')
                ->orderByDesc('views')
                ->limit(10)
                ->get()
                ->toArray(),
            'top_products' => static::forUser($userId)
                ->where('occurred_at', '>=', $since)
                ->ofType(self::TYPE_PRODUCT_VIEW)
                ->whereNotNull('product_id')
                ->selectRaw('product_id, product_name, COUNT(*) as views')
                ->groupBy('product_id', 'product_name')
                ->orderByDesc('views')
                ->limit(10)
                ->get()
                ->toArray(),
        ];
    }

    /**
     * Check if a subscriber visited a URL matching a pattern
     */
    public static function subscriberVisitedUrl(
        int $subscriberId,
        string $urlPattern,
        ?int $withinDays = null
    ): bool {
        $query = static::forSubscriber($subscriberId);

        if ($withinDays) {
            $query->withinDays($withinDays);
        }

        return $query->urlMatches($urlPattern)->exists();
    }

    /**
     * Check if a subscriber viewed a specific product
     */
    public static function subscriberViewedProduct(
        int $subscriberId,
        string $productId,
        ?int $withinDays = null
    ): bool {
        $query = static::forSubscriber($subscriberId)
            ->ofType(self::TYPE_PRODUCT_VIEW)
            ->forProduct($productId);

        if ($withinDays) {
            $query->withinDays($withinDays);
        }

        return $query->exists();
    }

    /**
     * Get products viewed by a subscriber
     */
    public static function getProductsViewedBySubscriber(
        int $subscriberId,
        int $limit = 20
    ): array {
        return static::forSubscriber($subscriberId)
            ->ofType(self::TYPE_PRODUCT_VIEW)
            ->whereNotNull('product_id')
            ->selectRaw('product_id, product_name, MAX(product_price) as price, COUNT(*) as view_count, MAX(occurred_at) as last_viewed')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('last_viewed')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get cart abandonment candidates (added to cart but no checkout)
     */
    public static function getCartAbandonmentCandidates(
        int $userId,
        int $hoursThreshold = 24
    ): \Illuminate\Database\Eloquent\Collection {
        $cutoff = now()->subHours($hoursThreshold);

        // Get subscribers who added to cart but didn't checkout
        $addedToCart = static::forUser($userId)
            ->ofType(self::TYPE_ADD_TO_CART)
            ->where('occurred_at', '>=', $cutoff)
            ->whereNotNull('subscriber_id')
            ->select('subscriber_id', DB::raw('MAX(occurred_at) as last_cart_add'))
            ->groupBy('subscriber_id');

        $checkedOut = static::forUser($userId)
            ->ofType(self::TYPE_CHECKOUT_STARTED)
            ->where('occurred_at', '>=', $cutoff)
            ->whereNotNull('subscriber_id')
            ->pluck('subscriber_id');

        return Subscriber::whereIn('id', $addedToCart->pluck('subscriber_id'))
            ->whereNotIn('id', $checkedOut)
            ->get();
    }
}

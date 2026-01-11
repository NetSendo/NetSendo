<?php

namespace App\Http\Controllers;

use App\Models\PixelEvent;
use App\Models\SubscriberDevice;
use App\Services\LiveVisitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PixelSettingsController extends Controller
{
    public function __construct(
        protected LiveVisitorService $liveVisitorService
    ) {}

    /**
     * Display pixel settings and analytics
     */
    public function index()
    {
        $user = Auth::user();
        $userId = $user->id;

        // Get pixel statistics
        $stats = $this->getPixelStats($userId);

        // Generate embed code
        $embedCode = $this->generateEmbedCode($userId);

        // Get live visitors
        $liveVisitors = $this->liveVisitorService->getActiveVisitors($userId);

        return Inertia::render('Settings/PixelSettings/Index', [
            'stats' => $stats,
            'embedCode' => $embedCode,
            'userId' => $userId,
            'baseUrl' => config('app.url'),
            'liveVisitors' => $liveVisitors,
            'reverbConfig' => [
                'key' => env('VITE_REVERB_APP_KEY', config('broadcasting.connections.reverb.key')),
                'host' => env('VITE_REVERB_HOST', 'localhost'),
                'port' => (int) env('VITE_REVERB_PORT', 8085),
                'scheme' => env('VITE_REVERB_SCHEME', 'http'),
            ],
        ]);
    }

    /**
     * Get pixel tracking statistics
     */
    protected function getPixelStats(int $userId): array
    {
        $last7Days = now()->subDays(7);
        $last30Days = now()->subDays(30);

        // Basic counts
        $totalDevices = SubscriberDevice::forUser($userId)->count();
        $identifiedDevices = SubscriberDevice::forUser($userId)->identified()->count();

        // Event counts (last 7 days)
        $eventsLast7Days = PixelEvent::forUser($userId)
            ->where('occurred_at', '>=', $last7Days)
            ->count();

        $pageViewsLast7Days = PixelEvent::forUser($userId)
            ->ofType(PixelEvent::TYPE_PAGE_VIEW)
            ->where('occurred_at', '>=', $last7Days)
            ->count();

        $productViewsLast7Days = PixelEvent::forUser($userId)
            ->ofType(PixelEvent::TYPE_PRODUCT_VIEW)
            ->where('occurred_at', '>=', $last7Days)
            ->count();

        $addToCartLast7Days = PixelEvent::forUser($userId)
            ->ofType(PixelEvent::TYPE_ADD_TO_CART)
            ->where('occurred_at', '>=', $last7Days)
            ->count();

        $checkoutsLast7Days = PixelEvent::forUser($userId)
            ->ofType(PixelEvent::TYPE_CHECKOUT_STARTED)
            ->where('occurred_at', '>=', $last7Days)
            ->count();

        // Unique visitors (last 7 days)
        $uniqueVisitors = PixelEvent::forUser($userId)
            ->where('occurred_at', '>=', $last7Days)
            ->distinct('subscriber_device_id')
            ->count('subscriber_device_id');

        // Events by day (for chart)
        $eventsByDay = PixelEvent::forUser($userId)
            ->where('occurred_at', '>=', $last7Days)
            ->selectRaw('DATE(occurred_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Top pages
        $topPages = PixelEvent::forUser($userId)
            ->ofType(PixelEvent::TYPE_PAGE_VIEW)
            ->where('occurred_at', '>=', $last7Days)
            ->selectRaw('page_url, page_title, COUNT(*) as views')
            ->groupBy('page_url', 'page_title')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'url' => $row->page_url,
                'title' => $row->page_title ?: parse_url($row->page_url, PHP_URL_PATH),
                'views' => $row->views,
            ])
            ->toArray();

        // Top products
        $topProducts = PixelEvent::forUser($userId)
            ->ofType(PixelEvent::TYPE_PRODUCT_VIEW)
            ->whereNotNull('product_id')
            ->where('occurred_at', '>=', $last7Days)
            ->selectRaw('product_id, product_name, COUNT(*) as views')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->toArray();

        // Device types breakdown
        $deviceTypes = SubscriberDevice::forUser($userId)
            ->where('first_seen_at', '>=', $last30Days)
            ->selectRaw('device_type, COUNT(*) as count')
            ->groupBy('device_type')
            ->pluck('count', 'device_type')
            ->toArray();

        // Browser breakdown
        $browsers = SubscriberDevice::forUser($userId)
            ->where('first_seen_at', '>=', $last30Days)
            ->whereNotNull('browser')
            ->selectRaw('browser, COUNT(*) as count')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'browser')
            ->toArray();

        return [
            'summary' => [
                'total_events' => $eventsLast7Days,
                'page_views' => $pageViewsLast7Days,
                'product_views' => $productViewsLast7Days,
                'add_to_cart' => $addToCartLast7Days,
                'checkouts' => $checkoutsLast7Days,
                'unique_visitors' => $uniqueVisitors,
                'total_devices' => $totalDevices,
                'identified_devices' => $identifiedDevices,
            ],
            'events_by_day' => $eventsByDay,
            'top_pages' => $topPages,
            'top_products' => $topProducts,
            'device_types' => $deviceTypes,
            'browsers' => $browsers,
        ];
    }

    /**
     * Generate embed code for the pixel
     */
    protected function generateEmbedCode(int $userId): string
    {
        $baseUrl = config('app.url');

        return <<<HTML
<!-- NetSendo Pixel -->
<script>
(function(n,e,t,s,d,o){n.NetSendo=n.NetSendo||[];
n.NetSendo.push(['init',{userId:{$userId},apiUrl:'{$baseUrl}/t/pixel'}]);
var a=e.createElement(t);a.async=1;a.src='{$baseUrl}/t/pixel/{$userId}';
var m=e.getElementsByTagName(t)[0];m.parentNode.insertBefore(a,m);
})(window,document,'script');
</script>
<!-- End NetSendo Pixel -->
HTML;
    }

    /**
     * API endpoint to get stats (for AJAX refresh)
     */
    public function stats()
    {
        $stats = $this->getPixelStats(Auth::id());
        return response()->json($stats);
    }

    /**
     * API endpoint to get live visitors
     */
    public function liveVisitors()
    {
        $visitors = $this->liveVisitorService->getActiveVisitors(Auth::id());
        return response()->json([
            'visitors' => $visitors,
            'count' => count($visitors),
        ]);
    }
}

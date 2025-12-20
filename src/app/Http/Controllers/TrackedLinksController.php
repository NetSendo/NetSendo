<?php

namespace App\Http\Controllers;

use App\Models\EmailClick;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrackedLinksController extends Controller
{
    /**
     * Display tracked links dashboard
     */
    public function index(Request $request)
    {
        $query = EmailClick::query()
            ->select('url', DB::raw('COUNT(*) as clicks'), DB::raw('COUNT(DISTINCT subscriber_id) as unique_clicks'), DB::raw('MIN(created_at) as first_click'), DB::raw('MAX(created_at) as last_click'))
            ->groupBy('url');

        // Apply filters
        if ($request->filled('message_id')) {
            $query->where('message_id', $request->message_id);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->from)->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        if ($request->filled('search')) {
            $query->where('url', 'like', '%' . $request->search . '%');
        }

        // Get total stats
        $totalClicks = EmailClick::count();
        $uniqueLinks = EmailClick::distinct('url')->count('url');
        $uniqueClickers = EmailClick::distinct('subscriber_id')->count('subscriber_id');
        
        // Today stats
        $todayClicks = EmailClick::whereDate('created_at', today())->count();

        // Get paginated results
        $links = $query->orderByDesc('clicks')->paginate(20)->withQueryString();

        // Get messages for filter dropdown
        $messages = Message::select('id', 'subject')
            ->whereIn('id', EmailClick::distinct('message_id')->pluck('message_id'))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // Get daily trend (last 30 days)
        $thirtyDaysAgo = now()->subDays(30)->startOfDay();
        $dailyTrend = EmailClick::where('created_at', '>=', $thirtyDaysAgo)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as clicks'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill in missing days
        $trendData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $trendData[] = [
                'date' => $date,
                'label' => now()->subDays($i)->format('d.m'),
                'clicks' => $dailyTrend[$date]->clicks ?? 0,
            ];
        }

        return Inertia::render('Settings/TrackedLinks/Index', [
            'links' => $links,
            'stats' => [
                'total_clicks' => $totalClicks,
                'unique_links' => $uniqueLinks,
                'unique_clickers' => $uniqueClickers,
                'today_clicks' => $todayClicks,
            ],
            'trend' => $trendData,
            'messages' => $messages,
            'filters' => $request->only(['message_id', 'from', 'to', 'search']),
        ]);
    }

    /**
     * Show details for a specific URL
     */
    public function show(Request $request)
    {
        $url = $request->query('url');
        
        if (!$url) {
            abort(404);
        }

        $clicks = EmailClick::where('url', $url)
            ->with(['message:id,subject', 'subscriber:id,email,first_name,last_name'])
            ->orderByDesc('created_at')
            ->paginate(50);

        $stats = [
            'total_clicks' => EmailClick::where('url', $url)->count(),
            'unique_clicks' => EmailClick::where('url', $url)->distinct('subscriber_id')->count('subscriber_id'),
            'first_click' => EmailClick::where('url', $url)->min('created_at'),
            'last_click' => EmailClick::where('url', $url)->max('created_at'),
        ];

        return Inertia::render('Settings/TrackedLinks/Show', [
            'url' => $url,
            'clicks' => $clicks,
            'stats' => $stats,
        ]);
    }

    /**
     * Export tracked links to CSV
     */
    public function export(Request $request): StreamedResponse
    {
        $query = EmailClick::query()
            ->select('url', DB::raw('COUNT(*) as clicks'), DB::raw('COUNT(DISTINCT subscriber_id) as unique_clicks'), DB::raw('MIN(created_at) as first_click'), DB::raw('MAX(created_at) as last_click'))
            ->groupBy('url');

        // Apply filters
        if ($request->filled('message_id')) {
            $query->where('message_id', $request->message_id);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->from)->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        $links = $query->orderByDesc('clicks')->get();
        $filename = 'tracked_links_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($links) {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Header
            fputcsv($handle, ['URL', 'Kliknięcia', 'Unikalne kliknięcia', 'Pierwsze kliknięcie', 'Ostatnie kliknięcie'], ';');
            
            foreach ($links as $link) {
                fputcsv($handle, [
                    $link->url,
                    $link->clicks,
                    $link->unique_clicks,
                    $link->first_click,
                    $link->last_click,
                ], ';');
            }
            
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

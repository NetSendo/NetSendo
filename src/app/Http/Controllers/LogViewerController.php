<?php

namespace App\Http\Controllers;

use App\Models\LogSetting;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class LogViewerController extends Controller
{
    /**
     * Display the log viewer page
     */
    public function index()
    {
        $settings = [
            'retention_hours' => LogSetting::getRetentionHours(),
        ];

        $webhookStats = WebhookLog::getLast24HoursStats(auth()->id());

        return Inertia::render('Settings/Logs/Index', [
            'settings' => $settings,
            'webhookStats' => $webhookStats,
        ]);
    }

    /**
     * Get log file content via API
     */
    public function getLogContent(Request $request)
    {
        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            return response()->json([
                'content' => '',
                'size' => 0,
                'last_modified' => null,
                'exists' => false,
            ]);
        }

        $maxLines = $request->get('lines', 500);
        $search = $request->get('search', '');
        $level = $request->get('level', ''); // ERROR, WARNING, INFO, DEBUG

        try {
            $content = File::get($logPath);
            $lines = explode("\n", $content);

            // Filter by level if specified
            if ($level) {
                $lines = array_filter($lines, function($line) use ($level) {
                    return stripos($line, ".{$level}:") !== false ||
                           stripos($line, "[{$level}]") !== false;
                });
            }

            // Filter by search term if specified
            if ($search) {
                $lines = array_filter($lines, function($line) use ($search) {
                    return stripos($line, $search) !== false;
                });
            }

            // Get last N lines (most recent first)
            $lines = array_slice(array_reverse(array_values($lines)), 0, $maxLines);

            $stats = File::size($logPath);
            $lastModified = File::lastModified($logPath);

            return response()->json([
                'lines' => $lines,
                'total_lines' => count(explode("\n", $content)),
                'size' => $this->formatBytes($stats),
                'size_bytes' => $stats,
                'last_modified' => date('Y-m-d H:i:s', $lastModified),
                'exists' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'exists' => false,
            ], 500);
        }
    }

    /**
     * Get webhook logs via API
     */
    public function getWebhookLogs(Request $request)
    {
        $query = WebhookLog::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by event
        if ($request->has('event') && $request->event) {
            $query->where('event', $request->event);
        }

        // Search in URL or error message
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('url', 'like', "%{$search}%")
                  ->orWhere('error_message', 'like', "%{$search}%")
                  ->orWhere('event', 'like', "%{$search}%");
            });
        }

        $limit = min($request->get('limit', 100), 500);
        $logs = $query->limit($limit)->get();

        // Get unique events for filter dropdown
        $events = WebhookLog::where('user_id', auth()->id())
            ->distinct()
            ->pluck('event')
            ->toArray();

        return response()->json([
            'logs' => $logs,
            'events' => $events,
            'stats' => WebhookLog::getLast24HoursStats(auth()->id()),
        ]);
    }

    /**
     * Clear webhook logs
     */
    public function clearWebhookLogs(Request $request)
    {
        $days = $request->get('older_than_days', 30);

        $deleted = WebhookLog::where('user_id', auth()->id())
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
            'message' => __('logs.webhook_logs_cleared', ['count' => $deleted]),
        ]);
    }

    /**
     * Clear the log file
     */
    public function clearLog()
    {
        $logPath = storage_path('logs/laravel.log');

        try {
            if (File::exists($logPath)) {
                File::put($logPath, '');

                \Log::info('[LogViewer] Log file manually cleared by user');
            }

            return response()->json([
                'success' => true,
                'message' => __('logs.cleared'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current log settings
     */
    public function getSettings()
    {
        return response()->json([
            'retention_hours' => LogSetting::getRetentionHours(),
        ]);
    }

    /**
     * Save log settings
     */
    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'retention_hours' => 'required|integer|min:1|max:720', // max 30 days
        ]);

        LogSetting::setValue('retention_hours', $validated['retention_hours']);

        return back()->with('success', __('logs.settings.saved'));
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}


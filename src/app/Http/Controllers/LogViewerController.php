<?php

namespace App\Http\Controllers;

use App\Models\ApiRequestLog;
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
        $apiStats = $this->calculateApiStats();

        return Inertia::render('Settings/Logs/Index', [
            'settings' => $settings,
            'webhookStats' => $webhookStats,
            'apiStats' => $apiStats,
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
            $fileSize = File::size($logPath);
            $lastModified = File::lastModified($logPath);

            // Use tail-like approach to read last N lines without loading entire file
            // This prevents memory exhaustion with large log files
            $lines = $this->tailFile($logPath, $maxLines * 3, $search, $level); // Read more to account for filtering

            // Apply filters if needed
            if ($level || $search) {
                $lines = array_filter($lines, function($line) use ($level, $search) {
                    $matchesLevel = true;
                    $matchesSearch = true;

                    if ($level) {
                        $matchesLevel = stripos($line, ".{$level}:") !== false ||
                                       stripos($line, "[{$level}]") !== false;
                    }

                    if ($search) {
                        $matchesSearch = stripos($line, $search) !== false;
                    }

                    return $matchesLevel && $matchesSearch;
                });

                // Re-index and limit after filtering
                $lines = array_values($lines);
            }

            // Limit to requested number of lines
            $lines = array_slice($lines, 0, $maxLines);

            return response()->json([
                'lines' => $lines,
                'total_lines' => '~' . number_format($fileSize / 100), // Approximate line count
                'size' => $this->formatBytes($fileSize),
                'size_bytes' => $fileSize,
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
     * Read last N lines from file (tail-like approach)
     * Memory-efficient for large files
     */
    private function tailFile(string $path, int $lines, string $search = '', string $level = ''): array
    {
        $result = [];
        $handle = fopen($path, 'rb');

        if (!$handle) {
            return [];
        }

        // Seek to end of file
        fseek($handle, 0, SEEK_END);
        $pos = ftell($handle);

        // Read buffer size (read in chunks from end)
        $bufferSize = 8192;
        $buffer = '';
        $linesFound = 0;

        // Read from end of file backwards
        while ($pos > 0 && $linesFound < $lines) {
            // Calculate how much to read
            $readSize = min($bufferSize, $pos);
            $pos -= $readSize;

            // Seek and read
            fseek($handle, $pos, SEEK_SET);
            $chunk = fread($handle, $readSize);

            // Prepend to buffer
            $buffer = $chunk . $buffer;

            // Count newlines in buffer
            $linesFound = substr_count($buffer, "\n");
        }

        fclose($handle);

        // Split buffer into lines and reverse (most recent first)
        $allLines = explode("\n", $buffer);
        $allLines = array_filter($allLines, fn($line) => trim($line) !== '');
        $allLines = array_reverse(array_values($allLines));

        return array_slice($allLines, 0, $lines);
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
     * Get API request logs
     */
    public function getApiLogs(Request $request)
    {
        $query = ApiRequestLog::forUser(auth()->id())
            ->orderBy('created_at', 'desc');

        // Filter by method
        if ($request->has('method') && $request->method) {
            $query->forMethod($request->method);
        }

        // Filter by endpoint
        if ($request->has('endpoint') && $request->endpoint) {
            $query->forEndpoint($request->endpoint);
        }

        // Filter errors only
        if ($request->boolean('errors_only')) {
            $query->errors();
        }

        // Search in endpoint or response body
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('endpoint', 'like', "%{$search}%")
                  ->orWhereJsonContains('response_body->message', $search);
            });
        }

        $limit = min($request->get('limit', 100), 500);
        $logs = $query->limit($limit)->get();

        // Get unique endpoints for filter dropdown
        $endpoints = ApiRequestLog::forUser(auth()->id())
            ->distinct()
            ->pluck('endpoint')
            ->toArray();

        return response()->json([
            'logs' => $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'method' => $log->method,
                    'endpoint' => $log->endpoint,
                    'response_status' => $log->response_status,
                    'is_error' => $log->isError(),
                    'duration_ms' => $log->duration_ms,
                    'ip_address' => $log->ip_address,
                    'request_body' => $log->request_body,
                    'response_body' => $log->response_body,
                    'created_at' => $log->created_at->toIso8601String(),
                ];
            }),
            'endpoints' => $endpoints,
            'stats' => $this->calculateApiStats(),
        ]);
    }

    /**
     * Clear old API logs
     */
    public function clearApiLogs(Request $request)
    {
        $days = $request->get('older_than_days', 30);

        $deleted = ApiRequestLog::where('user_id', auth()->id())
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
            'message' => __('logs.api_logs_cleared', ['count' => $deleted]),
        ]);
    }

    /**
     * Get API statistics
     */
    protected function calculateApiStats(): array
    {
        $userId = auth()->id();
        $last24Hours = now()->subHours(24);

        return [
            'total_requests' => ApiRequestLog::forUser($userId)
                ->where('created_at', '>=', $last24Hours)
                ->count(),
            'error_requests' => ApiRequestLog::forUser($userId)
                ->where('created_at', '>=', $last24Hours)
                ->errors()
                ->count(),
            'avg_duration_ms' => (int) ApiRequestLog::forUser($userId)
                ->where('created_at', '>=', $last24Hours)
                ->avg('duration_ms'),
        ];
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

<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')
            ->orderBy('created_at', 'desc');

        // Filter by user
        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->byAction($request->action);
        }

        // Filter by action category (e.g., 'subscriber')
        if ($request->filled('category')) {
            $query->byActionPattern($request->category . '.*');
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        // Search in properties (JSON)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('properties', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        // Get users for filter dropdown
        $users = User::select('id', 'name', 'email')->get();

        // Get action categories
        $actionCategories = ActivityLog::getActionCategories();

        // Get stats for current view
        $stats = [
            'total' => ActivityLog::count(),
            'today' => ActivityLog::where('created_at', '>=', now()->startOfDay())->count(),
            'this_week' => ActivityLog::where('created_at', '>=', now()->startOfWeek())->count(),
        ];

        return Inertia::render('Settings/ActivityLogs/Index', [
            'logs' => $logs,
            'users' => $users,
            'actionCategories' => $actionCategories,
            'stats' => $stats,
            'filters' => $request->only(['user_id', 'action', 'category', 'from', 'to', 'search']),
        ]);
    }

    /**
     * Export logs to CSV
     */
    public function export(Request $request): StreamedResponse
    {
        $query = ActivityLog::with('user')->orderBy('created_at', 'desc');

        // Apply same filters as index
        if ($request->filled('user_id')) {
            $query->byUser($request->user_id);
        }
        if ($request->filled('action')) {
            $query->byAction($request->action);
        }
        if ($request->filled('category')) {
            $query->byActionPattern($request->category . '.*');
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        $logs = $query->limit(10000)->get();

        $filename = 'activity_logs_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM for Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Header
            fputcsv($handle, [
                'Data',
                'Użytkownik',
                'Akcja',
                'Opis',
                'Model',
                'ID',
                'Szczegóły',
                'IP',
            ], ';');
            
            foreach ($logs as $log) {
                $properties = $log->properties ?? [];
                $description = $properties['description'] ?? 
                              $properties['name'] ?? 
                              $properties['email'] ?? 
                              $properties['subject'] ?? 
                              '';
                
                fputcsv($handle, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user?->name ?? '-',
                    $log->action_name,
                    $description,
                    $log->model_type ? class_basename($log->model_type) : '-',
                    $log->model_id ?? '-',
                    json_encode($properties, JSON_UNESCAPED_UNICODE),
                    $log->ip_address ?? '-',
                ], ';');
            }
            
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Cleanup old logs
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $deleted = ActivityLog::cleanup($request->days);

        // Log this cleanup action
        ActivityLog::log('settings.updated', null, [
            'action' => 'activity_logs_cleanup',
            'deleted_count' => $deleted,
            'older_than_days' => $request->days,
        ]);

        return back()->with('success', __('Usunięto :count starych wpisów.', ['count' => $deleted]));
    }
}

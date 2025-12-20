<?php

namespace App\Http\Controllers;

use App\Models\CronJobLog;
use App\Models\CronSetting;
use App\Services\CronScheduleService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CronSettingsController extends Controller
{
    protected CronScheduleService $cronService;

    public function __construct(CronScheduleService $cronService)
    {
        $this->cronService = $cronService;
    }

    /**
     * Wyświetl panel ustawień CRON
     */
    public function index()
    {
        $settings = $this->cronService->getGlobalSettings();
        $stats = CronJobLog::getLast24HoursStats();
        $recentLogs = CronJobLog::getRecent(20);
        $listsAllowedNow = $this->cronService->getListsAllowedForDispatch();

        return Inertia::render('Settings/Cron/Index', [
            'settings' => $settings,
            'stats' => $stats,
            'recentLogs' => $recentLogs,
            'listsAllowedNow' => $listsAllowedNow,
            'isDispatchAllowed' => $this->cronService->isGlobalDispatchAllowed(),
        ]);
    }

    /**
     * Zapisz ustawienia globalne CRON
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'volume_per_minute' => 'required|integer|min:1|max:10000',
            'daily_maintenance_hour' => 'required|integer|min:0|max:23',
            'schedule' => 'required|array',
            'schedule.*.enabled' => 'required|boolean',
            'schedule.*.start' => 'required|integer|min:0|max:1440',
            'schedule.*.end' => 'required|integer|min:0|max:1440',
        ]);

        $this->cronService->saveGlobalSettings($validated);

        return back()->with('success', __('cron.settings_saved'));
    }

    /**
     * Pobierz statystyki (API)
     */
    public function stats()
    {
        return response()->json($this->cronService->getStats());
    }

    /**
     * Pobierz logi (API)
     */
    public function logs(Request $request)
    {
        $limit = min($request->get('limit', 50), 200);
        $logs = CronJobLog::getRecent($limit);

        return response()->json($logs);
    }

    /**
     * Wyczyść stare logi (ręcznie)
     */
    public function clearLogs(Request $request)
    {
        $days = $request->get('older_than_days', 7);
        $deleted = CronJobLog::cleanupOld($days);

        return back()->with('success', __('cron.logs_cleared', ['count' => $deleted]));
    }

    /**
     * Uruchom test wysyłki (debug)
     */
    public function testDispatch(Request $request)
    {
        if (!app()->environment('local')) {
            abort(403, 'Only available in local environment');
        }

        try {
            $stats = $this->cronService->processQueue();
            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pobierz status CRON dla globalnego bannera i dashboardu (API)
     */
    public function cronStatus()
    {
        $hasEverRun = CronJobLog::exists();
        $lastRun = CronJobLog::latest('started_at')->first();
        $stats = CronJobLog::getLast24HoursStats();
        
        return response()->json([
            'has_ever_run' => $hasEverRun,
            'is_active' => $hasEverRun,
            'last_run' => $lastRun?->started_at?->toIso8601String(),
            'last_run_status' => $lastRun?->status,
            'stats_24h' => $stats,
            'is_dispatch_allowed' => $this->cronService->isGlobalDispatchAllowed(),
        ]);
    }

    /**
     * Webhook endpoint for external automation tools (n8n, Make, Zapier)
     * Requires API token for authentication
     */
    public function webhookTrigger(Request $request)
    {
        // Validate token
        $token = $request->header('X-Cron-Token') ?? $request->get('token');
        $storedToken = CronSetting::getValue('webhook_token');
        
        if (!$storedToken || !hash_equals($storedToken, $token ?? '')) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or missing authentication token',
            ], 401);
        }
        
        try {
            $stats = $this->cronService->processQueue();
            
            return response()->json([
                'success' => true,
                'message' => 'CRON executed successfully',
                'stats' => $stats,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate or regenerate webhook token
     */
    public function generateWebhookToken()
    {
        $token = bin2hex(random_bytes(32));
        CronSetting::setValue('webhook_token', $token);
        
        return response()->json([
            'success' => true,
            'token' => $token,
            'webhook_url' => url('/api/cron/webhook'),
        ]);
    }

    /**
     * Get current webhook settings
     */
    public function webhookSettings()
    {
        $token = CronSetting::getValue('webhook_token');
        
        return response()->json([
            'has_token' => !empty($token),
            'token' => $token,
            'webhook_url' => url('/api/cron/webhook'),
        ]);
    }
}


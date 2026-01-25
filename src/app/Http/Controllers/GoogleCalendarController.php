<?php

namespace App\Http\Controllers;

use App\Models\GoogleIntegration;
use App\Models\UserCalendarConnection;
use App\Services\GoogleCalendarOAuthService;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleCalendarController extends Controller
{
    public function __construct(
        private GoogleCalendarOAuthService $oauthService,
        private GoogleCalendarService $calendarService
    ) {}

    /**
     * Display Calendar integration settings.
     */
    public function index(): Response
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $connection = UserCalendarConnection::where('user_id', $userId)
            ->with('googleIntegration')
            ->first();

        $integrations = GoogleIntegration::where('user_id', $userId)
            ->where('status', 'active')
            ->get(['id', 'name', 'client_id']);

        // Get available calendars if connected
        $calendars = [];
        if ($connection && $connection->is_active) {
            try {
                $calendars = $this->calendarService->listCalendars($connection);
            } catch (Exception $e) {
                Log::warning('Failed to fetch calendars', ['error' => $e->getMessage()]);
            }
        }

        return Inertia::render('Settings/Calendar/Index', [
            'connection' => $connection ? [
                'id' => $connection->id,
                'is_active' => $connection->is_active,
                'auto_sync_tasks' => $connection->auto_sync_tasks,
                'calendar_id' => $connection->calendar_id,
                'connected_email' => $connection->connected_email,
                'has_push_notifications' => $connection->hasPushNotifications(),
                'channel_expires_at' => $connection->channel_expires_at?->toISOString(),
                'integration_name' => $connection->googleIntegration?->name,
                'sync_settings' => $connection->sync_settings ?? [],
            ] : null,
            'integrations' => $integrations,
            'calendars' => $calendars,
            'webhook_url' => route('webhooks.google-calendar'),
            'calendar_redirect_uri' => route('settings.calendar.callback'),
        ]);
    }

    /**
     * Initiate OAuth flow for Calendar connection.
     */
    public function connect(GoogleIntegration $integration): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        // Verify ownership
        if ($integration->user_id !== $userId) {
            abort(403);
        }

        $state = $this->oauthService->encodeState([
            'integration_id' => $integration->id,
            'user_id' => auth()->id(),
            'type' => 'calendar',
        ]);

        $url = $this->oauthService->getAuthorizationUrl($integration, $state);

        return redirect()->away($url);
    }

    /**
     * Handle OAuth callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()
                ->route('settings.calendar.index')
                ->with('error', __('calendar.oauth_denied'));
        }

        try {
            $state = $this->oauthService->decodeState($request->input('state'));

            // Verify state
            if (($state['user_id'] ?? null) != auth()->id()) {
                throw new Exception('Invalid state: user mismatch');
            }

            $integration = GoogleIntegration::findOrFail($state['integration_id']);

            // Exchange code for tokens
            $tokens = $this->oauthService->exchangeCodeForTokens(
                $integration,
                $request->input('code')
            );

            // Get user email
            $userInfo = $this->oauthService->getUserInfo($tokens['access_token']);

            $userId = auth()->user()->admin_user_id ?? auth()->id();

            // Create or update connection
            $connection = UserCalendarConnection::updateOrCreate(
                [
                    'user_id' => $userId,
                    'google_integration_id' => $integration->id,
                ],
                [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? null,
                    'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                    'connected_email' => $userInfo['email'] ?? null,
                    'is_active' => true,
                    'auto_sync_tasks' => true,
                ]
            );

            // Set up push notifications
            try {
                $this->calendarService->watchCalendar($connection);
            } catch (Exception $e) {
                Log::warning('Failed to set up Calendar push notifications', [
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the connection, just log and continue
            }

            return redirect()
                ->route('settings.calendar.index')
                ->with('success', __('calendar.connected'));

        } catch (Exception $e) {
            Log::error('Calendar OAuth callback failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('settings.calendar.index')
                ->with('error', __('calendar.connection_failed'));
        }
    }

    /**
     * Disconnect Calendar.
     */
    public function disconnect(UserCalendarConnection $connection): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($connection->user_id !== $userId) {
            abort(403);
        }

        try {
            // Stop push notifications
            $this->calendarService->stopWatch($connection);

            // Revoke tokens
            $this->oauthService->revokeTokens($connection);

        } catch (Exception $e) {
            Log::warning('Error during Calendar disconnect', [
                'error' => $e->getMessage(),
            ]);
        }

        // Delete connection
        $connection->delete();

        return redirect()
            ->route('settings.calendar.index')
            ->with('success', __('calendar.disconnected'));
    }

    /**
     * Update sync settings.
     */
    public function updateSettings(Request $request, UserCalendarConnection $connection): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($connection->user_id !== $userId) {
            abort(403);
        }

        $validated = $request->validate([
            'calendar_id' => 'nullable|string|max:255',
            'auto_sync_tasks' => 'boolean',
            'sync_settings' => 'nullable|array',
            'sync_settings.import_external_events' => 'nullable|boolean',
        ]);

        $connection->update($validated);

        // If calendar changed, update push notifications
        if (isset($validated['calendar_id']) && $validated['calendar_id'] !== $connection->calendar_id) {
            try {
                $this->calendarService->stopWatch($connection);
                $connection->update(['calendar_id' => $validated['calendar_id']]);
                $this->calendarService->watchCalendar($connection);
            } catch (Exception $e) {
                Log::warning('Failed to update Calendar watch', ['error' => $e->getMessage()]);
            }
        }

        return redirect()
            ->route('settings.calendar.index')
            ->with('success', __('calendar.settings_updated'));
    }

    /**
     * Manual sync trigger.
     */
    public function syncNow(UserCalendarConnection $connection): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($connection->user_id !== $userId) {
            abort(403);
        }

        try {
            // Get all tasks that should be synced
            $tasks = \App\Models\CrmTask::forUser($userId)
                ->where('sync_to_calendar', true)
                ->whereNull('google_calendar_event_id')
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->get();

            foreach ($tasks as $task) {
                \App\Jobs\SyncTaskToCalendar::dispatch($task);
            }

            return redirect()
                ->route('settings.calendar.index')
                ->with('success', __('calendar.sync_started', ['count' => $tasks->count()]));

        } catch (Exception $e) {
            Log::error('Manual Calendar sync failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('settings.calendar.index')
                ->with('error', __('calendar.sync_failed'));
        }
    }

    /**
     * Refresh push notification channel.
     */
    public function refreshChannel(UserCalendarConnection $connection): RedirectResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($connection->user_id !== $userId) {
            abort(403);
        }

        try {
            $this->calendarService->stopWatch($connection);
            $this->calendarService->watchCalendar($connection);

            return redirect()
                ->route('settings.calendar.index')
                ->with('success', __('calendar.channel_refreshed'));

        } catch (Exception $e) {
            Log::error('Failed to refresh Calendar channel', ['error' => $e->getMessage()]);

            return redirect()
                ->route('settings.calendar.index')
                ->with('error', __('calendar.channel_refresh_failed'));
        }
    }

    /**
     * Get sync status (API).
     */
    public function syncStatus(): \Illuminate\Http\JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        $connection = UserCalendarConnection::where('user_id', $userId)->first();

        if (!$connection) {
            return response()->json([
                'connected' => false,
            ]);
        }

        $pendingSyncCount = \App\Models\CrmTask::forUser($userId)
            ->where('sync_to_calendar', true)
            ->whereNull('google_calendar_event_id')
            ->count();

        $syncedCount = \App\Models\CrmTask::forUser($userId)
            ->syncedToCalendar()
            ->count();

        return response()->json([
            'connected' => $connection->is_active,
            'email' => $connection->connected_email,
            'pending_sync' => $pendingSyncCount,
            'synced' => $syncedCount,
            'has_push' => $connection->hasPushNotifications(),
            'channel_expires' => $connection->channel_expires_at?->toISOString(),
        ]);
    }

    /**
     * Bulk sync all existing pending tasks to Calendar.
     */
    public function bulkSync(UserCalendarConnection $connection): \Illuminate\Http\JsonResponse
    {
        $userId = auth()->user()->admin_user_id ?? auth()->id();

        if ($connection->user_id !== $userId) {
            abort(403);
        }

        try {
            // Get all pending tasks that are NOT yet synced
            $tasks = \App\Models\CrmTask::forUser($userId)
                ->pending()
                ->whereNull('google_calendar_event_id')
                ->get();

            $syncedCount = 0;
            foreach ($tasks as $task) {
                $task->update(['sync_to_calendar' => true]);
                \App\Jobs\SyncTaskToCalendar::dispatch($task);
                $syncedCount++;
            }

            return response()->json([
                'success' => true,
                'synced_count' => $syncedCount,
                'message' => "Rozpoczęto synchronizację {$syncedCount} zadań z kalendarzem.",
            ]);

        } catch (Exception $e) {
            Log::error('Bulk Calendar sync failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Synchronizacja nie powiodła się: ' . $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\CrmTask;
use App\Models\UserCalendarConnection;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class ProcessCalendarWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $channelId,
        public string $resourceId,
        public string $resourceState
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GoogleCalendarService $calendarService): void
    {
        // Find the connection for this channel
        $connection = UserCalendarConnection::where('channel_id', $this->channelId)
            ->where('resource_id', $this->resourceId)
            ->active()
            ->first();

        if (!$connection) {
            Log::warning('Calendar webhook received for unknown channel', [
                'channel_id' => $this->channelId,
                'resource_id' => $this->resourceId,
            ]);
            return;
        }

        // Handle different resource states
        switch ($this->resourceState) {
            case 'sync':
                // Initial sync confirmation - nothing to do
                Log::debug('Calendar sync confirmed', ['connection_id' => $connection->id]);
                break;

            case 'exists':
            case 'update':
                // Changes exist - fetch and process them
                $this->processChanges($calendarService, $connection);
                break;

            case 'not_exists':
                // Resource was deleted
                Log::info('Calendar resource deleted', ['connection_id' => $connection->id]);
                $connection->clearChannel();
                break;

            default:
                Log::warning('Unknown Calendar webhook state', [
                    'state' => $this->resourceState,
                    'connection_id' => $connection->id,
                ]);
        }
    }

    /**
     * Process calendar changes.
     */
    private function processChanges(GoogleCalendarService $calendarService, UserCalendarConnection $connection): void
    {
        try {
            // Fetch recent events (last 24 hours to catch updates)
            $events = $calendarService->listEvents(
                $connection,
                now()->subDay(),
                now()->addMonths(3)
            );

            foreach ($events['items'] as $event) {
                $this->processEvent($event, $calendarService, $connection);
            }

            Log::info('Processed Calendar webhook', [
                'connection_id' => $connection->id,
                'events_count' => count($events['items']),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to process Calendar webhook', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process a single event from the webhook.
     */
    private function processEvent(array $event, GoogleCalendarService $calendarService, UserCalendarConnection $connection): void
    {
        $eventId = $event['id'] ?? null;
        if (!$eventId) {
            return;
        }

        // Check if this event is linked to a NetSendo task
        $taskId = $calendarService->getTaskIdFromEvent($event);

        if ($taskId) {
            // Event was created from a CRM task - update the task
            $this->updateExistingTask($taskId, $event, $calendarService);
        } else {
            // Event was not created by NetSendo - could optionally create a task
            // For now, we only sync tasks that originated in CRM
            $this->handleExternalEvent($event, $connection, $calendarService);
        }
    }

    /**
     * Update an existing CRM task from Calendar event changes.
     */
    private function updateExistingTask(int $taskId, array $event, GoogleCalendarService $calendarService): void
    {
        $task = CrmTask::find($taskId);

        if (!$task) {
            Log::warning('Task not found for Calendar event update', [
                'task_id' => $taskId,
                'event_id' => $event['id'],
            ]);
            return;
        }

        // Check if the Calendar change is newer than our last sync
        $eventUpdated = isset($event['updated']) ? Carbon::parse($event['updated']) : now();

        if ($task->google_calendar_synced_at && $task->google_calendar_synced_at->gte($eventUpdated)) {
            // Our data is newer or equal - skip update
            return;
        }

        // Handle cancelled events
        if (($event['status'] ?? '') === 'cancelled') {
            $task->clearCalendarSync();
            // Optionally delete or mark task as cancelled
            if ($task->status !== 'cancelled') {
                $task->update(['status' => 'cancelled']);
                Log::info('Task cancelled from Calendar', ['task_id' => $task->id]);
            }
            return;
        }

        // Update task with event data
        $task->updateFromCalendarEvent($event);

        Log::info('Task updated from Calendar', [
            'task_id' => $task->id,
            'event_id' => $event['id'],
        ]);
    }

    /**
     * Handle events not created by NetSendo.
     */
    private function handleExternalEvent(array $event, UserCalendarConnection $connection, GoogleCalendarService $calendarService): void
    {
        // Check if we already have a task linked to this event
        $existingTask = CrmTask::where('google_calendar_event_id', $event['id'])
            ->where('user_id', $connection->user_id)
            ->first();

        if ($existingTask) {
            // Update existing task
            $existingTask->updateFromCalendarEvent($event);
            return;
        }

        // For now, we don't auto-create tasks from external events
        // This could be enabled via sync_settings if desired
        $syncSettings = $connection->sync_settings ?? [];

        if (!($syncSettings['import_external_events'] ?? false)) {
            return;
        }

        // Skip cancelled events
        if (($event['status'] ?? '') === 'cancelled') {
            return;
        }

        // Create new task from external event
        $taskData = $calendarService->eventToTaskData($event);

        $task = CrmTask::create([
            ...$taskData,
            'user_id' => $connection->user_id,
            'owner_id' => $connection->user_id,
            'type' => 'task',
            'priority' => 'medium',
            'status' => 'pending',
            'google_calendar_event_id' => $event['id'],
            'google_calendar_id' => $connection->calendar_id,
            'google_calendar_synced_at' => now(),
            'sync_to_calendar' => true,
        ]);

        Log::info('Created task from external Calendar event', [
            'task_id' => $task->id,
            'event_id' => $event['id'],
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('ProcessCalendarWebhook job failed permanently', [
            'channel_id' => $this->channelId,
            'resource_id' => $this->resourceId,
            'error' => $exception->getMessage(),
        ]);
    }
}

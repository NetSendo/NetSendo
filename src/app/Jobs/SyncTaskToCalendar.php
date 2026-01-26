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
use Exception;

class SyncTaskToCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public CrmTask $task,
        public string $action = 'upsert' // upsert, delete
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GoogleCalendarService $calendarService): void
    {
        // Get user's active Calendar connection
        $connection = UserCalendarConnection::where('user_id', $this->task->user_id)
            ->active()
            ->autoSync()
            ->first();

        if (!$connection) {
            Log::debug('No active Calendar connection for user', [
                'user_id' => $this->task->user_id,
                'task_id' => $this->task->id,
            ]);
            return;
        }

        try {
            if ($this->action === 'delete') {
                $this->handleDelete($calendarService, $connection);
            } else {
                $this->handleUpsert($calendarService, $connection);
            }
        } catch (Exception $e) {
            Log::error('Failed to sync task to Calendar', [
                'task_id' => $this->task->id,
                'action' => $this->action,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle create or update action.
     */
    private function handleUpsert(GoogleCalendarService $calendarService, UserCalendarConnection $connection): void
    {
        // Skip if task shouldn't be synced
        if (!$this->task->shouldSyncToCalendar()) {
            // If task was synced before but now shouldn't be, delete the event
            if ($this->task->isSyncedToCalendar()) {
                $this->handleDelete($calendarService, $connection);
            }
            return;
        }

        // Create Zoom meeting if enabled and not already created
        if ($this->task->include_zoom_meeting && !$this->task->zoom_meeting_id) {
            $this->createZoomMeeting();
        }

        if ($this->task->isSyncedToCalendar()) {
            // Update existing event
            $event = $calendarService->updateEvent($this->task, $connection);
        } else {
            // Create new event - use task's selected_calendar_id if set
            $calendarId = $this->task->selected_calendar_id ?? $connection->calendar_id;
            $event = $calendarService->createEventFromTask($this->task, $connection, $calendarId);

            if ($event && isset($event['id'])) {
                $this->task->markCalendarSynced($event['id'], $calendarId);
            }
        }

        if (!$event) {
            throw new Exception('Failed to sync event to Calendar');
        }
    }

    /**
     * Create Zoom meeting for the task.
     */
    private function createZoomMeeting(): void
    {
        try {
            // Get the user's active Zoom connection
            $zoomConnection = \App\Models\UserZoomConnection::forUser($this->task->user_id)
                ->active()
                ->first();

            if (!$zoomConnection) {
                Log::debug('No active Zoom connection for user, skipping Zoom meeting creation', [
                    'user_id' => $this->task->user_id,
                    'task_id' => $this->task->id,
                ]);
                return;
            }

            $zoomService = app(\App\Services\ZoomMeetingService::class);
            $meetingData = $zoomService->createMeetingFromTask($this->task, $zoomConnection);

            if ($meetingData) {
                $this->task->update([
                    'zoom_meeting_id' => $meetingData['id'] ?? null,
                    'zoom_join_url' => $meetingData['join_url'] ?? null,
                    'zoom_start_url' => $meetingData['start_url'] ?? null,
                    'zoom_password' => $meetingData['password'] ?? null,
                ]);

                Log::info('Zoom meeting created for task', [
                    'task_id' => $this->task->id,
                    'zoom_meeting_id' => $meetingData['id'] ?? null,
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to create Zoom meeting for task', [
                'task_id' => $this->task->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the entire job if Zoom fails, just log and continue
        }
    }

    /**
     * Handle delete action.
     */
    private function handleDelete(GoogleCalendarService $calendarService, UserCalendarConnection $connection): void
    {
        if (!$this->task->google_calendar_event_id) {
            return;
        }

        $calendarId = $this->task->google_calendar_id ?? $connection->calendar_id;

        $calendarService->deleteEvent(
            $this->task->google_calendar_event_id,
            $calendarId,
            $connection
        );

        $this->task->clearCalendarSync();
    }

    /**
     * Determine the time before the job should be retried.
     */
    public function retryAfter(): int
    {
        return 60 * ($this->attempts() ?? 1);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('SyncTaskToCalendar job failed permanently', [
            'task_id' => $this->task->id,
            'action' => $this->action,
            'error' => $exception->getMessage(),
        ]);
    }
}

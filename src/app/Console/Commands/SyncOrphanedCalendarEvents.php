<?php

namespace App\Console\Commands;

use App\Models\CrmTask;
use App\Models\UserCalendarConnection;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOrphanedCalendarEvents extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'calendar:sync-orphaned-events
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--days=90 : Number of days to look back for events}';

    /**
     * The console command description.
     */
    protected $description = 'Find and delete orphaned Google Calendar events (events linked to deleted tasks)';

    public function __construct(
        private GoogleCalendarService $calendarService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $days = (int) $this->option('days');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No events will be deleted');
        }

        $connections = UserCalendarConnection::active()->autoSync()->get();

        if ($connections->isEmpty()) {
            $this->info('No active calendar connections found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$connections->count()} active calendar connections.");

        $totalOrphaned = 0;
        $totalDeleted = 0;
        $totalFailed = 0;

        foreach ($connections as $connection) {
            $this->info("\nProcessing connection ID: {$connection->id} ({$connection->connected_email})");

            try {
                $result = $this->processConnection($connection, $days, $dryRun);
                $totalOrphaned += $result['orphaned'];
                $totalDeleted += $result['deleted'];
                $totalFailed += $result['failed'];
            } catch (\Exception $e) {
                $this->error("  ❌ Error: {$e->getMessage()}");
                Log::error('Failed to process calendar connection for orphaned events', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info("Summary:");
        $this->info("  Orphaned events found: {$totalOrphaned}");

        if ($dryRun) {
            $this->info("  Would be deleted: {$totalOrphaned}");
        } else {
            $this->info("  Successfully deleted: {$totalDeleted}");
            if ($totalFailed > 0) {
                $this->warn("  Failed to delete: {$totalFailed}");
            }
        }

        Log::info('Orphaned calendar events sync completed', [
            'orphaned' => $totalOrphaned,
            'deleted' => $totalDeleted,
            'failed' => $totalFailed,
            'dry_run' => $dryRun,
        ]);

        return $totalFailed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Process a single calendar connection.
     */
    private function processConnection(UserCalendarConnection $connection, int $days, bool $dryRun): array
    {
        $result = ['orphaned' => 0, 'deleted' => 0, 'failed' => 0];

        // Define time range
        $from = Carbon::now()->subDays($days);
        $to = Carbon::now()->addDays(365); // Also check future events

        try {
            $events = $this->calendarService->listEvents($connection, $from, $to);
        } catch (\Exception $e) {
            $this->error("  Failed to fetch events: {$e->getMessage()}");
            throw $e;
        }

        $items = $events['items'] ?? [];
        $this->info("  Found " . count($items) . " events to check.");

        foreach ($items as $event) {
            // Check if this is a NetSendo event
            $taskId = $this->calendarService->getTaskIdFromEvent($event);

            if (!$taskId) {
                // Not a NetSendo event, skip
                continue;
            }

            // Check if task exists in database
            $taskExists = CrmTask::where('id', $taskId)->exists();

            if (!$taskExists) {
                $result['orphaned']++;
                $eventId = $event['id'] ?? 'unknown';
                $eventTitle = $event['summary'] ?? 'Untitled';

                if ($dryRun) {
                    $this->warn("  🗑️  Would delete: [{$eventId}] {$eventTitle} (task #{$taskId} not found)");
                } else {
                    $this->info("  🗑️  Deleting: [{$eventId}] {$eventTitle} (task #{$taskId} not found)");

                    try {
                        $deleted = $this->calendarService->deleteEvent(
                            $eventId,
                            $connection->calendar_id,
                            $connection
                        );

                        if ($deleted) {
                            $result['deleted']++;
                            Log::info('Deleted orphaned calendar event', [
                                'event_id' => $eventId,
                                'task_id' => $taskId,
                                'connection_id' => $connection->id,
                            ]);
                        } else {
                            $result['failed']++;
                        }
                    } catch (\Exception $e) {
                        $result['failed']++;
                        $this->error("    Failed to delete event: {$e->getMessage()}");
                        Log::error('Failed to delete orphaned calendar event', [
                            'event_id' => $eventId,
                            'task_id' => $taskId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        if ($result['orphaned'] === 0) {
            $this->info("  ✅ No orphaned events found.");
        }

        return $result;
    }
}

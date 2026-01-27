<?php

namespace App\Console\Commands;

use App\Jobs\SyncTaskToCalendar;
use App\Models\CrmTask;
use App\Models\UserCalendarConnection;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPendingCalendarTasks extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'calendar:sync-pending-tasks
                            {--limit=50 : Maximum number of tasks to sync per run}
                            {--dry-run : Show what would be synced without actually syncing}';

    /**
     * The console command description.
     */
    protected $description = 'Find and sync tasks that should be synced to Google Calendar but are not yet synced';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No tasks will be synced');
        }

        // Get users with active calendar connections
        $activeUserIds = UserCalendarConnection::active()
            ->autoSync()
            ->pluck('user_id')
            ->unique()
            ->toArray();

        if (empty($activeUserIds)) {
            $this->info('No users with active calendar connections found.');
            return Command::SUCCESS;
        }

        $this->info('Found ' . count($activeUserIds) . ' users with active calendar connections.');

        // Find tasks that should be synced but are not yet synced
        // - sync_to_calendar = true
        // - google_calendar_event_id IS NULL
        // - status is not 'cancelled'
        // - due_at is in the future or within last 7 days (to catch recently created tasks)
        $pendingTasks = CrmTask::query()
            ->whereIn('user_id', $activeUserIds)
            ->where('sync_to_calendar', true)
            ->whereNull('google_calendar_event_id')
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($query) {
                $query->whereNull('due_at')
                    ->orWhere('due_at', '>=', Carbon::now()->subDays(7));
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($pendingTasks->isEmpty()) {
            $this->info('✅ No pending tasks to sync.');
            Log::debug('Calendar sync check: No pending tasks found');
            return Command::SUCCESS;
        }

        $this->info("Found {$pendingTasks->count()} pending tasks to sync.");

        $synced = 0;
        $failed = 0;

        foreach ($pendingTasks as $task) {
            $taskInfo = "Task #{$task->id}: {$task->title}";

            if ($dryRun) {
                $this->line("  Would sync: {$taskInfo}");
                $synced++;
                continue;
            }

            try {
                SyncTaskToCalendar::dispatch($task);
                $this->line("  ✓ Dispatched: {$taskInfo}");
                $synced++;

                Log::info('Dispatched pending calendar sync', [
                    'task_id' => $task->id,
                    'user_id' => $task->user_id,
                ]);
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$taskInfo} - {$e->getMessage()}");
                $failed++;

                Log::error('Failed to dispatch calendar sync', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info("Summary:");

        if ($dryRun) {
            $this->info("  Would sync: {$synced} tasks");
        } else {
            $this->info("  Dispatched: {$synced} tasks");
            if ($failed > 0) {
                $this->warn("  Failed: {$failed} tasks");
            }
        }

        Log::info('Pending calendar tasks sync completed', [
            'synced' => $synced,
            'failed' => $failed,
            'dry_run' => $dryRun,
        ]);

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

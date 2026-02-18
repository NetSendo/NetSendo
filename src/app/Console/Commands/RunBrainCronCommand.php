<?php

namespace App\Console\Commands;

use App\Models\AiBrainActivityLog;
use App\Models\AiBrainSettings;
use App\Models\User;
use App\Services\Brain\AgentOrchestrator;
use App\Services\Brain\Skills\MarketingSalesSkill;
use App\Services\Brain\Telegram\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunBrainCronCommand extends Command
{
    protected $signature = 'brain:run-cron';

    protected $description = 'Run Brain AI orchestration for users with active cron scheduling';

    public function handle(AgentOrchestrator $orchestrator): int
    {
        $this->info('[Brain CRON] Starting orchestration cycle...');

        // Find all users with cron enabled
        $settings = AiBrainSettings::where('cron_enabled', true)
            ->whereNotNull('cron_interval_minutes')
            ->get();

        if ($settings->isEmpty()) {
            $this->info('[Brain CRON] No users with active cron. Skipping.');
            return self::SUCCESS;
        }

        $processed = 0;
        $skipped = 0;

        foreach ($settings as $setting) {
            $user = User::find($setting->user_id);
            if (!$user) {
                $this->warn("[Brain CRON] User #{$setting->user_id} not found. Skipping.");
                continue;
            }

            // Check if enough time has passed since last cron run
            $intervalMinutes = (int) $setting->cron_interval_minutes;
            if ($setting->last_cron_run_at) {
                $minutesSinceLastRun = $setting->last_cron_run_at->diffInMinutes(now());
                if ($minutesSinceLastRun < $intervalMinutes) {
                    $remaining = $intervalMinutes - $minutesSinceLastRun;
                    $this->line("[Brain CRON] User #{$user->id} ({$user->name}): next run in {$remaining}min. Skipping.");
                    $skipped++;
                    continue;
                }
            }

            $this->info("[Brain CRON] Processing user #{$user->id} ({$user->name})...");

            try {
                $this->processUserCron($orchestrator, $user, $setting);
                $processed++;
            } catch (\Exception $e) {
                $this->error("[Brain CRON] Error for user #{$user->id}: {$e->getMessage()}");
                Log::error('Brain CRON error', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Log the error
                AiBrainActivityLog::logEvent(
                    $user->id,
                    'cron_error',
                    'error',
                    null,
                    ['error' => $e->getMessage()],
                );
            }
        }

        $this->info("[Brain CRON] Cycle complete. Processed: {$processed}, Skipped: {$skipped}");
        return self::SUCCESS;
    }

    /**
     * Process cron orchestration for a single user.
     */
    private function processUserCron(AgentOrchestrator $orchestrator, User $user, AiBrainSettings $settings): void
    {
        // Get suggested tasks from MarketingSalesSkill
        $suggestedTasks = MarketingSalesSkill::getSuggestedTasks($user);

        // Filter to high-priority tasks only for automatic execution
        $highPriorityTasks = collect($suggestedTasks)
            ->where('priority', 'high')
            ->values();

        if ($highPriorityTasks->isEmpty()) {
            $this->line("[Brain CRON] User #{$user->id}: No high-priority tasks. Updating timestamps.");

            // Update timestamps even if no tasks — cron ran successfully
            $settings->update([
                'last_cron_run_at' => now(),
            ]);

            AiBrainActivityLog::logEvent(
                $user->id,
                'cron_run',
                'success',
                null,
                [
                    'message' => 'No high-priority tasks found.',
                    'suggested_tasks_count' => count($suggestedTasks),
                    'high_priority_count' => 0,
                ],
            );

            // Telegram report: no tasks
            $this->sendTelegramReport($settings, $user, collect(), []);

            return;
        }

        $this->info("[Brain CRON] User #{$user->id}: Found {$highPriorityTasks->count()} high-priority tasks.");

        $executedCount = 0;
        $taskResults = [];

        foreach ($highPriorityTasks as $i => $task) {
            $this->line("[Brain CRON]   → Executing: {$task['title']}");

            try {
                // Use the task's action as the message to the orchestrator
                $result = $orchestrator->processMessage(
                    $task['action'],
                    $user,
                    'cron', // channel = cron to distinguish from manual
                    null,   // no specific conversation
                    true,   // force new conversation
                );

                $status = ($result['type'] ?? '') === 'error' ? 'error' : 'success';
                $taskResults[$i] = $status;

                $this->line("[Brain CRON]   ✓ Result: {$status}");

                AiBrainActivityLog::logEvent(
                    $user->id,
                    'cron_task_executed',
                    $status,
                    $task['agent'] ?? null,
                    [
                        'task_id' => $task['id'] ?? null,
                        'task_title' => $task['title'] ?? null,
                        'category' => $task['category'] ?? null,
                        'result_type' => $result['type'] ?? null,
                    ],
                );

                $executedCount++;
            } catch (\Exception $e) {
                $taskResults[$i] = 'error';
                $this->warn("[Brain CRON]   ✗ Task failed: {$e->getMessage()}");
                Log::warning('Brain CRON task execution failed', [
                    'user_id' => $user->id,
                    'task' => $task['title'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update timestamps
        $settings->update([
            'last_cron_run_at' => now(),
            'last_activity_at' => now(),
        ]);

        // Telegram report: completed tasks
        $this->sendTelegramReport($settings, $user, $highPriorityTasks, $taskResults);

        $this->info("[Brain CRON] User #{$user->id}: Executed {$executedCount}/{$highPriorityTasks->count()} tasks.");
    }

    /**
     * Send a summary report to Telegram (if connected).
     */
    private function sendTelegramReport(
        AiBrainSettings $settings,
        User $user,
        $tasks,
        array $taskResults,
    ): void {
        if (!$settings->isTelegramConnected() || empty($settings->telegram_chat_id)) {
            return;
        }

        try {
            $telegram = app(TelegramBotService::class);
            $interval = (int) $settings->cron_interval_minutes;
            $nextRun = now()->addMinutes($interval)->format('H:i');

            if ($tasks->isEmpty()) {
                $message = "🧠 *Brain CRON Report*\n\n"
                    . "✅ Cykl zakończony — brak zadań o wysokim priorytecie.\n"
                    . "\n⏰ Następne uruchomienie: ~{$nextRun}";
            } else {
                $lines = ["🧠 *Brain CRON Report*\n"];
                $lines[] = "📝 Znalezionych zadań: {$tasks->count()}\n";

                foreach ($tasks as $i => $task) {
                    $status = $taskResults[$i] ?? 'skipped';
                    $icon = $status === 'success' ? '✅' : ($status === 'error' ? '❌' : '⏭️');
                    $lines[] = "{$icon} {$task['title']}";
                }

                $successCount = collect($taskResults)->filter(fn($r) => $r === 'success')->count();
                $lines[] = "\n📊 Wykonano: {$successCount}/{$tasks->count()}";
                $lines[] = "⏰ Następne uruchomienie: ~{$nextRun}";

                $message = implode("\n", $lines);
            }

            $telegram->sendMessage($settings->telegram_chat_id, $message);
        } catch (\Exception $e) {
            Log::warning('Brain CRON Telegram report failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

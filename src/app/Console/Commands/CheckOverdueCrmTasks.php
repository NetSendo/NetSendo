<?php

namespace App\Console\Commands;

use App\Events\CrmTaskOverdue;
use App\Models\CrmTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverdueCrmTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:check-overdue-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue CRM tasks and fire events';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for overdue CRM tasks...');

        $overdueTasks = CrmTask::overdue()
            ->where('overdue_notified', false)
            ->get();

        $count = 0;

        foreach ($overdueTasks as $task) {
            try {
                // Fire the overdue event
                event(new CrmTaskOverdue($task));

                // Mark as notified to avoid duplicate notifications
                $task->update(['overdue_notified' => true]);

                $count++;

                Log::info('CRM: Task overdue event fired', [
                    'task_id' => $task->id,
                    'title' => $task->title,
                ]);
            } catch (\Exception $e) {
                Log::error('CRM: Failed to process overdue task', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Processed {$count} overdue tasks.");

        return self::SUCCESS;
    }
}

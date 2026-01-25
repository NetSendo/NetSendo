<?php

namespace App\Console\Commands;

use App\Models\CrmTask;
use App\Models\Notification;
use App\Mail\TaskReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTaskReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crm:send-task-reminders {--dry-run : Show what would be sent without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Send reminders for CRM tasks that have a reminder_at in the past and reminder_sent = false';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $tasks = CrmTask::needsReminder()
            ->with(['contact.subscriber', 'owner', 'user'])
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No task reminders need to be sent.');
            return Command::SUCCESS;
        }

        $this->info("Found {$tasks->count()} task(s) with pending reminders.");

        $sent = 0;
        $errors = 0;

        foreach ($tasks as $task) {
            try {
                $taskTitle = $task->title;
                $ownerName = $task->owner?->name ?? 'Unknown';
                $contactName = $task->contact?->full_name ?? 'Brak kontaktu';
                $recipient = $task->owner ?? $task->user;

                if ($dryRun) {
                    $this->line("  [DRY RUN] Would send reminder: {$taskTitle} to {$ownerName}");
                    $this->line("            - In-app: YES");
                    $this->line("            - Email: " . ($this->shouldSendEmail($recipient) ? 'YES' : 'NO'));
                    continue;
                }

                $this->line("  Sending reminder: {$taskTitle} to {$ownerName}");

                // Create in-app notification
                Notification::create([
                    'user_id' => $task->owner_id ?? $task->user_id,
                    'title' => 'Przypomnienie o zadaniu',
                    'message' => "Masz zaplanowane zadanie: {$task->title}" .
                        ($task->contact ? " ({$contactName})" : ''),
                    'type' => 'task_reminder',
                    'data' => [
                        'task_id' => $task->id,
                        'contact_id' => $task->crm_contact_id,
                        'deal_id' => $task->crm_deal_id,
                    ],
                    'link' => '/crm/tasks',
                ]);

                // Send email notification if enabled in user preferences
                if ($recipient && $this->shouldSendEmail($recipient)) {
                    try {
                        Mail::to($recipient->email)->send(new TaskReminderMail($task));
                        $this->line("    ✓ Email sent to {$recipient->email}");
                    } catch (\Exception $e) {
                        Log::warning('Failed to send task reminder email', [
                            'task_id' => $task->id,
                            'email' => $recipient->email,
                            'error' => $e->getMessage(),
                        ]);
                        $this->warn("    ⚠ Email failed: " . $e->getMessage());
                    }
                }

                // Mark reminder as sent
                $task->markReminderSent();

                Log::info('Task reminder sent', [
                    'task_id' => $task->id,
                    'user_id' => $task->owner_id ?? $task->user_id,
                ]);

                $sent++;

            } catch (\Exception $e) {
                $errors++;
                $this->error("  Error sending reminder for task #{$task->id}: " . $e->getMessage());
                Log::error('Task reminder error', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$dryRun) {
            $this->newLine();
            $this->info("Sent: {$sent} | Errors: {$errors}");
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Check if email notifications should be sent to a user.
     */
    protected function shouldSendEmail($user): bool
    {
        if (!$user || !$user->email) {
            return false;
        }

        // Check user notification preferences
        $preferences = $user->notification_preferences ?? [];

        // Default to true if no preference is set (opt-out model)
        return $preferences['task_reminder_email'] ?? true;
    }
}

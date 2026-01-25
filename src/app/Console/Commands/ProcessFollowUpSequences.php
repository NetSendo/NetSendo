<?php

namespace App\Console\Commands;

use App\Models\CrmFollowUpEnrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessFollowUpSequences extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crm:process-follow-ups {--dry-run : Show what would be processed without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Process follow-up sequence enrollments and execute pending steps';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $enrollments = CrmFollowUpEnrollment::needsProcessing()
            ->with(['sequence', 'currentStep', 'contact'])
            ->get();

        if ($enrollments->isEmpty()) {
            $this->info('No enrollments need processing.');
            return Command::SUCCESS;
        }

        $this->info("Found {$enrollments->count()} enrollment(s) to process.");

        $processed = 0;
        $errors = 0;

        foreach ($enrollments as $enrollment) {
            try {
                $stepName = $enrollment->currentStep?->task_title ?? 'Unknown step';
                $contactName = $enrollment->contact?->full_name ?? 'Unknown contact';
                $sequenceName = $enrollment->sequence?->name ?? 'Unknown sequence';

                if ($dryRun) {
                    $this->line("  [DRY RUN] Would process: {$sequenceName} / {$stepName} for {$contactName}");
                    continue;
                }

                $this->line("  Processing: {$sequenceName} / {$stepName} for {$contactName}");

                $task = $enrollment->processCurrentStep();

                if ($task) {
                    $this->info("    → Created task: {$task->title}");
                    Log::info('Follow-up task created', [
                        'enrollment_id' => $enrollment->id,
                        'task_id' => $task->id,
                        'contact_id' => $enrollment->crm_contact_id,
                    ]);
                }

                if ($enrollment->fresh()->status === 'completed') {
                    $this->info("    → Sequence completed for {$contactName}");
                }

                $processed++;

            } catch (\Exception $e) {
                $errors++;
                $this->error("  Error processing enrollment #{$enrollment->id}: " . $e->getMessage());
                Log::error('Follow-up processing error', [
                    'enrollment_id' => $enrollment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$dryRun) {
            $this->newLine();
            $this->info("Processed: {$processed} | Errors: {$errors}");
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

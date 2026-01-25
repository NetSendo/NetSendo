<?php

namespace App\Console\Commands;

use App\Services\LeadScoringService;
use Illuminate\Console\Command;

class ProcessScoreDecay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:process-score-decay
                            {--dry-run : Show what would be processed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process score decay for inactive CRM contacts';

    /**
     * Execute the console command.
     */
    public function handle(LeadScoringService $scoringService): int
    {
        $this->info('Processing lead score decay...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - no changes will be made');
            // In dry run mode, we would just count affected contacts
            $this->info('Use without --dry-run to apply changes.');
            return Command::SUCCESS;
        }

        $processed = $scoringService->processDecay();

        $this->info("Processed score decay for {$processed} contacts.");

        return Command::SUCCESS;
    }
}

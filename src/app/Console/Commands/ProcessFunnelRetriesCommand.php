<?php

namespace App\Console\Commands;

use App\Services\Funnels\FunnelRetryService;
use Illuminate\Console\Command;

class ProcessFunnelRetriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'funnels:process-retries';

    /**
     * The console command description.
     */
    protected $description = 'Process funnel enrollments waiting for conditions and send retry reminders';

    /**
     * Execute the console command.
     */
    public function handle(FunnelRetryService $retryService): int
    {
        $this->info('Processing funnel retries...');

        $processed = $retryService->processWaitingEnrollments();

        $this->info("Processed {$processed} enrollments.");

        return Command::SUCCESS;
    }
}

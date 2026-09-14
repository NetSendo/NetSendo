<?php

namespace App\Console\Commands;

use App\Services\Funnels\FunnelExecutionService;
use App\Services\Funnels\FunnelRetryService;
use Illuminate\Console\Command;

class ProcessFunnelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'funnels:process';

    /**
     * The former name, from when the command only handled condition waits.
     */
    protected $aliases = ['funnels:process-retries'];

    /**
     * The console command description.
     */
    protected $description = 'Resume funnel enrollments whose delay has passed and process those waiting for a condition (reminders, retry exhaustion)';

    /**
     * Execute the console command.
     */
    public function handle(FunnelExecutionService $executionService, FunnelRetryService $retryService): int
    {
        $resumed = $executionService->processReadyEnrollments();
        $this->info("Resumed {$resumed} enrollments after a delay.");

        $processed = $retryService->processWaitingEnrollments();
        $this->info("Processed {$processed} enrollments waiting for a condition.");

        return Command::SUCCESS;
    }
}

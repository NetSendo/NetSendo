<?php

namespace App\Jobs;

use App\Models\AbTest;
use App\Services\AbTestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAbTestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Execute the job.
     */
    public function handle(AbTestService $abTestService): void
    {
        Log::info('ProcessAbTestsJob: Starting A/B test evaluation');

        try {
            $abTestService->processReadyTests();

            Log::info('ProcessAbTestsJob: Completed successfully');
        } catch (\Exception $e) {
            Log::error('ProcessAbTestsJob: Error processing tests', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAbTestsJob: Job failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Nmi\IpWarmingService;
use Illuminate\Console\Command;

class NmiProcessWarmingCommand extends Command
{
    protected $signature = 'nmi:process-warming';

    protected $description = 'Process daily IP warming updates for all warming IPs';

    public function __construct(
        private IpWarmingService $warmingService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('nmi.features.enabled')) {
            $this->info('NMI is disabled. Skipping warming processing.');
            return self::SUCCESS;
        }

        $this->info('Processing IP warming updates...');

        $processed = $this->warmingService->processDailyWarmingUpdates();

        $this->info("Processed {$processed} IPs.");

        return self::SUCCESS;
    }
}

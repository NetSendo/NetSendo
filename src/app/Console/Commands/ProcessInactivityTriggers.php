<?php

namespace App\Console\Commands;

use App\Services\Automation\InactivityTriggerService;
use Illuminate\Console\Command;

class ProcessInactivityTriggers extends Command
{
    protected $signature = 'automations:process-inactivity
                            {--limit= : Subscribers raised per rule in this run (default 1000)}';

    protected $description = 'Run "subscriber_inactive" automations for subscribers who have not opened or clicked for the rule\'s days';

    public function handle(InactivityTriggerService $service): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $count = $service->processAll($limit);

        $this->info("Inactive subscribers raised: {$count}");

        return self::SUCCESS;
    }
}

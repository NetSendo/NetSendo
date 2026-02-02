<?php

namespace App\Console\Commands;

use App\Services\Nmi\BlacklistMonitorService;
use Illuminate\Console\Command;

class NmiCheckBlacklistsCommand extends Command
{
    protected $signature = 'nmi:check-blacklists';

    protected $description = 'Check all active IPs against DNS blacklists';

    public function __construct(
        private BlacklistMonitorService $blacklistService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('nmi.features.enabled')) {
            $this->info('NMI is disabled. Skipping blacklist checks.');
            return self::SUCCESS;
        }

        $this->info('Checking IPs against blacklists...');

        $checked = $this->blacklistService->processScheduledChecks();

        $this->info("Checked {$checked} IPs.");

        return self::SUCCESS;
    }
}

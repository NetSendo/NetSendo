<?php

namespace App\Console\Commands;

use App\Services\Nmi\MtaAuthFile;
use Illuminate\Console\Command;

class NmiSyncMtaAuthCommand extends Command
{
    protected $signature = 'nmi:sync-mta-auth';

    protected $description = 'Write the logins NetSendo MTA (Haraka) accepts: one per domain with NMI switched on';

    public function handle(MtaAuthFile $file): int
    {
        if (!config('nmi.features.enabled')) {
            $this->info('NMI is disabled. Skipping the MTA logins.');
            return self::SUCCESS;
        }

        if (!$file->write()) {
            $this->error("Could not write {$file->path()}. In Docker: docker compose exec -u root app chown dev:www-data " . dirname($file->path()));
            return self::FAILURE;
        }

        $this->info(count($file->entries()) . " MTA login(s) in {$file->path()}.");

        return self::SUCCESS;
    }
}

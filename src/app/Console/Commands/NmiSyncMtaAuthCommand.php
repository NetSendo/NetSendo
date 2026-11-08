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
        if (!$file->sync()) {
            $this->error("Could not write {$file->path()}. In Docker: docker compose exec -u root app chown dev:www-data " . dirname($file->path()));
            return self::FAILURE;
        }

        $this->info(\App\Services\Nmi\NmiSwitch::enabled()
            ? count($file->entries()) . " MTA login(s) in {$file->path()}."
            : 'NetSendo MTA is switched off: no MTA logins.');

        return self::SUCCESS;
    }
}

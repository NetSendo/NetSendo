<?php

namespace App\Console\Commands;

use App\Services\Nmi\MtaCredentialService;
use Illuminate\Console\Command;

class NmiSyncMtaAuthCommand extends Command
{
    protected $signature = 'nmi:sync-mta-auth';

    protected $description = 'Write the NetSendo MTA login file from the NMI logins of the sending domains';

    /**
     * The file lives on a volume shared with the MTA container. Saving an NMI mailbox
     * writes it too; this command restores it after the volume was recreated or the file
     * was removed (scheduled, see routes/console.php).
     */
    public function handle(MtaCredentialService $credentials): int
    {
        if (! config('nmi.features.enabled')) {
            $this->info('NMI is switched off (NMI_DISABLED=true): nothing to write.');

            return self::SUCCESS;
        }

        try {
            $count = $credentials->sync();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$count} NMI login(s) in {$credentials->authFile()}");

        return self::SUCCESS;
    }
}

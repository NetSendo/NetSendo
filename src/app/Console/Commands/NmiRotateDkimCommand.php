<?php

namespace App\Console\Commands;

use App\Models\DedicatedIpAddress;
use App\Services\Nmi\DkimKeyManager;
use Illuminate\Console\Command;

class NmiRotateDkimCommand extends Command
{
    protected $signature = 'nmi:rotate-dkim
                            {--force : Force rotation for all IPs}
                            {--months=6 : Rotation threshold in months}';

    protected $description = 'Rotate DKIM keys that are older than the specified threshold';

    public function __construct(
        private DkimKeyManager $dkimManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('nmi.features.enabled')) {
            $this->info('NMI is disabled. Skipping DKIM rotation.');
            return self::SUCCESS;
        }

        $months = (int) $this->option('months');
        $force = $this->option('force');

        if ($force) {
            $ips = DedicatedIpAddress::whereNotNull('domain_configuration_id')
                ->active()
                ->get();
            $this->warn("Force rotating DKIM keys for all {$ips->count()} IPs...");
        } else {
            $ips = $this->dkimManager->getIpsNeedingRotation($months);
            $this->info("Found {$ips->count()} IPs needing DKIM key rotation...");
        }

        $rotated = 0;
        foreach ($ips as $ip) {
            try {
                $this->dkimManager->rotateKeysForIp($ip);
                $rotated++;
                $this->line("  ✓ Rotated keys for IP {$ip->ip_address}");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to rotate keys for IP {$ip->ip_address}: {$e->getMessage()}");
            }
        }

        $this->info("Rotated {$rotated} DKIM keys.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\UpgradeDmarcPolicyJob;
use App\Models\DomainConfiguration;
use Illuminate\Console\Command;

class UpgradeDmarcCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deliverability:upgrade-dmarc {--domain= : Check specific domain ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze domains for potential DMARC policy upgrades';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $domainId = $this->option('domain');

        if ($domainId) {
            $domain = DomainConfiguration::find($domainId);
            if (!$domain) {
                $this->error("Domain with ID {$domainId} not found.");
                return Command::FAILURE;
            }

            $this->info("Analyzing DMARC upgrade for: {$domain->domain}");
            UpgradeDmarcPolicyJob::dispatch($domain);
        } else {
            // Get domains that are verified and have DMARC configured
            $domains = DomainConfiguration::query()
                ->where('cname_verified', true)
                ->where('dmarc_status', 'pass')
                ->whereIn('dmarc_policy', ['none', 'quarantine'])
                ->get();

            $this->info("Found {$domains->count()} domains eligible for DMARC upgrade analysis.");

            foreach ($domains as $domain) {
                $this->line("Analyzing: {$domain->domain} (current policy: {$domain->dmarc_policy})");
                UpgradeDmarcPolicyJob::dispatch($domain);
            }
        }

        $this->info('DMARC upgrade analysis jobs dispatched successfully.');
        return Command::SUCCESS;
    }
}

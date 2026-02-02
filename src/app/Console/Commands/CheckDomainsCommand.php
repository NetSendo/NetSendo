<?php

namespace App\Console\Commands;

use App\Jobs\CheckDomainDnsJob;
use App\Models\DomainConfiguration;
use Illuminate\Console\Command;

class CheckDomainsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deliverability:check-domains {--domain= : Check specific domain ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check DNS configuration for all verified domains';

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

            $this->info("Dispatching DNS check for: {$domain->domain}");
            CheckDomainDnsJob::dispatch($domain);
        } else {
            $domains = DomainConfiguration::query()
                ->where('cname_verified', true)
                ->where(function ($query) {
                    $query->whereNull('last_check_at')
                        ->orWhere('last_check_at', '<', now()->subHours(6));
                })
                ->get();

            $this->info("Found {$domains->count()} domains to check.");

            foreach ($domains as $domain) {
                $this->line("Dispatching check for: {$domain->domain}");
                CheckDomainDnsJob::dispatch($domain);
            }
        }

        $this->info('DNS check jobs dispatched successfully.');
        return Command::SUCCESS;
    }
}

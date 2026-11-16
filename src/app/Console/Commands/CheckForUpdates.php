<?php

namespace App\Console\Commands;

use App\Services\UpdateCheckService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckForUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netsendo:check-updates {--force : Ask GitHub now instead of using the cached release list}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for available NetSendo updates from GitHub';

    /**
     * Scheduled hourly: it refreshes the cached release list when it has expired, so the
     * sidebar rarely has to ask GitHub itself. GitHub being out of reach (no outbound
     * connection, the API rate limit of a shared IP) is not a failure of this installation:
     * the command says so and exits 0, instead of an hourly "failed with exit code 1"
     * error in the log.
     */
    public function handle(UpdateCheckService $updates): int
    {
        $currentVersion = (string) config('netsendo.version');

        if (empty(config('netsendo.github_repo'))) {
            $this->info('Version checking is disabled (no GitHub repo configured).');
            return self::SUCCESS;
        }

        $this->info("Checking for updates... (current: v{$currentVersion})");

        $status = $updates->status(fresh: (bool) $this->option('force'));

        if (isset($status['error'])) {
            $this->warn('Could not check for updates: ' . $updates->lastError());
            if ($updates->lastRateLimited()) {
                $this->line('   The next check waits until GitHub resets the limit.');
            }

            return self::SUCCESS;
        }

        $this->displayResult($status, $currentVersion);

        if ($status['updates_available']) {
            Log::info('NetSendo updates available', [
                'current' => $currentVersion,
                'latest' => $status['latest_version'],
                'count' => $status['update_count'],
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Display the result in console.
     */
    private function displayResult(array $result, string $currentVersion): void
    {
        if (! ($result['updates_available'] ?? false)) {
            $this->info("✓ Up to date (v{$currentVersion})");
            return;
        }

        $count = $result['update_count'] ?? 0;
        $latest = $result['latest_version'] ?? $currentVersion;

        $this->newLine();
        $this->warn("⚠️  {$count} update(s) available!");
        $this->info("   Current: v{$currentVersion}");
        $this->info("   Latest:  v{$latest}");

        if (! empty($result['new_versions'])) {
            $this->newLine();
            $this->line('   Available versions:');
            foreach (array_slice($result['new_versions'], 0, 3) as $version) {
                $this->line("   - v{$version['version']} ({$version['name']})");
            }
        }

        $this->newLine();
        $this->info('   Download: ' . config('netsendo.github_releases_url'));
    }
}

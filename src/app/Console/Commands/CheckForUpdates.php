<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckForUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netsendo:check-updates {--force : Bypass cache and force refresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for available NetSendo updates from GitHub';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $currentVersion = config('netsendo.version');
        $githubRepo = config('netsendo.github_repo');

        if (empty($githubRepo)) {
            $this->info('Version checking is disabled (no GitHub repo configured).');
            return self::SUCCESS;
        }

        // Always fetch fresh data from GitHub (no cache)
        // Cache was causing delays in update detection and stale data after updates

        $this->info("Checking for updates... (current: v{$currentVersion})");

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'NetSendo-Version-Check',
                ])
                ->get("https://api.github.com/repos/{$githubRepo}/releases");

            if (!$response->successful()) {
                $this->error('Failed to connect to GitHub API.');
                Log::warning('NetSendo update check failed: GitHub API error', [
                    'status' => $response->status(),
                ]);
                return self::FAILURE;
            }

            $releases = $response->json();
            
            if (!is_array($releases) || empty($releases)) {
                $this->info('No releases found on GitHub.');
                return self::SUCCESS;
            }

            // Process releases
            $newVersions = [];
            foreach ($releases as $release) {
                if ($release['prerelease'] ?? false) {
                    continue;
                }

                $tagName = $release['tag_name'] ?? '';
                $version = ltrim($tagName, 'v');

                if ($this->isNewerVersion($version, $currentVersion)) {
                    $newVersions[] = [
                        'version' => $version,
                        'tag' => $tagName,
                        'name' => $release['name'] ?? $tagName,
                        'published_at' => $release['published_at'] ?? null,
                        'url' => $release['html_url'] ?? '',
                    ];
                }
            }

            // Sort by version descending
            usort($newVersions, fn($a, $b) => version_compare($b['version'], $a['version']));

            $latestVersion = !empty($newVersions) ? $newVersions[0]['version'] : $currentVersion;

            $result = [
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'updates_available' => !empty($newVersions),
                'update_count' => count($newVersions),
                'new_versions' => array_slice($newVersions, 0, 5),
                'checked_at' => now()->toIso8601String(),
            ];

            $this->displayResult($result, $currentVersion, false);

            // Log if updates are available
            if (!empty($newVersions)) {
                Log::info('NetSendo updates available', [
                    'current' => $currentVersion,
                    'latest' => $latestVersion,
                    'count' => count($newVersions),
                ]);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error checking for updates: ' . $e->getMessage());
            Log::error('NetSendo update check error', [
                'error' => $e->getMessage(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Display the result in console.
     */
    private function displayResult(array $result, string $currentVersion, bool $fromCache): void
    {
        $source = $fromCache ? ' (cached)' : '';

        if ($result['updates_available'] ?? false) {
            $count = $result['update_count'] ?? 0;
            $latest = $result['latest_version'] ?? $currentVersion;
            
            $this->newLine();
            $this->warn("⚠️  {$count} update(s) available!{$source}");
            $this->info("   Current: v{$currentVersion}");
            $this->info("   Latest:  v{$latest}");
            
            if (!empty($result['new_versions'])) {
                $this->newLine();
                $this->line('   Available versions:');
                foreach (array_slice($result['new_versions'], 0, 3) as $version) {
                    $this->line("   - v{$version['version']} ({$version['name']})");
                }
            }
            
            $this->newLine();
            $this->info('   Download: ' . config('netsendo.github_releases_url'));
        } else {
            $this->info("✓ Up to date (v{$currentVersion}){$source}");
        }
    }

    /**
     * Compare versions using semantic versioning.
     */
    private function isNewerVersion(string $version, string $currentVersion): bool
    {
        $version = preg_replace('/[^0-9.]/', '', $version);
        $currentVersion = preg_replace('/[^0-9.]/', '', $currentVersion);

        if (empty($version) || empty($currentVersion)) {
            return false;
        }

        return version_compare($version, $currentVersion, '>');
    }
}

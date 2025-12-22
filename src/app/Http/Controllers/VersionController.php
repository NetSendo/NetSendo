<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class VersionController extends Controller
{
    /**
     * Check for available updates from GitHub.
     */
    public function check()
    {
        $currentVersion = config('netsendo.version');
        $githubRepo = config('netsendo.github_repo');
        
        if (empty($githubRepo)) {
            return response()->json([
                'current_version' => $currentVersion,
                'updates_available' => false,
                'message' => 'Sprawdzanie aktualizacji jest wyłączone.',
            ]);
        }

        // Always fetch fresh data - no cache to avoid showing stale update status
        // This ensures users see correct status immediately after updating
        $result = $this->fetchUpdatesFromGitHub($currentVersion, $githubRepo);

        return response()->json($result);
    }

    /**
     * Force refresh version check (same as check - no cache).
     */
    public function refresh()
    {
        // Same as check() - both always fetch fresh data now
        return $this->check();
    }

    /**
     * Get current version info.
     */
    public function current()
    {
        return response()->json([
            'version' => config('netsendo.version'),
            'app_name' => config('app.name'),
        ]);
    }

    /**
     * Fetch updates from GitHub releases API.
     */
    private function fetchUpdatesFromGitHub(string $currentVersion, string $githubRepo): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'NetSendo-Version-Check',
                ])
                ->get("https://api.github.com/repos/{$githubRepo}/releases");

            if (!$response->successful()) {
                return [
                    'current_version' => $currentVersion,
                    'updates_available' => false,
                    'error' => 'Nie udało się połączyć z GitHub.',
                ];
            }

            $releases = $response->json();
            
            if (!is_array($releases) || empty($releases)) {
                return [
                    'current_version' => $currentVersion,
                    'updates_available' => false,
                    'latest_version' => $currentVersion,
                    'new_versions' => [],
                ];
            }

            // Filter and sort releases
            $newVersions = [];
            foreach ($releases as $release) {
                if (isset($release['prerelease']) && $release['prerelease']) {
                    continue; // Skip pre-releases
                }
                
                $tagName = $release['tag_name'] ?? '';
                $version = ltrim($tagName, 'v'); // Remove 'v' prefix if present
                
                if ($this->isNewerVersion($version, $currentVersion)) {
                    $newVersions[] = [
                        'version' => $version,
                        'tag' => $tagName,
                        'name' => $release['name'] ?? $tagName,
                        'published_at' => $release['published_at'] ?? null,
                        'body' => $this->truncateChangelog($release['body'] ?? ''),
                        'url' => $release['html_url'] ?? '',
                    ];
                }
            }

            // Sort by version descending (newest first)
            usort($newVersions, function ($a, $b) {
                return version_compare($b['version'], $a['version']);
            });

            $latestVersion = !empty($newVersions) ? $newVersions[0]['version'] : $currentVersion;

            return [
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'updates_available' => !empty($newVersions),
                'update_count' => count($newVersions),
                'new_versions' => array_slice($newVersions, 0, 5), // Limit to 5 latest
            ];

        } catch (\Exception $e) {
            return [
                'current_version' => $currentVersion,
                'updates_available' => false,
                'error' => 'Błąd podczas sprawdzania aktualizacji.',
            ];
        }
    }

    /**
     * Compare version strings (semantic versioning).
     */
    private function isNewerVersion(string $version, string $currentVersion): bool
    {
        // Clean versions
        $version = preg_replace('/[^0-9.]/', '', $version);
        $currentVersion = preg_replace('/[^0-9.]/', '', $currentVersion);
        
        if (empty($version) || empty($currentVersion)) {
            return false;
        }

        return version_compare($version, $currentVersion, '>');
    }

    /**
     * Truncate changelog to reasonable length.
     */
    private function truncateChangelog(string $body): string
    {
        $body = trim($body);
        
        if (strlen($body) <= 300) {
            return $body;
        }

        return substr($body, 0, 300) . '...';
    }

    /**
     * Get full changelog from GitHub releases for Updates page.
     */
    public function changelog()
    {
        $githubRepo = config('netsendo.github_repo');
        $currentVersion = config('netsendo.version');
        
        if (empty($githubRepo)) {
            return response()->json([
                'releases' => [],
                'current_version' => $currentVersion,
            ]);
        }

        // Cache for 1 hour
        $cacheKey = 'netsendo_changelog';
        
        $releases = Cache::remember($cacheKey, 3600, function () use ($githubRepo) {
            return $this->fetchAllReleases($githubRepo);
        });

        return response()->json([
            'releases' => $releases,
            'current_version' => $currentVersion,
        ]);
    }

    /**
     * Fetch all releases from GitHub for changelog.
     */
    private function fetchAllReleases(string $githubRepo): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'NetSendo-Version-Check',
                ])
                ->get("https://api.github.com/repos/{$githubRepo}/releases");

            if (!$response->successful()) {
                return [];
            }

            $releases = $response->json();
            
            if (!is_array($releases)) {
                return [];
            }

            $result = [];
            foreach ($releases as $release) {
                $tagName = $release['tag_name'] ?? '';
                $version = ltrim($tagName, 'v');
                
                $result[] = [
                    'version' => $version,
                    'tag' => $tagName,
                    'name' => $release['name'] ?? $tagName,
                    'published_at' => $release['published_at'] ?? null,
                    'body' => $release['body'] ?? '',
                    'url' => $release['html_url'] ?? '',
                    'prerelease' => $release['prerelease'] ?? false,
                ];
            }

            return $result;

        } catch (\Exception $e) {
            return [];
        }
    }
}


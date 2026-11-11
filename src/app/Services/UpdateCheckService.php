<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NetSendo releases from the GitHub API, for the update notice in the sidebar, the Updates
 * page and the scheduled `netsendo:check-updates`.
 *
 * GitHub allows 60 unauthenticated API requests an hour per IP address. Client instances
 * often share one host and one IP, and the panel used to ask GitHub on every page view plus
 * once an hour from the scheduler, so the limit ran out: the update notice failed and the
 * scheduler logged "netsendo:check-updates failed with exit code 1" every hour. The release
 * list is now cached, a failed request is not repeated for a while, and the cache lifetime
 * varies per fetch so that instances drift apart instead of asking GitHub in the same minute.
 *
 * The cache holds the release list, not the comparison with the running version, so the
 * status is right straight after an update.
 */
class UpdateCheckService
{
    public const CACHE_KEY = 'netsendo:github-releases';

    /** Remembers a failed request: [message, rate_limited] */
    public const FAILURE_KEY = 'netsendo:github-releases-failed';

    /** A successful list is kept 12 hours plus up to 6 hours, different on every fetch */
    public const TTL_SECONDS = 12 * 3600;
    public const TTL_JITTER_SECONDS = 6 * 3600;

    /** After a failure GitHub is not asked again for this long (rate limit: until its reset) */
    public const FAILURE_TTL_SECONDS = 3 * 3600;
    public const RATE_LIMIT_MAX_WAIT_SECONDS = 3600;

    private ?string $lastError = null;
    private bool $lastRateLimited = false;

    /**
     * All releases, newest first as GitHub lists them; null when GitHub could not be asked
     * (see lastError()).
     *
     * @return array<int, array{version: string, tag: string, name: string, published_at: ?string, body: string, url: string, prerelease: bool}>|null
     */
    public function releases(bool $fresh = false): ?array
    {
        $this->lastError = null;
        $this->lastRateLimited = false;

        $repo = config('netsendo.github_repo');
        if (empty($repo)) {
            return [];
        }

        if (! $fresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }

            $failure = Cache::get(self::FAILURE_KEY);
            if (is_array($failure)) {
                [$this->lastError, $this->lastRateLimited] = $failure;

                return null;
            }
        }

        return $this->fetch($repo);
    }

    /**
     * Releases newer than the running version (pre-releases skipped), newest first, with the
     * changelog shortened for the sidebar. Same shape the version endpoints always returned.
     */
    public function status(bool $fresh = false): array
    {
        $currentVersion = (string) config('netsendo.version');
        $releases = $this->releases($fresh);

        if ($releases === null) {
            return [
                'current_version' => $currentVersion,
                'updates_available' => false,
                'error' => __('settings.updates_check_failed'),
            ];
        }

        $newVersions = [];
        foreach ($releases as $release) {
            if ($release['prerelease'] || ! $this->isNewerVersion($release['version'], $currentVersion)) {
                continue;
            }
            $newVersions[] = [
                'version' => $release['version'],
                'tag' => $release['tag'],
                'name' => $release['name'],
                'published_at' => $release['published_at'],
                'body' => $this->truncateChangelog($release['body']),
                'url' => $release['url'],
            ];
        }

        usort($newVersions, fn ($a, $b) => version_compare($b['version'], $a['version']));

        return [
            'current_version' => $currentVersion,
            'latest_version' => $newVersions[0]['version'] ?? $currentVersion,
            'updates_available' => ! empty($newVersions),
            'update_count' => count($newVersions),
            'new_versions' => array_slice($newVersions, 0, 5),
        ];
    }

    /** Why the last releases() call returned null */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /** Whether the last releases() call hit GitHub's rate limit */
    public function lastRateLimited(): bool
    {
        return $this->lastRateLimited;
    }

    private function fetch(string $repo): ?array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'NetSendo-Version-Check',
                ])
                ->get("https://api.github.com/repos/{$repo}/releases");
        } catch (\Throwable $e) {
            return $this->fail('GitHub could not be reached: ' . $e->getMessage(), false, self::FAILURE_TTL_SECONDS);
        }

        if (! $response->successful()) {
            if ($this->isRateLimited($response)) {
                $reset = (int) $response->header('X-RateLimit-Reset');
                $wait = $reset > 0 ? $reset - Carbon::now()->getTimestamp() : self::RATE_LIMIT_MAX_WAIT_SECONDS;

                return $this->fail(
                    'GitHub API rate limit reached for this server\'s IP address (60 requests an hour without a token).',
                    true,
                    max(60, min(self::RATE_LIMIT_MAX_WAIT_SECONDS, $wait)),
                );
            }

            return $this->fail("GitHub API answered HTTP {$response->status()}.", false, self::FAILURE_TTL_SECONDS);
        }

        $data = $response->json();
        if (! is_array($data)) {
            return $this->fail('GitHub API sent an unexpected answer.', false, self::FAILURE_TTL_SECONDS);
        }

        $releases = [];
        foreach ($data as $release) {
            if (! is_array($release)) {
                continue;
            }
            $tag = (string) ($release['tag_name'] ?? '');
            $releases[] = [
                'version' => ltrim($tag, 'v'),
                'tag' => $tag,
                'name' => (string) ($release['name'] ?? '') ?: $tag,
                'published_at' => $release['published_at'] ?? null,
                'body' => (string) ($release['body'] ?? ''),
                'url' => (string) ($release['html_url'] ?? ''),
                'prerelease' => (bool) ($release['prerelease'] ?? false),
            ];
        }

        Cache::put(self::CACHE_KEY, $releases, self::TTL_SECONDS + random_int(0, self::TTL_JITTER_SECONDS));
        Cache::forget(self::FAILURE_KEY);

        return $releases;
    }

    private function fail(string $message, bool $rateLimited, int $retryAfterSeconds): ?array
    {
        $this->lastError = $message;
        $this->lastRateLimited = $rateLimited;

        Cache::put(self::FAILURE_KEY, [$message, $rateLimited], $retryAfterSeconds);
        Log::warning('NetSendo update check: ' . $message);

        return null;
    }

    /** GitHub answers 403 (or 429) with X-RateLimit-Remaining: 0 when the limit is used up */
    private function isRateLimited(Response $response): bool
    {
        return in_array($response->status(), [403, 429], true)
            && $response->header('X-RateLimit-Remaining') === '0';
    }

    private function isNewerVersion(string $version, string $currentVersion): bool
    {
        $version = preg_replace('/[^0-9.]/', '', $version);
        $currentVersion = preg_replace('/[^0-9.]/', '', $currentVersion);

        if (empty($version) || empty($currentVersion)) {
            return false;
        }

        return version_compare($version, $currentVersion, '>');
    }

    private function truncateChangelog(string $body): string
    {
        $body = trim($body);

        return strlen($body) <= 300 ? $body : substr($body, 0, 300) . '...';
    }
}

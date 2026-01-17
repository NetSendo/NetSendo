<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpStatus extends Model
{
    protected $table = 'mcp_status';

    protected $fillable = [
        'status',
        'message',
        'version',
        'api_url',
        'tested_at',
    ];

    protected $casts = [
        'tested_at' => 'datetime',
    ];

    /**
     * Get the latest MCP status record
     */
    public static function getLatestStatus(): ?self
    {
        return static::orderByDesc('tested_at')->first();
    }

    /**
     * Record a successful connection test
     */
    public static function recordSuccess(string $message, ?string $version = null, ?string $apiUrl = null): self
    {
        return static::create([
            'status' => 'success',
            'message' => $message,
            'version' => $version,
            'api_url' => $apiUrl,
            'tested_at' => now(),
        ]);
    }

    /**
     * Record a failed connection test
     */
    public static function recordFailure(string $message, ?string $apiUrl = null): self
    {
        return static::create([
            'status' => 'failed',
            'message' => $message,
            'api_url' => $apiUrl,
            'tested_at' => now(),
        ]);
    }

    /**
     * Check if MCP was tested recently (within last 24 hours)
     */
    public static function isRecentlyTested(): bool
    {
        $latest = static::getLatestStatus();

        if (!$latest || !$latest->tested_at) {
            return false;
        }

        return $latest->tested_at->isAfter(now()->subDay());
    }

    /**
     * Get formatted status for API response
     */
    public function toStatusArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'version' => $this->version,
            'api_url' => $this->api_url,
            'tested_at' => $this->tested_at?->toIso8601String(),
            'tested_at_human' => $this->tested_at?->diffForHumans(),
        ];
    }

    /**
     * Clean up old status records (keep last 100)
     */
    public static function cleanup(int $keepCount = 100): int
    {
        $total = static::count();

        if ($total <= $keepCount) {
            return 0;
        }

        $toDelete = $total - $keepCount;

        return static::orderBy('tested_at')
            ->limit($toDelete)
            ->delete();
    }
}

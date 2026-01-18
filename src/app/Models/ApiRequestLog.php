<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    protected $fillable = [
        'user_id',
        'api_key_id',
        'method',
        'endpoint',
        'request_body',
        'response_status',
        'response_body',
        'ip_address',
        'user_agent',
        'duration_ms',
    ];

    protected $casts = [
        'request_body' => 'array',
        'response_body' => 'array',
    ];

    /**
     * Get the user that made the request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the API key used for the request.
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by endpoint.
     */
    public function scopeForEndpoint($query, string $endpoint)
    {
        return $query->where('endpoint', 'like', "%{$endpoint}%");
    }

    /**
     * Scope to filter by method.
     */
    public function scopeForMethod($query, string $method)
    {
        return $query->where('method', strtoupper($method));
    }

    /**
     * Scope to filter errors (non-2xx responses).
     */
    public function scopeErrors($query)
    {
        return $query->where('response_status', '>=', 400);
    }

    /**
     * Check if this was an error response.
     */
    public function isError(): bool
    {
        return $this->response_status >= 400;
    }

    /**
     * Clean up old logs older than given days.
     */
    public static function cleanupOldLogs(int $days = 30): int
    {
        return static::where('created_at', '<', now()->subDays($days))->delete();
    }
}

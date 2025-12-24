<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Webhook extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'last_triggered_at',
        'failure_count',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    /**
     * Available webhook events
     */
    public const EVENTS = [
        // Subscriber events
        'subscriber.created',
        'subscriber.updated',
        'subscriber.deleted',
        'subscriber.subscribed',
        'subscriber.unsubscribed',
        'subscriber.bounced',
        'subscriber.tag_added',
        'subscriber.tag_removed',
        // SMS events
        'sms.queued',
        'sms.sent',
        'sms.failed',
    ];

    /**
     * Generate a new webhook with a random secret
     */
    public static function createWithSecret(array $attributes): self
    {
        $attributes['secret'] = Str::random(64);
        return static::create($attributes);
    }

    /**
     * Get the user that owns the webhook
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if webhook is subscribed to a specific event
     */
    public function isSubscribedTo(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    /**
     * Generate HMAC signature for payload
     */
    public function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }

    /**
     * Mark webhook as triggered
     */
    public function markTriggered(): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'failure_count' => 0,
        ]);
    }

    /**
     * Increment failure count
     */
    public function incrementFailure(): void
    {
        $this->increment('failure_count');

        // Deactivate webhook after 10 consecutive failures
        if ($this->failure_count >= 10) {
            $this->update(['is_active' => false]);
        }
    }

    /**
     * Scope to get active webhooks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get webhooks for a specific event
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->whereJsonContains('events', $event);
    }
}

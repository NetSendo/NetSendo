<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelAbEnrollment extends Model
{
    use HasFactory;

    protected $table = 'funnel_ab_enrollments';

    protected $fillable = [
        'ab_test_id',
        'variant_id',
        'funnel_subscriber_id',
        'subscriber_id',
        'converted',
        'converted_at',
        'conversion_value',
        'events',
    ];

    protected $casts = [
        'converted' => 'boolean',
        'converted_at' => 'datetime',
        'conversion_value' => 'decimal:2',
        'events' => 'array',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function abTest(): BelongsTo
    {
        return $this->belongsTo(FunnelAbTest::class, 'ab_test_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(FunnelAbVariant::class, 'variant_id');
    }

    public function funnelSubscriber(): BelongsTo
    {
        return $this->belongsTo(FunnelSubscriber::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeConverted($query)
    {
        return $query->where('converted', true);
    }

    public function scopeForVariant($query, int $variantId)
    {
        return $query->where('variant_id', $variantId);
    }

    // =====================================
    // Actions
    // =====================================

    /**
     * Mark this enrollment as converted.
     */
    public function markConverted(float $value = 0): self
    {
        if ($this->converted) {
            return $this; // Already converted
        }

        $this->converted = true;
        $this->converted_at = now();
        $this->conversion_value = $value;
        $this->save();

        // Increment variant counter
        $this->variant->recordConversion($value);

        return $this;
    }

    /**
     * Record an event (open, click, etc.)
     */
    public function recordEvent(string $type, array $data = []): self
    {
        $events = $this->events ?? [];
        $events[] = [
            'type' => $type,
            'at' => now()->toIso8601String(),
            'data' => $data,
        ];
        $this->events = $events;
        $this->save();

        // Update variant counters
        match ($type) {
            'open' => $this->variant->recordOpen(),
            'click' => $this->variant->recordClick(),
            default => null,
        };

        return $this;
    }

    /**
     * Get the count of specific event type.
     */
    public function getEventCount(string $type): int
    {
        if (!$this->events) return 0;
        return collect($this->events)->where('type', $type)->count();
    }
}

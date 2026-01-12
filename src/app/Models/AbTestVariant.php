<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbTestVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'ab_test_id',
        'variant_letter',
        'subject',
        'preheader',
        'content',
        'from_name',
        'from_email',
        'scheduled_send_time',
        'weight',
        'is_control',
        'is_ai_generated',
        'metadata',
    ];

    protected $casts = [
        'weight' => 'integer',
        'is_control' => 'boolean',
        'is_ai_generated' => 'boolean',
        'metadata' => 'array',
        'scheduled_send_time' => 'datetime',
    ];

    // Valid variant letters
    public const VARIANT_LETTERS = ['A', 'B', 'C', 'D', 'E'];

    /**
     * Get the A/B test this variant belongs to.
     */
    public function abTest(): BelongsTo
    {
        return $this->belongsTo(AbTest::class);
    }

    /**
     * Get all email opens for this variant.
     */
    public function opens(): HasMany
    {
        return $this->hasMany(EmailOpen::class, 'ab_test_variant_id');
    }

    /**
     * Get all email clicks for this variant.
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(EmailClick::class, 'ab_test_variant_id');
    }

    /**
     * Get all queue entries for this variant.
     */
    public function queueEntries(): HasMany
    {
        return $this->hasMany(MessageQueueEntry::class, 'ab_test_variant_id');
    }

    /**
     * Get the number of emails sent with this variant.
     */
    public function getSentCount(): int
    {
        return $this->queueEntries()->where('status', 'sent')->count();
    }

    /**
     * Get the total number of opens.
     */
    public function getOpenCount(): int
    {
        return $this->opens()->count();
    }

    /**
     * Get the number of unique opens (one per subscriber).
     */
    public function getUniqueOpenCount(): int
    {
        return $this->opens()->distinct('subscriber_id')->count('subscriber_id');
    }

    /**
     * Get the total number of clicks.
     */
    public function getClickCount(): int
    {
        return $this->clicks()->count();
    }

    /**
     * Get the number of unique clicks (one per subscriber).
     */
    public function getUniqueClickCount(): int
    {
        return $this->clicks()->distinct('subscriber_id')->count('subscriber_id');
    }

    /**
     * Calculate open rate as percentage.
     */
    public function getOpenRate(): float
    {
        $sent = $this->getSentCount();
        if ($sent === 0) {
            return 0.0;
        }

        return round(($this->getUniqueOpenCount() / $sent) * 100, 2);
    }

    /**
     * Calculate click rate as percentage of sent.
     */
    public function getClickRate(): float
    {
        $sent = $this->getSentCount();
        if ($sent === 0) {
            return 0.0;
        }

        return round(($this->getUniqueClickCount() / $sent) * 100, 2);
    }

    /**
     * Calculate click-to-open rate (CTOR) as percentage.
     */
    public function getClickToOpenRate(): float
    {
        $uniqueOpens = $this->getUniqueOpenCount();
        if ($uniqueOpens === 0) {
            return 0.0;
        }

        return round(($this->getUniqueClickCount() / $uniqueOpens) * 100, 2);
    }

    /**
     * Get conversion rate (placeholder for future implementation).
     */
    public function getConversionRate(): float
    {
        // TODO: Implement conversion tracking integration
        return 0.0;
    }

    /**
     * Get all metrics as an array.
     */
    public function getMetrics(): array
    {
        return [
            'sent' => $this->getSentCount(),
            'opens' => $this->getOpenCount(),
            'unique_opens' => $this->getUniqueOpenCount(),
            'clicks' => $this->getClickCount(),
            'unique_clicks' => $this->getUniqueClickCount(),
            'open_rate' => $this->getOpenRate(),
            'click_rate' => $this->getClickRate(),
            'click_to_open_rate' => $this->getClickToOpenRate(),
        ];
    }

    /**
     * Get the effective subject (variant or fallback to message).
     */
    public function getEffectiveSubject(): ?string
    {
        if ($this->subject) {
            return $this->subject;
        }

        return $this->abTest?->message?->subject;
    }

    /**
     * Get the effective content (variant or fallback to message).
     */
    public function getEffectiveContent(): ?string
    {
        if ($this->content) {
            return $this->content;
        }

        return $this->abTest?->message?->content;
    }

    /**
     * Get the effective preheader (variant or fallback to message).
     */
    public function getEffectivePreheader(): ?string
    {
        if ($this->preheader) {
            return $this->preheader;
        }

        return $this->abTest?->message?->preheader;
    }
}

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
     * Get the number of emails sent with this variant during the test period.
     * Excludes winner sends to remaining audience after test completion.
     *
     * @param bool $testPeriodOnly If true, only count sends during test period
     */
    public function getSentCount(bool $testPeriodOnly = true): int
    {
        $query = $this->queueEntries()->where('status', 'sent');

        // If test has ended, only count sends before test ended (test sample only)
        if ($testPeriodOnly && $this->abTest?->test_ended_at) {
            $query->where('sent_at', '<=', $this->abTest->test_ended_at);
        }

        return $query->count();
    }

    /**
     * Get the total number of emails sent with this variant (including winner rollout).
     */
    public function getTotalSentCount(): int
    {
        return $this->getSentCount(false);
    }

    /**
     * Get the total number of opens during test period.
     */
    public function getOpenCount(bool $testPeriodOnly = true): int
    {
        $query = $this->opens();

        if ($testPeriodOnly && $this->abTest?->test_ended_at) {
            $query->where('created_at', '<=', $this->abTest->test_ended_at);
        }

        return $query->count();
    }

    /**
     * Get the number of unique opens (one per subscriber) during test period.
     */
    public function getUniqueOpenCount(bool $testPeriodOnly = true): int
    {
        $query = $this->opens();

        if ($testPeriodOnly && $this->abTest?->test_ended_at) {
            $query->where('created_at', '<=', $this->abTest->test_ended_at);
        }

        return $query->distinct('subscriber_id')->count('subscriber_id');
    }

    /**
     * Get the total number of clicks during test period.
     */
    public function getClickCount(bool $testPeriodOnly = true): int
    {
        $query = $this->clicks();

        if ($testPeriodOnly && $this->abTest?->test_ended_at) {
            $query->where('created_at', '<=', $this->abTest->test_ended_at);
        }

        return $query->count();
    }

    /**
     * Get the number of unique clicks (one per subscriber) during test period.
     */
    public function getUniqueClickCount(bool $testPeriodOnly = true): int
    {
        $query = $this->clicks();

        if ($testPeriodOnly && $this->abTest?->test_ended_at) {
            $query->where('created_at', '<=', $this->abTest->test_ended_at);
        }

        return $query->distinct('subscriber_id')->count('subscriber_id');
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
     * Get all metrics as an array (test period only by default).
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
     * Get all metrics including the winner rollout to remaining audience.
     */
    public function getTotalMetrics(): array
    {
        $sent = $this->getSentCount(false);
        $uniqueOpens = $this->getUniqueOpenCount(false);
        $uniqueClicks = $this->getUniqueClickCount(false);

        return [
            'sent' => $sent,
            'opens' => $this->getOpenCount(false),
            'unique_opens' => $uniqueOpens,
            'clicks' => $this->getClickCount(false),
            'unique_clicks' => $uniqueClicks,
            'open_rate' => $sent > 0 ? round(($uniqueOpens / $sent) * 100, 2) : 0.0,
            'click_rate' => $sent > 0 ? round(($uniqueClicks / $sent) * 100, 2) : 0.0,
            'click_to_open_rate' => $uniqueOpens > 0 ? round(($uniqueClicks / $uniqueOpens) * 100, 2) : 0.0,
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

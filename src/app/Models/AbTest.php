<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'name',
        'status',
        'test_type',
        'winning_metric',
        'sample_percentage',
        'test_duration_hours',
        'auto_select_winner',
        'confidence_threshold',
        'winner_variant_id',
        'test_started_at',
        'test_ended_at',
        'winner_sent_at',
        'test_settings',
        'final_results',
    ];

    protected $casts = [
        'auto_select_winner' => 'boolean',
        'sample_percentage' => 'integer',
        'test_duration_hours' => 'integer',
        'confidence_threshold' => 'integer',
        'test_settings' => 'array',
        'final_results' => 'array',
        'test_started_at' => 'datetime',
        'test_ended_at' => 'datetime',
        'winner_sent_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    // Test type constants
    public const TYPE_SUBJECT = 'subject';
    public const TYPE_CONTENT = 'content';
    public const TYPE_SENDER = 'sender';
    public const TYPE_SEND_TIME = 'send_time';
    public const TYPE_FULL = 'full';

    // Winning metric constants
    public const METRIC_OPEN_RATE = 'open_rate';
    public const METRIC_CLICK_RATE = 'click_rate';
    public const METRIC_CONVERSION_RATE = 'conversion_rate';

    /**
     * Get the message this test belongs to.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user who created this test.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all variants for this test.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(AbTestVariant::class)->orderBy('variant_letter');
    }

    /**
     * Get the winning variant.
     */
    public function winnerVariant(): BelongsTo
    {
        return $this->belongsTo(AbTestVariant::class, 'winner_variant_id');
    }

    /**
     * Scope for running tests.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Scope for completed tests.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for tests belonging to a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for tests ready to evaluate (past duration, still running).
     */
    public function scopeReadyToEvaluate($query)
    {
        return $query->where('status', self::STATUS_RUNNING)
            ->whereNotNull('test_started_at')
            ->whereRaw('DATE_ADD(test_started_at, INTERVAL test_duration_hours HOUR) <= NOW()');
    }

    /**
     * Check if the test is still running.
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if the test has completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the test duration has elapsed.
     */
    public function hasDurationElapsed(): bool
    {
        if (!$this->test_started_at) {
            return false;
        }

        return $this->test_started_at->addHours($this->test_duration_hours)->isPast();
    }

    /**
     * Get the control variant (typically A).
     */
    public function getControlVariant(): ?AbTestVariant
    {
        return $this->variants()->where('is_control', true)->first()
            ?? $this->variants()->where('variant_letter', 'A')->first();
    }

    /**
     * Calculate results for all variants.
     */
    public function calculateResults(): array
    {
        $results = [];

        foreach ($this->variants as $variant) {
            $results[$variant->variant_letter] = [
                'variant_id' => $variant->id,
                'variant_letter' => $variant->variant_letter,
                'sent' => $variant->getSentCount(),
                'opens' => $variant->getOpenCount(),
                'unique_opens' => $variant->getUniqueOpenCount(),
                'clicks' => $variant->getClickCount(),
                'unique_clicks' => $variant->getUniqueClickCount(),
                'open_rate' => $variant->getOpenRate(),
                'click_rate' => $variant->getClickRate(),
                'click_to_open_rate' => $variant->getClickToOpenRate(),
            ];
        }

        return $results;
    }

    /**
     * Determine the winning variant based on the winning metric.
     */
    public function determineWinner(): ?AbTestVariant
    {
        $variants = $this->variants;
        $winner = null;
        $bestScore = -1;

        foreach ($variants as $variant) {
            $score = match ($this->winning_metric) {
                self::METRIC_OPEN_RATE => $variant->getOpenRate(),
                self::METRIC_CLICK_RATE => $variant->getClickRate(),
                self::METRIC_CONVERSION_RATE => $variant->getConversionRate(),
                default => $variant->getOpenRate(),
            };

            if ($score > $bestScore) {
                $bestScore = $score;
                $winner = $variant;
            }
        }

        return $winner;
    }
}

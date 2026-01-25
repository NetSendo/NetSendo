<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class LeadScoreHistory extends Model
{
    use HasFactory;

    /**
     * Disable timestamps - we only use created_at.
     */
    public $timestamps = false;

    protected $table = 'lead_score_history';

    protected $fillable = [
        'crm_contact_id',
        'lead_scoring_rule_id',
        'event_type',
        'points_change',
        'score_before',
        'score_after',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'points_change' => 'integer',
        'score_before' => 'integer',
        'score_after' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? now();
        });
    }

    /**
     * Get the CRM contact this history belongs to.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    /**
     * Get the scoring rule that triggered this change.
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(LeadScoringRule::class, 'lead_scoring_rule_id');
    }

    // ==================== SCOPES ====================

    /**
     * Scope to get recent history entries.
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get only positive changes.
     */
    public function scopePositive(Builder $query): Builder
    {
        return $query->where('points_change', '>', 0);
    }

    /**
     * Scope to get only negative changes (decay).
     */
    public function scopeNegative(Builder $query): Builder
    {
        return $query->where('points_change', '<', 0);
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeForEvent(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    // ==================== HELPERS ====================

    /**
     * Get a human-readable label for the event type.
     */
    public function getEventLabelAttribute(): string
    {
        return LeadScoringRule::EVENT_TYPES[$this->event_type] ?? $this->event_type;
    }

    /**
     * Get formatted points with sign.
     */
    public function getFormattedPointsAttribute(): string
    {
        $prefix = $this->points_change > 0 ? '+' : '';
        return $prefix . $this->points_change;
    }

    /**
     * Get the description text for this history entry.
     */
    public function getDescriptionAttribute(): string
    {
        $label = $this->event_label;
        $points = $this->formatted_points;

        if ($this->rule) {
            return "{$this->rule->name}: {$points} pkt";
        }

        return "{$label}: {$points} pkt";
    }

    /**
     * Create a score history entry for a contact.
     */
    public static function log(
        CrmContact $contact,
        string $eventType,
        int $pointsChange,
        int $scoreBefore,
        int $scoreAfter,
        ?LeadScoringRule $rule = null,
        array $metadata = []
    ): self {
        return self::create([
            'crm_contact_id' => $contact->id,
            'lead_scoring_rule_id' => $rule?->id,
            'event_type' => $eventType,
            'points_change' => $pointsChange,
            'score_before' => $scoreBefore,
            'score_after' => $scoreAfter,
            'metadata' => $metadata,
        ]);
    }
}

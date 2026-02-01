<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardIntelScan extends Model
{
    protected $table = 'cardintel_scans';

    protected $fillable = [
        'user_id',
        'file_path',
        'file_url',
        'raw_text',
        'status',
        'mode',
        'error_message',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Possible statuses for a scan.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * Possible modes for processing.
     */
    public const MODE_MANUAL = 'manual';
    public const MODE_AGENT = 'agent';
    public const MODE_AUTO = 'auto';

    /**
     * Get the user that owns this scan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the extraction results for this scan.
     */
    public function extraction(): HasOne
    {
        return $this->hasOne(CardIntelExtraction::class, 'scan_id');
    }

    /**
     * Get the context scoring for this scan.
     */
    public function context(): HasOne
    {
        return $this->hasOne(CardIntelContext::class, 'scan_id');
    }

    /**
     * Get the enrichment data for this scan.
     */
    public function enrichment(): HasOne
    {
        return $this->hasOne(CardIntelEnrichment::class, 'scan_id');
    }

    /**
     * Get all actions for this scan.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(CardIntelAction::class, 'scan_id');
    }

    /**
     * Get the Contact Intelligence Record linked to this scan.
     */
    public function intelligenceRecord(): HasOne
    {
        return $this->hasOne(ContactIntelligenceRecord::class, 'latest_scan_id');
    }

    /**
     * Scope for user's scans.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for pending scans (queue).
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for processing scans.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope for completed scans.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed scans.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for scans needing review (not completed in manual/agent mode).
     */
    public function scopeNeedsReview($query)
    {
        return $query->where('status', self::STATUS_COMPLETED)
                     ->whereIn('mode', [self::MODE_MANUAL, self::MODE_AGENT])
                     ->whereDoesntHave('actions', function ($q) {
                         $q->whereIn('action_type', ['send_email', 'send_sms'])
                           ->where('status', 'completed');
                     });
    }

    /**
     * Check if scan is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if scan is in manual mode.
     */
    public function isManualMode(): bool
    {
        return $this->mode === self::MODE_MANUAL;
    }

    /**
     * Check if scan is in auto mode.
     */
    public function isAutoMode(): bool
    {
        return $this->mode === self::MODE_AUTO;
    }

    /**
     * Get the contact's primary email from extraction.
     */
    public function getEmailAttribute(): ?string
    {
        return $this->extraction?->fields['email'] ?? null;
    }

    /**
     * Get the contact's full name from extraction.
     */
    public function getFullNameAttribute(): ?string
    {
        $extraction = $this->extraction;
        if (!$extraction) {
            return null;
        }

        $firstName = $extraction->fields['first_name'] ?? '';
        $lastName = $extraction->fields['last_name'] ?? '';

        return trim("$firstName $lastName") ?: null;
    }

    /**
     * Get the company name from extraction.
     */
    public function getCompanyAttribute(): ?string
    {
        return $this->extraction?->fields['company'] ?? null;
    }

    /**
     * Mark scan as processing.
     */
    public function markAsProcessing(): self
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
        return $this;
    }

    /**
     * Mark scan as completed.
     */
    public function markAsCompleted(): self
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
        return $this;
    }

    /**
     * Mark scan as failed with error message.
     */
    public function markAsFailed(string $errorMessage): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
        return $this;
    }
}

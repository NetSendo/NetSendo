<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ContactIntelligenceRecord extends Model
{
    protected $table = 'contact_intelligence_records';

    protected $fillable = [
        'user_id',
        'contact_key',
        'latest_scan_id',
        'crm_contact_id',
        'merged_profile_json',
        'timeline_json',
        'is_synced_to_crm',
        'last_synced_at',
    ];

    protected $casts = [
        'merged_profile_json' => 'array',
        'timeline_json' => 'array',
        'is_synced_to_crm' => 'boolean',
        'last_synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns this record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the latest scan for this record.
     */
    public function latestScan(): BelongsTo
    {
        return $this->belongsTo(CardIntelScan::class, 'latest_scan_id');
    }

    /**
     * Get the CRM contact if synced.
     */
    public function crmContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    /**
     * Get merged profile as array (accessor).
     */
    protected function mergedProfile(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->merged_profile_json ?? [],
        );
    }

    /**
     * Get timeline as array (accessor).
     */
    protected function timeline(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->timeline_json ?? [],
        );
    }

    /**
     * Scope for user's records.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for synced to CRM.
     */
    public function scopeSyncedToCrm($query)
    {
        return $query->where('is_synced_to_crm', true);
    }

    /**
     * Scope for not synced to CRM.
     */
    public function scopeNotSyncedToCrm($query)
    {
        return $query->where('is_synced_to_crm', false);
    }

    /**
     * Get email from merged profile.
     */
    public function getEmailAttribute(): ?string
    {
        return $this->merged_profile['email'] ?? null;
    }

    /**
     * Get phone from merged profile.
     */
    public function getPhoneAttribute(): ?string
    {
        return $this->merged_profile['phone'] ?? null;
    }

    /**
     * Get full name from merged profile.
     */
    public function getFullNameAttribute(): ?string
    {
        $firstName = $this->merged_profile['first_name'] ?? '';
        $lastName = $this->merged_profile['last_name'] ?? '';

        return trim("$firstName $lastName") ?: null;
    }

    /**
     * Get company from merged profile.
     */
    public function getCompanyAttribute(): ?string
    {
        return $this->merged_profile['company'] ?? null;
    }

    /**
     * Get latest context level.
     */
    public function getContextLevelAttribute(): ?string
    {
        return $this->latestScan?->context?->context_level;
    }

    /**
     * Get latest quality score.
     */
    public function getQualityScoreAttribute(): ?int
    {
        return $this->latestScan?->context?->quality_score;
    }

    /**
     * Add an event to the timeline.
     */
    public function addTimelineEvent(string $type, array $data = []): self
    {
        $timeline = $this->timeline_json ?? [];

        $timeline[] = [
            'type' => $type,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->update(['timeline_json' => $timeline]);

        return $this;
    }

    /**
     * Update merged profile with new data.
     */
    public function mergeProfile(array $newData): self
    {
        $profile = $this->merged_profile_json ?? [];

        // Only update non-empty values
        foreach ($newData as $key => $value) {
            if (!empty($value)) {
                $profile[$key] = $value;
            }
        }

        $this->update(['merged_profile_json' => $profile]);

        return $this;
    }

    /**
     * Mark as synced to CRM.
     */
    public function markAsSyncedToCrm(int $crmContactId): self
    {
        $this->update([
            'crm_contact_id' => $crmContactId,
            'is_synced_to_crm' => true,
            'last_synced_at' => now(),
        ]);

        $this->addTimelineEvent('synced_to_crm', ['crm_contact_id' => $crmContactId]);

        return $this;
    }

    /**
     * Create contact key from email or phone.
     */
    public static function createContactKey(?string $email, ?string $phone): ?string
    {
        if ($email) {
            return 'email:' . md5(strtolower(trim($email)));
        }

        if ($phone) {
            // Normalize phone: remove all non-digits
            $normalized = preg_replace('/\D/', '', $phone);
            return 'phone:' . md5($normalized);
        }

        return null;
    }

    /**
     * Find or create a CIR for a scan.
     */
    public static function findOrCreateForScan(CardIntelScan $scan): ?self
    {
        $extraction = $scan->extraction;

        if (!$extraction || !$extraction->hasMinimumFields()) {
            return null;
        }

        $fields = $extraction->fields;
        $contactKey = self::createContactKey($fields['email'], $fields['phone']);

        if (!$contactKey) {
            return null;
        }

        $record = self::firstOrCreate(
            [
                'user_id' => $scan->user_id,
                'contact_key' => $contactKey,
            ],
            [
                'merged_profile_json' => $fields,
                'timeline_json' => [],
            ]
        );

        // Update with latest scan
        $record->update(['latest_scan_id' => $scan->id]);
        $record->mergeProfile($fields);
        $record->addTimelineEvent('scan_added', ['scan_id' => $scan->id]);

        return $record;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmFollowUpSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'trigger_type',
        'trigger_conditions',
        'is_active',
        'is_default',
        'default_key',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'trigger_conditions' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user who owns this sequence.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the steps in this sequence.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(CrmFollowUpStep::class, 'sequence_id')->orderBy('position');
    }

    /**
     * Get all enrollments for this sequence.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CrmFollowUpEnrollment::class, 'sequence_id');
    }

    /**
     * Get active enrollments for this sequence.
     */
    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->where('status', 'active');
    }

    // ==================== SCOPES ====================

    /**
     * Scope to only include sequences for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to only include active sequences.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by trigger type.
     */
    public function scopeByTrigger($query, string $triggerType)
    {
        return $query->where('trigger_type', $triggerType);
    }

    /**
     * Scope to only include default sequences.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to filter by default key.
     */
    public function scopeByDefaultKey($query, string $defaultKey)
    {
        return $query->where('default_key', $defaultKey);
    }

    /**
     * Get the translated name if default sequence.
     */
    public function getNameAttribute($value)
    {
        if ($this->is_default && $this->default_key) {
            $key = 'crm.default_sequences.' . $this->default_key . '.name';
            $translation = __($key);

            if ($translation !== $key) {
                return $translation;
            }
        }

        return $value;
    }

    /**
     * Get the translated description if default sequence.
     */
    public function getDescriptionAttribute($value)
    {
        if ($this->is_default && $this->default_key) {
            $key = 'crm.default_sequences.' . $this->default_key . '.description';
            $translation = __($key);

            if ($translation !== $key) {
                return $translation;
            }
        }

        return $value;
    }

    // ==================== ACTIONS ====================

    /**
     * Enroll a contact into this sequence.
     */
    public function enrollContact(CrmContact $contact): CrmFollowUpEnrollment
    {
        $firstStep = $this->steps()->first();

        $enrollment = $this->enrollments()->create([
            'crm_contact_id' => $contact->id,
            'current_step_id' => $firstStep?->id,
            'status' => 'active',
            'started_at' => now(),
            'next_action_at' => $firstStep
                ? now()->addDays($firstStep->delay_days)->addHours($firstStep->delay_hours)
                : null,
        ]);

        return $enrollment;
    }

    /**
     * Check if a contact is already enrolled.
     */
    public function isContactEnrolled(CrmContact $contact): bool
    {
        return $this->enrollments()
            ->where('crm_contact_id', $contact->id)
            ->whereIn('status', ['active', 'paused'])
            ->exists();
    }

    /**
     * Get the total number of steps.
     */
    public function getStepsCountAttribute(): int
    {
        return $this->steps()->count();
    }

    /**
     * Get enrollment statistics.
     */
    public function getStats(): array
    {
        return [
            'total_enrolled' => $this->enrollments()->count(),
            'active' => $this->enrollments()->where('status', 'active')->count(),
            'completed' => $this->enrollments()->where('status', 'completed')->count(),
            'paused' => $this->enrollments()->where('status', 'paused')->count(),
        ];
    }

    /**
     * Duplicate this sequence.
     */
    public function duplicate(): self
    {
        $newSequence = $this->replicate();
        $newSequence->name = $this->name . ' (kopia)';
        $newSequence->save();

        foreach ($this->steps as $step) {
            $newStep = $step->replicate();
            $newStep->sequence_id = $newSequence->id;
            $newStep->save();
        }

        return $newSequence;
    }
}

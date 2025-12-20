<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Funnel extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';

    // Trigger type constants
    public const TRIGGER_LIST_SIGNUP = 'list_signup';
    public const TRIGGER_TAG_ADDED = 'tag_added';
    public const TRIGGER_FORM_SUBMIT = 'form_submit';
    public const TRIGGER_MANUAL = 'manual';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'status',
        'trigger_type',
        'trigger_list_id',
        'trigger_form_id',
        'trigger_tag',
        'subscribers_count',
        'completed_count',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'subscribers_count' => 'integer',
        'completed_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($funnel) {
            if (empty($funnel->slug)) {
                $funnel->slug = static::generateUniqueSlug($funnel->name);
            }
        });
    }

    /**
     * Generate a unique slug for the funnel.
     */
    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }

    // =====================================
    // Relationships
    // =====================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FunnelStep::class)->orderBy('order');
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(FunnelSubscriber::class);
    }

    public function triggerList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class, 'trigger_list_id');
    }

    public function triggerForm(): BelongsTo
    {
        return $this->belongsTo(SubscriptionForm::class, 'trigger_form_id');
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePaused($query)
    {
        return $query->where('status', self::STATUS_PAUSED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByTriggerType($query, string $type)
    {
        return $query->where('trigger_type', $type);
    }

    public function scopeForList($query, int $listId)
    {
        return $query->where('trigger_list_id', $listId);
    }

    // =====================================
    // Accessors
    // =====================================

    public function getCompletionRateAttribute(): float
    {
        if ($this->subscribers_count === 0) {
            return 0;
        }

        return round(($this->completed_count / $this->subscribers_count) * 100, 1);
    }

    public function getActiveSubscribersCountAttribute(): int
    {
        return $this->subscribers()->where('status', FunnelSubscriber::STATUS_ACTIVE)->count();
    }

    // =====================================
    // Methods
    // =====================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function activate(): bool
    {
        if ($this->steps()->count() === 0) {
            return false;
        }

        $this->status = self::STATUS_ACTIVE;
        return $this->save();
    }

    public function pause(): bool
    {
        $this->status = self::STATUS_PAUSED;
        return $this->save();
    }

    public function getStartStep(): ?FunnelStep
    {
        return $this->steps()->where('type', FunnelStep::TYPE_START)->first();
    }

    public function getStats(): array
    {
        return [
            'total_subscribers' => $this->subscribers_count,
            'active_subscribers' => $this->active_subscribers_count,
            'completed' => $this->completed_count,
            'completion_rate' => $this->completion_rate,
            'steps_count' => $this->steps()->count(),
        ];
    }

    public function duplicate(string $newName = null): Funnel
    {
        $newFunnel = $this->replicate([
            'slug',
            'subscribers_count',
            'completed_count',
        ]);

        $newFunnel->name = $newName ?? $this->name . ' (kopia)';
        $newFunnel->slug = static::generateUniqueSlug($newFunnel->name);
        $newFunnel->status = self::STATUS_DRAFT;
        $newFunnel->save();

        // Duplicate steps
        $stepMapping = [];
        foreach ($this->steps as $step) {
            $newStep = $step->replicate();
            $newStep->funnel_id = $newFunnel->id;
            $newStep->save();
            $stepMapping[$step->id] = $newStep->id;
        }

        // Update step connections
        foreach ($newFunnel->steps as $step) {
            $updated = false;
            if ($step->next_step_id && isset($stepMapping[$step->next_step_id])) {
                $step->next_step_id = $stepMapping[$step->next_step_id];
                $updated = true;
            }
            if ($step->next_step_yes_id && isset($stepMapping[$step->next_step_yes_id])) {
                $step->next_step_yes_id = $stepMapping[$step->next_step_yes_id];
                $updated = true;
            }
            if ($step->next_step_no_id && isset($stepMapping[$step->next_step_no_id])) {
                $step->next_step_no_id = $stepMapping[$step->next_step_no_id];
                $updated = true;
            }
            if ($updated) {
                $step->save();
            }
        }

        return $newFunnel;
    }

    public function incrementSubscribersCount(): void
    {
        $this->increment('subscribers_count');
    }

    public function incrementCompletedCount(): void
    {
        $this->increment('completed_count');
    }

    public static function getTriggerTypes(): array
    {
        return [
            self::TRIGGER_LIST_SIGNUP => 'Po zapisie na listę',
            self::TRIGGER_TAG_ADDED => 'Po dodaniu tagu',
            self::TRIGGER_FORM_SUBMIT => 'Po wypełnieniu formularza',
            self::TRIGGER_MANUAL => 'Ręcznie',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Szkic',
            self::STATUS_ACTIVE => 'Aktywny',
            self::STATUS_PAUSED => 'Wstrzymany',
        ];
    }
}

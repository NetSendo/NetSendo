<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class FunnelSubscriber extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_EXITED = 'exited';

    protected $fillable = [
        'funnel_id',
        'subscriber_id',
        'current_step_id',
        'status',
        'next_action_at',
        'entered_at',
        'completed_at',
        'steps_completed',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'next_action_at' => 'datetime',
        'entered_at' => 'datetime',
        'completed_at' => 'datetime',
        'steps_completed' => 'integer',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'current_step_id');
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', self::STATUS_WAITING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeReadyToProcess($query)
    {
        return $query->where('status', self::STATUS_WAITING)
            ->where('next_action_at', '<=', now());
    }

    public function scopeForFunnel($query, int $funnelId)
    {
        return $query->where('funnel_id', $funnelId);
    }

    // =====================================
    // Status methods
    // =====================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isExited(): bool
    {
        return $this->status === self::STATUS_EXITED;
    }

    // =====================================
    // Step movement
    // =====================================

    public function moveToStep(FunnelStep $step): self
    {
        $this->current_step_id = $step->id;
        $this->status = self::STATUS_ACTIVE;
        $this->next_action_at = null;
        $this->save();

        return $this;
    }

    public function scheduleNextAction(int $delaySeconds): self
    {
        $this->status = self::STATUS_WAITING;
        $this->next_action_at = now()->addSeconds($delaySeconds);
        $this->save();

        return $this;
    }

    public function scheduleNextActionAt(Carbon $datetime): self
    {
        $this->status = self::STATUS_WAITING;
        $this->next_action_at = $datetime;
        $this->save();

        return $this;
    }

    public function incrementStepsCompleted(): self
    {
        $this->increment('steps_completed');
        return $this;
    }

    // =====================================
    // Status changes
    // =====================================

    public function markCompleted(): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->next_action_at = null;
        $this->save();

        $this->funnel->incrementCompletedCount();

        return $this;
    }

    public function markExited(string $reason = null): self
    {
        $this->status = self::STATUS_EXITED;
        $this->next_action_at = null;
        
        if ($reason) {
            $data = $this->data ?? [];
            $data['exit_reason'] = $reason;
            $this->data = $data;
        }
        
        $this->save();

        return $this;
    }

    public function pause(): self
    {
        $this->status = self::STATUS_PAUSED;
        $this->save();

        return $this;
    }

    public function resume(): self
    {
        $this->status = self::STATUS_ACTIVE;
        $this->save();

        return $this;
    }

    // =====================================
    // Data helpers
    // =====================================

    public function getData(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function setData(string $key, $value): self
    {
        $data = $this->data ?? [];
        $data[$key] = $value;
        $this->data = $data;
        $this->save();

        return $this;
    }

    public function addToHistory(string $action, array $details = []): self
    {
        $data = $this->data ?? [];
        $data['history'] = $data['history'] ?? [];
        $data['history'][] = [
            'action' => $action,
            'step_id' => $this->current_step_id,
            'at' => now()->toIso8601String(),
            'details' => $details,
        ];
        $this->data = $data;
        $this->save();

        return $this;
    }

    public function getHistory(): array
    {
        return $this->data['history'] ?? [];
    }

    // =====================================
    // Static methods
    // =====================================

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Aktywny',
            self::STATUS_WAITING => 'Oczekuje',
            self::STATUS_COMPLETED => 'Ukończony',
            self::STATUS_PAUSED => 'Wstrzymany',
            self::STATUS_EXITED => 'Opuścił',
        ];
    }

    /**
     * Enroll a subscriber in a funnel.
     */
    public static function enroll(Funnel $funnel, Subscriber $subscriber): ?self
    {
        // Check if already enrolled
        $existing = static::where('funnel_id', $funnel->id)
            ->where('subscriber_id', $subscriber->id)
            ->first();

        if ($existing) {
            return null;
        }

        // Get start step
        $startStep = $funnel->getStartStep();

        $enrollment = static::create([
            'funnel_id' => $funnel->id,
            'subscriber_id' => $subscriber->id,
            'current_step_id' => $startStep?->id,
            'status' => self::STATUS_ACTIVE,
            'entered_at' => now(),
            'steps_completed' => 0,
            'data' => [],
        ]);

        $funnel->incrementSubscribersCount();

        return $enrollment;
    }
}

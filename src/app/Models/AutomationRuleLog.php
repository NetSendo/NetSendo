<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRuleLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'automation_rule_id',
        'subscriber_id',
        'trigger_event',
        'trigger_data',
        'actions_executed',
        'status',
        'error_message',
        'execution_time_ms',
        'executed_at',
    ];

    protected $casts = [
        'trigger_data' => 'array',
        'actions_executed' => 'array',
        'executed_at' => 'datetime',
    ];

    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    // Relationships

    public function automationRule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    // Scopes

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('executed_at', '>=', now()->subDays($days));
    }

    public function scopeForRule($query, int $ruleId)
    {
        return $query->where('automation_rule_id', $ruleId);
    }

    // Helper methods

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'Sukces',
            self::STATUS_PARTIAL => 'Częściowy sukces',
            self::STATUS_FAILED => 'Błąd',
            self::STATUS_SKIPPED => 'Pominięto',
            default => $this->status,
        };
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'green',
            self::STATUS_PARTIAL => 'yellow',
            self::STATUS_FAILED => 'red',
            self::STATUS_SKIPPED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get executed actions summary
     */
    public function getActionsSummaryAttribute(): string
    {
        if (!is_array($this->actions_executed)) {
            return '-';
        }

        $count = count($this->actions_executed);
        $successful = collect($this->actions_executed)->where('status', 'success')->count();
        
        return "{$successful}/{$count} akcji";
    }

    /**
     * Create a log entry for a successful execution
     */
    public static function logSuccess(
        AutomationRule $rule,
        ?Subscriber $subscriber,
        string $triggerEvent,
        array $triggerData,
        array $actionsExecuted,
        int $executionTimeMs
    ): self {
        return self::create([
            'automation_rule_id' => $rule->id,
            'subscriber_id' => $subscriber?->id,
            'trigger_event' => $triggerEvent,
            'trigger_data' => $triggerData,
            'actions_executed' => $actionsExecuted,
            'status' => self::STATUS_SUCCESS,
            'execution_time_ms' => $executionTimeMs,
            'executed_at' => now(),
        ]);
    }

    /**
     * Create a log entry for a failed execution
     */
    public static function logFailure(
        AutomationRule $rule,
        ?Subscriber $subscriber,
        string $triggerEvent,
        array $triggerData,
        array $actionsExecuted,
        string $errorMessage,
        int $executionTimeMs
    ): self {
        return self::create([
            'automation_rule_id' => $rule->id,
            'subscriber_id' => $subscriber?->id,
            'trigger_event' => $triggerEvent,
            'trigger_data' => $triggerData,
            'actions_executed' => $actionsExecuted,
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'execution_time_ms' => $executionTimeMs,
            'executed_at' => now(),
        ]);
    }

    /**
     * Create a log entry for a skipped execution
     */
    public static function logSkipped(
        AutomationRule $rule,
        ?Subscriber $subscriber,
        string $triggerEvent,
        array $triggerData,
        string $reason
    ): self {
        return self::create([
            'automation_rule_id' => $rule->id,
            'subscriber_id' => $subscriber?->id,
            'trigger_event' => $triggerEvent,
            'trigger_data' => $triggerData,
            'actions_executed' => [],
            'status' => self::STATUS_SKIPPED,
            'error_message' => $reason,
            'executed_at' => now(),
        ]);
    }
}

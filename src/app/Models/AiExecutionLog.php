<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiExecutionLog extends Model
{
    protected $fillable = [
        'user_id',
        'ai_action_plan_id',
        'ai_action_plan_step_id',
        'agent_type',
        'action',
        'status',
        'input_data',
        'output_data',
        'error_message',
        'tokens_input',
        'tokens_output',
        'model_used',
        'duration_ms',
    ];

    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AiActionPlan::class, 'ai_action_plan_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AiActionPlanStep::class, 'ai_action_plan_step_id');
    }

    /**
     * Get total tokens.
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->tokens_input + $this->tokens_output;
    }

    /**
     * Create a success log entry.
     */
    public static function logSuccess(
        int $userId,
        string $agentType,
        string $action,
        array $input = [],
        array $output = [],
        int $tokensIn = 0,
        int $tokensOut = 0,
        ?string $model = null,
        int $durationMs = 0,
        ?int $planId = null,
        ?int $stepId = null,
    ): self {
        return static::create([
            'user_id' => $userId,
            'ai_action_plan_id' => $planId,
            'ai_action_plan_step_id' => $stepId,
            'agent_type' => $agentType,
            'action' => $action,
            'status' => 'success',
            'input_data' => $input,
            'output_data' => $output,
            'tokens_input' => $tokensIn,
            'tokens_output' => $tokensOut,
            'model_used' => $model,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Create an error log entry.
     */
    public static function logError(
        int $userId,
        string $agentType,
        string $action,
        string $error,
        array $input = [],
        ?int $planId = null,
        ?int $stepId = null,
    ): self {
        return static::create([
            'user_id' => $userId,
            'ai_action_plan_id' => $planId,
            'ai_action_plan_step_id' => $stepId,
            'agent_type' => $agentType,
            'action' => $action,
            'status' => 'error',
            'input_data' => $input,
            'error_message' => $error,
        ]);
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForAgent($query, string $agentType)
    {
        return $query->where('agent_type', $agentType);
    }

    public function scopeErrors($query)
    {
        return $query->where('status', 'error');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

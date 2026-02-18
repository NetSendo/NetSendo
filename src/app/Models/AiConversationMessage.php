<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConversationMessage extends Model
{
    protected $fillable = [
        'ai_conversation_id',
        'role',
        'content',
        'metadata',
        'tool_calls',
        'tool_results',
        'tokens_input',
        'tokens_output',
        'model_used',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tool_calls' => 'array',
        'tool_results' => 'array',
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    /**
     * Get total tokens for this message.
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->tokens_input + $this->tokens_output;
    }

    /**
     * Get the detected intent from metadata.
     */
    public function getIntentAttribute(): ?string
    {
        return $this->metadata['intent'] ?? null;
    }

    /**
     * Get the agent that handled this message.
     */
    public function getAgentAttribute(): ?string
    {
        return $this->metadata['agent'] ?? null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'title',
        'status',
        'context',
        'summary',
        'message_count',
        'total_tokens',
        'last_activity_at',
    ];

    protected $casts = [
        'context' => 'array',
        'summary' => 'array',
        'last_activity_at' => 'datetime',
        'message_count' => 'integer',
        'total_tokens' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiConversationMessage::class)->orderBy('created_at');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(AiActionPlan::class);
    }

    /**
     * Add a message to this conversation.
     */
    public function addMessage(string $role, string $content, array $metadata = []): AiConversationMessage
    {
        $message = $this->messages()->create([
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
        ]);

        $this->update([
            'message_count' => $this->message_count + 1,
            'last_activity_at' => now(),
        ]);

        return $message;
    }

    /**
     * Get recent messages for AI context window.
     */
    public function getRecentMessages(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return $this->messages()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Build the messages array for AI API calls.
     */
    public function buildAiMessages(int $limit = 20): array
    {
        return $this->getRecentMessages($limit)->map(function ($msg) {
            return [
                'role' => $msg->role === 'tool' ? 'assistant' : $msg->role,
                'content' => $msg->content,
            ];
        })->toArray();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Get or create an active conversation for user + channel.
     */
    public static function getOrCreateActive(int $userId, string $channel = 'web'): self
    {
        $conversation = static::forUser($userId)
            ->forChannel($channel)
            ->active()
            ->orderByDesc('last_activity_at')
            ->first();

        // Create new if none exists or last message was > 30 min ago
        if (!$conversation || ($conversation->last_activity_at && $conversation->last_activity_at->lt(now()->subMinutes(30)))) {
            $conversation = static::create([
                'user_id' => $userId,
                'channel' => $channel,
                'status' => 'active',
                'last_activity_at' => now(),
            ]);
        }

        return $conversation;
    }
}

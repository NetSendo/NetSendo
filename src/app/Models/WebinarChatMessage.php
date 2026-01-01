<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebinarChatMessage extends Model
{
    use HasFactory;

    // Sender type constants
    public const SENDER_HOST = 'host';
    public const SENDER_MODERATOR = 'moderator';
    public const SENDER_ATTENDEE = 'attendee';
    public const SENDER_SYSTEM = 'system';
    public const SENDER_BOT = 'bot';

    // Message type constants
    public const TYPE_TEXT = 'text';
    public const TYPE_PRODUCT = 'product';
    public const TYPE_CTA = 'cta';
    public const TYPE_POLL = 'poll';
    public const TYPE_REACTION = 'reaction';
    public const TYPE_QUESTION = 'question';
    public const TYPE_ANSWER = 'answer';

    protected $fillable = [
        'webinar_id',
        'webinar_session_id',
        'registration_id',
        'sender_type',
        'sender_name',
        'sender_avatar_url',
        'message',
        'message_type',
        'is_pinned',
        'is_highlighted',
        'is_answered',
        'is_visible',
        'is_deleted',
        'metadata',
        'show_at_seconds',
        'likes_count',
        'parent_id',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_highlighted' => 'boolean',
        'is_answered' => 'boolean',
        'is_visible' => 'boolean',
        'is_deleted' => 'boolean',
        'metadata' => 'array',
        'show_at_seconds' => 'integer',
        'likes_count' => 'integer',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function webinar(): BelongsTo
    {
        return $this->belongsTo(Webinar::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WebinarSession::class, 'webinar_session_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(WebinarRegistration::class, 'registration_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WebinarChatMessage::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(WebinarChatMessage::class, 'parent_id');
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true)->where('is_deleted', false);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeQuestions($query)
    {
        return $query->where('message_type', self::TYPE_QUESTION);
    }

    public function scopeUnanswered($query)
    {
        return $query->where('message_type', self::TYPE_QUESTION)
            ->where('is_answered', false);
    }

    public function scopeFromHost($query)
    {
        return $query->whereIn('sender_type', [self::SENDER_HOST, self::SENDER_MODERATOR]);
    }

    public function scopeFromAttendees($query)
    {
        return $query->where('sender_type', self::SENDER_ATTENDEE);
    }

    public function scopeForAutoWebinar($query, int $currentSeconds, int $bufferSeconds = 5)
    {
        return $query->whereNotNull('show_at_seconds')
            ->where('show_at_seconds', '<=', $currentSeconds)
            ->where('show_at_seconds', '>', $currentSeconds - $bufferSeconds);
    }

    // =====================================
    // Accessors
    // =====================================

    /**
     * Check if message is from host/moderator.
     */
    public function getIsFromHostAttribute(): bool
    {
        return in_array($this->sender_type, [self::SENDER_HOST, self::SENDER_MODERATOR]);
    }

    /**
     * Get formatted timestamp.
     */
    public function getFormattedTimeAttribute(): string
    {
        return $this->created_at->format('H:i');
    }

    /**
     * Get avatar URL or generate from seed.
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->sender_avatar_url) {
            return $this->sender_avatar_url;
        }

        // Generate avatar from sender name using DiceBear API
        $seed = urlencode($this->sender_name);
        return "https://api.dicebear.com/7.x/initials/svg?seed={$seed}";
    }

    // =====================================
    // Methods
    // =====================================

    /**
     * Pin message.
     */
    public function pin(): void
    {
        // Unpin other messages first
        $this->webinar->chatMessages()
            ->where('id', '!=', $this->id)
            ->where('is_pinned', true)
            ->update(['is_pinned' => false]);

        $this->update(['is_pinned' => true]);
    }

    /**
     * Unpin message.
     */
    public function unpin(): void
    {
        $this->update(['is_pinned' => false]);
    }

    /**
     * Highlight message.
     */
    public function highlight(): void
    {
        $this->update(['is_highlighted' => true]);
    }

    /**
     * Mark as answered (for Q&A).
     */
    public function markAsAnswered(): void
    {
        $this->update(['is_answered' => true]);
    }

    /**
     * Soft delete (hide).
     */
    public function softDelete(): void
    {
        $this->update([
            'is_visible' => false,
            'is_deleted' => true,
        ]);
    }

    /**
     * Add like.
     */
    public function addLike(): void
    {
        $this->increment('likes_count');
    }

    /**
     * Create system message.
     */
    public static function createSystemMessage(
        Webinar $webinar,
        string $message,
        ?WebinarSession $session = null
    ): self {
        return self::create([
            'webinar_id' => $webinar->id,
            'webinar_session_id' => $session?->id,
            'sender_type' => self::SENDER_SYSTEM,
            'sender_name' => 'System',
            'message' => $message,
            'message_type' => self::TYPE_TEXT,
        ]);
    }

    /**
     * Create product message.
     */
    public static function createProductMessage(
        Webinar $webinar,
        WebinarProduct $product,
        ?WebinarSession $session = null
    ): self {
        return self::create([
            'webinar_id' => $webinar->id,
            'webinar_session_id' => $session?->id,
            'sender_type' => self::SENDER_SYSTEM,
            'sender_name' => 'Oferta',
            'message' => $product->name,
            'message_type' => self::TYPE_PRODUCT,
            'is_pinned' => true,
            'metadata' => [
                'product_id' => $product->id,
                'price' => $product->price,
                'currency' => $product->currency,
                'cta_text' => $product->cta_text,
                'image_url' => $product->image_url,
            ],
        ]);
    }

    /**
     * Get broadcast data for WebSocket.
     */
    public function toBroadcast(): array
    {
        return [
            'id' => $this->id,
            'sender_type' => $this->sender_type,
            'sender_name' => $this->sender_name,
            'avatar_url' => $this->avatar_url,
            'message' => $this->message,
            'message_type' => $this->message_type,
            'is_pinned' => $this->is_pinned,
            'is_highlighted' => $this->is_highlighted,
            'is_from_host' => $this->is_from_host,
            'likes_count' => $this->likes_count,
            'metadata' => $this->metadata,
            'formatted_time' => $this->formatted_time,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

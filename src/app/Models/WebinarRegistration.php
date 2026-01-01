<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebinarRegistration extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_ATTENDED = 'attended';
    public const STATUS_MISSED = 'missed';
    public const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'webinar_id',
        'webinar_session_id',
        'subscriber_id',
        'email',
        'first_name',
        'last_name',
        'phone',
        'custom_fields',
        'access_token',
        'status',
        'joined_at',
        'left_at',
        'watch_time_seconds',
        'max_video_position_seconds',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'ip_address',
        'user_agent',
        'timezone',
        'referrer_url',
        'reminder_24h_sent',
        'reminder_1h_sent',
        'reminder_15min_sent',
        'replay_email_sent',
        'chat_messages_count',
        'reactions_count',
        'made_purchase',
        'purchase_amount',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'watch_time_seconds' => 'integer',
        'max_video_position_seconds' => 'integer',
        'reminder_24h_sent' => 'boolean',
        'reminder_1h_sent' => 'boolean',
        'reminder_15min_sent' => 'boolean',
        'replay_email_sent' => 'boolean',
        'chat_messages_count' => 'integer',
        'reactions_count' => 'integer',
        'made_purchase' => 'boolean',
        'purchase_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($registration) {
            if (empty($registration->access_token)) {
                $registration->access_token = Str::random(64);
            }
        });
    }

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

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(WebinarChatMessage::class, 'registration_id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(WebinarAnalytic::class, 'registration_id');
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeAttended($query)
    {
        return $query->where('status', self::STATUS_ATTENDED);
    }

    public function scopeMissed($query)
    {
        return $query->where('status', self::STATUS_MISSED);
    }

    public function scopeNeedsReminder24h($query)
    {
        return $query->where('reminder_24h_sent', false)
            ->whereHas('webinar', function ($q) {
                $q->where('scheduled_at', '<=', now()->addHours(24))
                  ->where('scheduled_at', '>', now()->addHours(23));
            });
    }

    public function scopeNeedsReminder1h($query)
    {
        return $query->where('reminder_1h_sent', false)
            ->whereHas('webinar', function ($q) {
                $q->where('scheduled_at', '<=', now()->addHour())
                  ->where('scheduled_at', '>', now()->addMinutes(55));
            });
    }

    public function scopeWithPurchase($query)
    {
        return $query->where('made_purchase', true);
    }

    // =====================================
    // Accessors
    // =====================================

    /**
     * Get full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: $this->email;
    }

    /**
     * Get display name (for chat).
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->first_name) {
            return $this->first_name;
        }

        return explode('@', $this->email)[0];
    }

    /**
     * Get watch URL.
     */
    public function getWatchUrlAttribute(): string
    {
        return route('webinar.watch', [
            'slug' => $this->webinar->slug,
            'token' => $this->access_token,
        ]);
    }

    /**
     * Get watch time in minutes.
     */
    public function getWatchTimeMinutesAttribute(): int
    {
        return (int) floor($this->watch_time_seconds / 60);
    }

    /**
     * Get engagement score (0-100).
     */
    public function getEngagementScoreAttribute(): int
    {
        $score = 0;

        // Base score for attending
        if ($this->joined_at) {
            $score += 30;
        }

        // Watch time score (up to 40 points)
        $webinarDuration = $this->webinar->duration_minutes ?? 60;
        $watchPercentage = min(100, ($this->watch_time_seconds / 60 / $webinarDuration) * 100);
        $score += (int) ($watchPercentage * 0.4);

        // Chat engagement (up to 15 points)
        $score += min(15, $this->chat_messages_count * 3);

        // Reactions (up to 10 points)
        $score += min(10, $this->reactions_count * 2);

        // Purchase bonus
        if ($this->made_purchase) {
            $score += 5;
        }

        return min(100, $score);
    }

    // =====================================
    // Methods
    // =====================================

    /**
     * Mark as joined.
     */
    public function join(): void
    {
        if ($this->joined_at) {
            // Already joined before, just update left_at to null (rejoining)
            $this->update(['left_at' => null]);
            return;
        }

        $this->update([
            'joined_at' => now(),
            'status' => self::STATUS_ATTENDED,
        ]);

        // Increment webinar/session attendees
        if ($this->session) {
            $this->session->incrementAttendees();
        } else {
            $this->webinar->incrementAttendees();
        }

        // Add attended tag
        $this->addTag($this->webinar->attended_tag);
    }

    /**
     * Mark as left.
     */
    public function leave(): void
    {
        if (!$this->joined_at) {
            return;
        }

        $watchTime = now()->diffInSeconds($this->joined_at);

        $this->update([
            'left_at' => now(),
            'watch_time_seconds' => $this->watch_time_seconds + $watchTime,
        ]);
    }

    /**
     * Update video position.
     */
    public function updateVideoPosition(int $seconds): void
    {
        if ($seconds > $this->max_video_position_seconds) {
            $this->update(['max_video_position_seconds' => $seconds]);
        }
    }

    /**
     * Mark as missed (didn't attend).
     */
    public function markAsMissed(): void
    {
        if ($this->status !== self::STATUS_REGISTERED) {
            return;
        }

        $this->update(['status' => self::STATUS_MISSED]);

        // Add missed tag
        $this->addTag($this->webinar->missed_tag);
    }

    /**
     * Record purchase.
     */
    public function recordPurchase(float $amount): void
    {
        $this->update([
            'made_purchase' => true,
            'purchase_amount' => $this->purchase_amount + $amount,
        ]);

        // Add purchased tag
        $this->addTag($this->webinar->purchased_tag);
    }

    /**
     * Add tag to linked subscriber.
     */
    protected function addTag(?string $tagName): void
    {
        if (!$tagName || !$this->subscriber_id) {
            return;
        }

        $tag = Tag::firstOrCreate(
            ['name' => $tagName, 'user_id' => $this->webinar->user_id],
            ['name' => $tagName, 'user_id' => $this->webinar->user_id]
        );

        $this->subscriber->tags()->syncWithoutDetaching([$tag->id]);
    }

    /**
     * Regenerate access token.
     */
    public function regenerateToken(): string
    {
        $this->access_token = Str::random(64);
        $this->save();
        return $this->access_token;
    }

    /**
     * Get UTM data.
     */
    public function getUtmData(): array
    {
        return array_filter([
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'utm_content' => $this->utm_content,
            'utm_term' => $this->utm_term,
        ]);
    }
}

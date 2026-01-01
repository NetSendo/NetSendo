<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Webinar extends Model
{
    use HasFactory;

    // Type constants
    public const TYPE_LIVE = 'live';
    public const TYPE_AUTO = 'auto';
    public const TYPE_HYBRID = 'hybrid';

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_LIVE = 'live';
    public const STATUS_ENDED = 'ended';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'thumbnail_url',
        'type',
        'status',
        'video_url',
        'youtube_live_id',
        'video_provider',
        'registration_page_id',
        'thank_you_page_id',
        'thank_you_url',
        'target_list_id',
        'registration_tag',
        'attended_tag',
        'missed_tag',
        'purchased_tag',
        'settings',
        'scheduled_at',
        'started_at',
        'ended_at',
        'duration_minutes',
        'registrations_count',
        'attendees_count',
        'peak_viewers',
        'timezone',
    ];

    protected $casts = [
        'settings' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'registrations_count' => 'integer',
        'attendees_count' => 'integer',
        'peak_viewers' => 'integer',
        'duration_minutes' => 'integer',
    ];

    /**
     * Default settings for a webinar.
     */
    public const DEFAULT_SETTINGS = [
        'theme' => 'dark',
        'primary_color' => '#6366f1',
        'secondary_color' => '#8b5cf6',
        'chat_enabled' => true,
        'chat_moderated' => false,
        'show_attendee_count' => true,
        'allow_replay' => true,
        'replay_available_hours' => 48,
        'registration_fields' => ['email', 'first_name'],
        'branding' => [
            'logo' => null,
            'background_image' => null,
        ],
        'countdown_enabled' => true,
        'max_attendees' => null,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($webinar) {
            if (empty($webinar->slug)) {
                $webinar->slug = static::generateUniqueSlug($webinar->name);
            }
        });
    }

    /**
     * Generate a unique slug for the webinar.
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

    public function sessions(): HasMany
    {
        return $this->hasMany(WebinarSession::class)->orderBy('scheduled_at', 'desc');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(WebinarRegistration::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(WebinarChatMessage::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(WebinarProduct::class)->orderBy('sort_order');
    }

    public function ctas(): HasMany
    {
        return $this->hasMany(WebinarCta::class)->orderBy('sort_order');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(WebinarAnalytic::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(AutoWebinarSchedule::class);
    }

    public function chatScripts(): HasMany
    {
        return $this->hasMany(AutoWebinarChatScript::class)->orderBy('show_at_seconds');
    }

    public function registrationPage(): BelongsTo
    {
        return $this->belongsTo(ExternalPage::class, 'registration_page_id');
    }

    public function thankYouPage(): BelongsTo
    {
        return $this->belongsTo(ExternalPage::class, 'thank_you_page_id');
    }

    public function targetList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class, 'target_list_id');
    }

    // =====================================
    // Scopes
    // =====================================

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeLive($query)
    {
        return $query->where('type', self::TYPE_LIVE);
    }

    public function scopeAuto($query)
    {
        return $query->where('type', self::TYPE_AUTO);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_LIVE, self::STATUS_PUBLISHED]);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>', now());
    }

    // =====================================
    // Accessors
    // =====================================

    /**
     * Get settings with defaults merged.
     */
    public function getSettingsWithDefaultsAttribute(): array
    {
        return array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }

    /**
     * Get the thank you URL (from page or custom).
     */
    public function getThankYouUrlAttribute(): ?string
    {
        if (!empty($this->attributes['thank_you_url'])) {
            return $this->attributes['thank_you_url'];
        }

        if ($this->thankYouPage) {
            return route('external-page.show', $this->thankYouPage);
        }

        return null;
    }

    /**
     * Get the registration URL.
     */
    public function getRegistrationUrlAttribute(): string
    {
        return route('webinar.register', $this->slug);
    }

    /**
     * Check if chat is enabled.
     */
    public function getChatEnabledAttribute(): bool
    {
        return $this->settings_with_defaults['chat_enabled'] ?? true;
    }

    /**
     * Calculate conversion rate.
     */
    public function getConversionRateAttribute(): float
    {
        if ($this->registrations_count === 0) {
            return 0;
        }

        return round(($this->attendees_count / $this->registrations_count) * 100, 1);
    }

    // =====================================
    // Methods
    // =====================================

    /**
     * Check if webinar is live.
     */
    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    /**
     * Check if webinar is auto-webinar.
     */
    public function isAutoWebinar(): bool
    {
        return $this->type === self::TYPE_AUTO;
    }

    /**
     * Check if webinar allows registration.
     */
    public function canRegister(): bool
    {
        if ($this->status === self::STATUS_DRAFT) {
            return false;
        }

        $maxAttendees = $this->settings_with_defaults['max_attendees'];
        if ($maxAttendees && $this->registrations_count >= $maxAttendees) {
            return false;
        }

        return true;
    }

    /**
     * Start the webinar.
     */
    public function start(): bool
    {
        if ($this->status !== self::STATUS_SCHEDULED) {
            return false;
        }

        $this->status = self::STATUS_LIVE;
        $this->started_at = now();
        return $this->save();
    }

    /**
     * End the webinar.
     */
    public function end(): bool
    {
        if ($this->status !== self::STATUS_LIVE) {
            return false;
        }

        $this->status = self::STATUS_ENDED;
        $this->ended_at = now();

        if ($this->started_at) {
            $this->duration_minutes = $this->started_at->diffInMinutes($this->ended_at);
        }

        return $this->save();
    }

    /**
     * Publish as replay.
     */
    public function publish(): bool
    {
        if (!in_array($this->status, [self::STATUS_ENDED, self::STATUS_DRAFT])) {
            return false;
        }

        $this->status = self::STATUS_PUBLISHED;
        return $this->save();
    }

    /**
     * Duplicate webinar.
     */
    public function duplicate(string $newName = null): Webinar
    {
        $newWebinar = $this->replicate([
            'slug',
            'status',
            'started_at',
            'ended_at',
            'registrations_count',
            'attendees_count',
            'peak_viewers',
        ]);

        $newWebinar->name = $newName ?? $this->name . ' (kopia)';
        $newWebinar->slug = static::generateUniqueSlug($newWebinar->name);
        $newWebinar->status = self::STATUS_DRAFT;
        $newWebinar->save();

        // Duplicate products
        foreach ($this->products as $product) {
            $newProduct = $product->replicate(['is_pinned', 'pinned_at', 'sold_count']);
            $newProduct->webinar_id = $newWebinar->id;
            $newProduct->save();
        }

        // Duplicate CTAs
        foreach ($this->ctas as $cta) {
            $newCta = $cta->replicate(['is_visible', 'views_count', 'clicks_count']);
            $newCta->webinar_id = $newWebinar->id;
            $newCta->save();
        }

        // Duplicate chat scripts for auto-webinars
        if ($this->isAutoWebinar()) {
            foreach ($this->chatScripts as $script) {
                $newScript = $script->replicate();
                $newScript->webinar_id = $newWebinar->id;
                $newScript->save();
            }

            if ($this->schedule) {
                $newSchedule = $this->schedule->replicate();
                $newSchedule->webinar_id = $newWebinar->id;
                $newSchedule->save();
            }
        }

        return $newWebinar;
    }

    /**
     * Get stats.
     */
    public function getStats(): array
    {
        return [
            'registrations' => $this->registrations_count,
            'attendees' => $this->attendees_count,
            'peak_viewers' => $this->peak_viewers,
            'conversion_rate' => $this->conversion_rate,
            'duration_minutes' => $this->duration_minutes,
            'products_count' => $this->products()->count(),
            'sessions_count' => $this->sessions()->count(),
        ];
    }

    /**
     * Increment registrations count.
     */
    public function incrementRegistrations(): void
    {
        $this->increment('registrations_count');
    }

    /**
     * Increment attendees count.
     */
    public function incrementAttendees(): void
    {
        $this->increment('attendees_count');
    }

    /**
     * Update peak viewers.
     */
    public function updatePeakViewers(int $currentViewers): void
    {
        if ($currentViewers > $this->peak_viewers) {
            $this->update(['peak_viewers' => $currentViewers]);
        }
    }

    /**
     * Get type options.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_LIVE => 'Live Webinar',
            self::TYPE_AUTO => 'Auto-Webinar',
            self::TYPE_HYBRID => 'Hybrid',
        ];
    }

    /**
     * Get status options.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Szkic',
            self::STATUS_SCHEDULED => 'Zaplanowany',
            self::STATUS_LIVE => 'Na żywo',
            self::STATUS_ENDED => 'Zakończony',
            self::STATUS_PUBLISHED => 'Opublikowany',
        ];
    }
}

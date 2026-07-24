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
        'vimeo_id',
        'video_provider',
        'registration_page_id',
        'thank_you_page_id',
        'thank_you_url',
        'target_list_id',
        'clicked_list_id',
        'attended_list_id',
        'attended_min_minutes',
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
        'chat_settings' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'registrations_count' => 'integer',
        'attendees_count' => 'integer',
        'peak_viewers' => 'integer',
        'duration_minutes' => 'integer',
        'attended_min_minutes' => 'integer',
    ];

    /**
     * Default chat settings.
     */
    public const DEFAULT_CHAT_SETTINGS = [
        'enabled' => true,
        'mode' => 'open', // open, moderated, qa_only, host_only
        'slow_mode_seconds' => 0,
        'fake_viewers_base' => 50,
        'fake_viewers_variance' => 20,
        'reactions_enabled' => true,
        'allow_questions' => true,
        'require_approval' => false,
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

    public function reactions(): HasMany
    {
        return $this->hasMany(WebinarChatReaction::class);
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

    public function clickedList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class, 'clicked_list_id');
    }

    public function attendedList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class, 'attended_list_id');
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
     * Editable funnel-page copy (issue #25).
     *
     * Custom text for the registration / thank-you / watch pages is stored under
     * settings['content'][$key]. An empty or missing value falls back to
     * $fallback (normally the default translation), so the public pages render
     * correctly whether or not the host has customised them.
     */
    public function pageContent(string $key, ?string $fallback = null): ?string
    {
        $value = $this->settings['content'][$key] ?? null;

        return (is_string($value) && trim($value) !== '') ? $value : $fallback;
    }

    /**
     * Editable benefit bullet points for the registration page (issue #25).
     */
    public function benefitsList(): array
    {
        $benefits = $this->settings['benefits'] ?? [];

        if (!is_array($benefits)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($b) => is_string($b) ? trim($b) : '', $benefits),
            fn ($b) => $b !== ''
        ));
    }

    /**
     * Normalized custom content sections for a funnel page.
     *
     * Sections are stored under settings['registration_sections'] /
     * settings['thankyou_sections'] as arrays of
     * ['type' => text|video, 'title', 'body', 'video_url', 'placement'].
     * Invalid entries (unknown type, empty text, unparseable video URL) are
     * dropped so the public pages never render broken blocks.
     */
    public function pageSections(string $page): array
    {
        $sections = $this->settings[$page . '_sections'] ?? [];

        if (!is_array($sections)) {
            return [];
        }

        $normalized = [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $type = $section['type'] ?? 'text';
            $title = is_string($section['title'] ?? null) ? trim($section['title']) : '';
            $body = is_string($section['body'] ?? null) ? trim($section['body']) : '';
            $placement = ($section['placement'] ?? null) === 'below_form' ? 'below_form' : 'above_form';

            if ($type === 'video') {
                $embedUrl = self::videoEmbedUrl($section['video_url'] ?? null);
                if (!$embedUrl) {
                    continue;
                }
                $normalized[] = ['type' => 'video', 'title' => $title, 'embed_url' => $embedUrl, 'placement' => $placement];
            } elseif ($type === 'text') {
                if ($title === '' && $body === '') {
                    continue;
                }
                $normalized[] = ['type' => 'text', 'title' => $title, 'body' => $body, 'placement' => $placement];
            }
        }

        return $normalized;
    }

    public function registrationSections(?string $placement = null): array
    {
        $sections = $this->pageSections('registration');

        if ($placement === null) {
            return $sections;
        }

        return array_values(array_filter($sections, fn ($s) => $s['placement'] === $placement));
    }

    public function thankYouSections(): array
    {
        return $this->pageSections('thankyou');
    }

    /**
     * Calendly booking widget settings for the thank-you pages, or null when
     * disabled/misconfigured. Only calendly.com links are accepted so the
     * embedded iframe can never point at an arbitrary host.
     */
    public function calendlySettings(): ?array
    {
        $calendly = $this->settings['calendly'] ?? null;

        if (!is_array($calendly) || empty($calendly['enabled'])) {
            return null;
        }

        $url = is_string($calendly['url'] ?? null) ? trim($calendly['url']) : '';

        if (!preg_match('#^https://([a-z0-9-]+\.)?calendly\.com/.+#i', $url)) {
            return null;
        }

        return [
            'url' => $url,
            'title' => is_string($calendly['title'] ?? null) ? trim($calendly['title']) : '',
            'description' => is_string($calendly['description'] ?? null) ? trim($calendly['description']) : '',
        ];
    }

    /**
     * Turn a YouTube/Vimeo link (or bare video id) into an embed URL.
     *
     * The id is extracted by regex and re-assembled into a known player URL,
     * so user input can never inject an arbitrary iframe src. Bare numeric
     * ids are treated as Vimeo, other id-shaped strings as YouTube.
     */
    public static function videoEmbedUrl(?string $input): ?string
    {
        $input = trim((string) $input);

        if ($input === '') {
            return null;
        }

        if (preg_match('#vimeo\.com/(?:video/)?(\d+)(?:/([a-zA-Z0-9]+))?#i', $input, $m)) {
            $query = !empty($m[2]) ? '?h=' . $m[2] : '';
            return "https://player.vimeo.com/video/{$m[1]}{$query}";
        }

        if (preg_match('#(?:youtube\.com/(?:watch\?.*?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})#i', $input, $m)) {
            return "https://www.youtube.com/embed/{$m[1]}";
        }

        if (preg_match('/^\d{6,12}$/', $input)) {
            return "https://player.vimeo.com/video/{$input}";
        }

        if (preg_match('/^[A-Za-z0-9_-]{6,20}$/', $input)) {
            return "https://www.youtube.com/embed/{$input}";
        }

        return null;
    }

    /**
     * Public thank-you page URL (usable as a post-purchase redirect).
     */
    public function getPurchaseThankYouUrlAttribute(): string
    {
        return route('webinar.thankyou', $this->slug);
    }

    /**
     * Get effective timezone (webinar's own or inherited from user).
     */
    public function getEffectiveTimezoneAttribute(): string
    {
        return $this->timezone ?? $this->user->timezone ?? 'UTC';
    }

    /**
     * Check if timezone is overridden (not inherited from user).
     */
    public function hasCustomTimezone(): bool
    {
        return !empty($this->timezone) && $this->timezone !== ($this->user->timezone ?? 'UTC');
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
        return $this->chat_settings_with_defaults['enabled'] ?? true;
    }

    /**
     * Get chat settings with defaults merged.
     */
    public function getChatSettingsWithDefaultsAttribute(): array
    {
        return array_merge(self::DEFAULT_CHAT_SETTINGS, $this->chat_settings ?? []);
    }

    /**
     * Update chat settings.
     */
    public function updateChatSettings(array $settings): void
    {
        $current = $this->chat_settings ?? [];
        $this->chat_settings = array_merge($current, $settings);
        $this->save();
    }

    /**
     * Get current viewers count (real + fake for social proof).
     */
    public function getCurrentViewersCount(?int $realViewers = null): int
    {
        $settings = $this->chat_settings_with_defaults;
        $base = $settings['fake_viewers_base'] ?? 0;
        $variance = $settings['fake_viewers_variance'] ?? 0;

        $fakeViewers = $base + rand(-$variance, $variance);

        return max(0, $fakeViewers + ($realViewers ?? 0));
    }

    /**
     * Check if reactions are enabled.
     */
    public function areReactionsEnabled(): bool
    {
        return $this->chat_settings_with_defaults['reactions_enabled'] ?? true;
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

    /**
     * Build the Vimeo player embed URL from the stored vimeo_id.
     *
     * Accepts a plain numeric id ("123456789") or the unlisted-video form
     * "123456789/abcdef123" (id + privacy hash), which Vimeo embeds via ?h=.
     */
    public function vimeoEmbedUrl(array $params = []): ?string
    {
        if (empty($this->vimeo_id)) {
            return null;
        }

        [$id, $hash] = array_pad(explode('/', trim($this->vimeo_id), 2), 2, null);
        $id = preg_replace('/[^0-9]/', '', $id);

        if ($id === '') {
            return null;
        }

        if ($hash) {
            $params = ['h' => $hash] + $params;
        }

        $query = $params ? '?' . http_build_query($params) : '';

        return "https://player.vimeo.com/video/{$id}{$query}";
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

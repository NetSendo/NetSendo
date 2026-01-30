<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class CalendlyIntegration extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'calendly_user_uri',
        'calendly_organization_uri',
        'calendly_user_email',
        'calendly_user_name',
        'webhook_id',
        'webhook_signing_key',
        'is_active',
        'settings',
        'event_types',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'settings' => 'array',
        'event_types' => 'array',
    ];

    /**
     * Hidden attributes (sensitive data)
     */
    protected $hidden = [
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'webhook_signing_key',
    ];

    /**
     * Get the user that owns this integration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all events from this integration.
     */
    public function events(): HasMany
    {
        return $this->hasMany(CalendlyEvent::class);
    }

    /**
     * Encrypt client_id before saving.
     */
    public function setClientIdAttribute($value): void
    {
        $this->attributes['client_id'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt client_id when retrieving.
     */
    public function getClientIdAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Encrypt client_secret before saving.
     */
    public function setClientSecretAttribute($value): void
    {
        $this->attributes['client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt client_secret when retrieving.
     */
    public function getClientSecretAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Encrypt access token before saving.
     */
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt access token when retrieving.
     */
    public function getAccessTokenAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Encrypt refresh token before saving.
     */
    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt refresh token when retrieving.
     */
    public function getRefreshTokenAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if the access token is expired or about to expire.
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        // Consider token expired if it expires within 5 minutes
        return $this->token_expires_at->subMinutes(5)->isPast();
    }

    /**
     * Get default settings structure.
     */
    public static function getDefaultSettings(): array
    {
        return [
            // CRM settings
            'crm' => [
                'enabled' => true,
                'default_status' => 'lead',
                'create_tasks' => true,
                'default_owner_id' => null,
            ],
            // Mailing list settings
            'mailing_lists' => [
                'enabled' => true,
                'default_list_ids' => [],
                'default_tag_ids' => [],
            ],
            // Event type specific mappings
            'event_type_mappings' => [
                // 'event_type_uri' => [
                //     'list_ids' => [],
                //     'tag_ids' => [],
                //     'crm_enabled' => true,
                // ]
            ],
            // Automation triggers
            'automation' => [
                'trigger_on_booking' => true,
                'trigger_on_cancellation' => true,
                'trigger_on_no_show' => false,
            ],
        ];
    }

    /**
     * Get setting value with dot notation support.
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings ?? self::getDefaultSettings();
        return data_get($settings, $key, $default);
    }

    /**
     * Update a specific setting.
     */
    public function updateSetting(string $key, $value): void
    {
        $settings = $this->settings ?? self::getDefaultSettings();
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /**
     * Get list IDs for a specific event type.
     */
    public function getListIdsForEventType(string $eventTypeUri): array
    {
        $mapping = $this->getSetting("event_type_mappings.{$eventTypeUri}");

        if ($mapping && isset($mapping['list_ids'])) {
            return $mapping['list_ids'];
        }

        return $this->getSetting('mailing_lists.default_list_ids', []);
    }

    /**
     * Get tag IDs for a specific event type.
     */
    public function getTagIdsForEventType(string $eventTypeUri): array
    {
        $mapping = $this->getSetting("event_type_mappings.{$eventTypeUri}");

        if ($mapping && isset($mapping['tag_ids'])) {
            return $mapping['tag_ids'];
        }

        return $this->getSetting('mailing_lists.default_tag_ids', []);
    }

    /**
     * Check if CRM integration is enabled for an event type.
     */
    public function isCrmEnabledForEventType(string $eventTypeUri): bool
    {
        $mapping = $this->getSetting("event_type_mappings.{$eventTypeUri}");

        if ($mapping && isset($mapping['crm_enabled'])) {
            return $mapping['crm_enabled'];
        }

        return $this->getSetting('crm.enabled', true);
    }

    /**
     * Scope for active integrations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

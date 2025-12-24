<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use App\Traits\LogsActivity;

class SmsProvider extends Model
{
    use LogsActivity;

    // Provider type constants
    public const PROVIDER_TWILIO = 'twilio';
    public const PROVIDER_SMSAPI = 'smsapi';
    public const PROVIDER_SMSAPI_COM = 'smsapi_com';
    public const PROVIDER_VONAGE = 'vonage';
    public const PROVIDER_MESSAGEBIRD = 'messagebird';
    public const PROVIDER_PLIVO = 'plivo';

    protected $fillable = [
        'user_id',
        'name',
        'provider',
        'credentials',
        'from_number',
        'from_name',
        'is_active',
        'is_default',
        'daily_limit',
        'sent_today',
        'sent_today_date',
        'last_tested_at',
        'last_test_status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'daily_limit' => 'integer',
        'sent_today' => 'integer',
        'sent_today_date' => 'date',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = [
        'credentials',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Credential Management
    |--------------------------------------------------------------------------
    */

    /**
     * Get decrypted credentials.
     */
    public function getDecryptedCredentials(): array
    {
        if (!$this->credentials) {
            return [];
        }

        try {
            $decrypted = Crypt::decryptString($this->credentials);
            return json_decode($decrypted, true) ?? [];
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt SMS provider credentials', [
                'provider_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Set and encrypt credentials.
     */
    public function setCredentials(array $credentials): void
    {
        $this->credentials = Crypt::encryptString(json_encode($credentials));
    }

    /*
    |--------------------------------------------------------------------------
    | Limit Management
    |--------------------------------------------------------------------------
    */

    /**
     * Check if the daily limit has been reached.
     */
    public function hasReachedDailyLimit(): bool
    {
        if ($this->daily_limit === null) {
            return false;
        }

        $this->resetDailyCounterIfNeeded();

        return $this->sent_today >= $this->daily_limit;
    }

    /**
     * Increment the sent counter.
     */
    public function incrementSentCount(int $count = 1): void
    {
        $this->resetDailyCounterIfNeeded();

        $this->increment('sent_today', $count);
    }

    /**
     * Reset daily counter if date has changed.
     */
    private function resetDailyCounterIfNeeded(): void
    {
        $today = now()->toDateString();

        if ($this->sent_today_date === null || $this->sent_today_date->toDateString() !== $today) {
            $this->update([
                'sent_today' => 0,
                'sent_today_date' => $today,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Static Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Get the default provider for a user.
     */
    public static function getDefaultFor(int $userId): ?self
    {
        return static::forUser($userId)
            ->where('is_default', true)
            ->active()
            ->first();
    }

    /**
     * Set this provider as default for the user.
     */
    public function setAsDefault(): void
    {
        // Unset any existing default
        static::forUser($this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Update test status.
     */
    public function updateTestStatus(bool $success): void
    {
        $this->update([
            'last_tested_at' => now(),
            'last_test_status' => $success ? 'success' : 'failed',
        ]);
    }

    /**
     * Get available provider types.
     */
    public static function getProviderTypes(): array
    {
        return [
            self::PROVIDER_TWILIO => 'Twilio',
            self::PROVIDER_SMSAPI => 'SMS API (PL)',
            self::PROVIDER_SMSAPI_COM => 'SMS API (COM)',
            self::PROVIDER_VONAGE => 'Vonage (Nexmo)',
            self::PROVIDER_MESSAGEBIRD => 'MessageBird',
            self::PROVIDER_PLIVO => 'Plivo',
        ];
    }
}

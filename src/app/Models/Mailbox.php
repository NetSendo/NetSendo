<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Mailbox extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'provider',
        'from_email',
        'from_name',
        'is_default',
        'is_active',
        'allowed_types',
        'reply_to',
        'time_restriction',
        'credentials',
        'daily_limit',
        'sent_today',
        'sent_today_date',
        'last_tested_at',
        'last_test_success',
        'last_test_message',
        'google_integration_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'allowed_types' => 'array',
        'daily_limit' => 'integer',
        'sent_today' => 'integer',
        'sent_today_date' => 'date',
        'last_tested_at' => 'datetime',
        'last_test_success' => 'boolean',
    ];

    protected $hidden = [
        'credentials',
    ];

    /**
     * Provider constants
     */
    public const PROVIDER_SMTP = 'smtp';
    public const PROVIDER_SENDGRID = 'sendgrid';
    public const PROVIDER_GMAIL = 'gmail';

    /**
     * Message type constants
     */
    public const TYPE_BROADCAST = 'broadcast';
    public const TYPE_AUTORESPONDER = 'autoresponder';
    public const TYPE_SYSTEM = 'system';

    /**
     * Get available providers with labels
     */
    public static function getProviders(): array
    {
        return [
            self::PROVIDER_SMTP => [
                'label' => 'SMTP',
                'icon' => 'server',
                'description' => 'Custom SMTP server',
            ],
            self::PROVIDER_SENDGRID => [
                'label' => 'SendGrid',
                'icon' => 'cloud',
                'description' => 'SendGrid API',
            ],
            self::PROVIDER_GMAIL => [
                'label' => 'Gmail',
                'icon' => 'mail',
                'description' => 'Gmail (OAuth 2.0)',
            ],
        ];
    }

    /**
     * Get default allowed types for a provider
     */
    public static function getDefaultAllowedTypes(string $provider): array
    {
        if ($provider === self::PROVIDER_GMAIL) {
            return [
                self::TYPE_AUTORESPONDER,
                self::TYPE_SYSTEM,
            ];
        }

        return [
            self::TYPE_BROADCAST,
            self::TYPE_AUTORESPONDER,
            self::TYPE_SYSTEM,
        ];
    }

    /**
     * Relationship: User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function googleIntegration()
    {
        return $this->belongsTo(GoogleIntegration::class, 'google_integration_id');
    }

    /**
     * Relationship: Messages using this mailbox
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Set credentials (auto-encrypt)
     */
    public function setCredentialsAttribute($value): void
    {
        $this->attributes['credentials'] = Crypt::encryptString(
            is_array($value) ? json_encode($value) : $value
        );
    }

    /**
     * Get decrypted credentials as array
     */
    public function getDecryptedCredentials(): array
    {
        try {
            return json_decode(Crypt::decryptString($this->attributes['credentials']), true) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if this mailbox can send a specific message type
     */
    public function canSendType(string $type): bool
    {
        return in_array($type, $this->allowed_types ?? []);
    }

    /**
     * Check if mailbox has reached daily limit
     */
    public function hasReachedDailyLimit(): bool
    {
        if ($this->daily_limit === null) {
            return false; // No limit
        }

        // Reset counter if it's a new day
        if ($this->sent_today_date === null || !$this->sent_today_date->isToday()) {
            return false;
        }

        return $this->sent_today >= $this->daily_limit;
    }

    /**
     * Increment the sent counter for today
     */
    public function incrementSentCount(): void
    {
        $today = now()->toDateString();

        if ($this->sent_today_date === null || !$this->sent_today_date->isToday()) {
            // New day, reset counter
            $this->sent_today = 1;
            $this->sent_today_date = $today;
        } else {
            $this->sent_today++;
        }

        $this->save();
    }

    /**
     * Update test result
     */
    public function updateTestResult(bool $success, string $message = ''): void
    {
        $this->update([
            'last_tested_at' => now(),
            'last_test_success' => $success,
            'last_test_message' => $message,
        ]);
    }

    /**
     * Set this mailbox as default for the user
     */
    public function setAsDefault(): void
    {
        // Remove default from other mailboxes of this user
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Scope: Active mailboxes only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: For specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: That can send a specific type
     */
    public function scopeCanSend($query, string $type)
    {
        return $query->whereJsonContains('allowed_types', $type);
    }

    /**
     * Get the default mailbox for a user (or create fallback)
     */
    public static function getDefaultFor(int $userId): ?self
    {
        return static::forUser($userId)
            ->active()
            ->where('is_default', true)
            ->first()
            ?? static::forUser($userId)->active()->first();
    }
}

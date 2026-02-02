<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class DedicatedIpAddress extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ip_pool_id',
        'domain_configuration_id',
        'ip_address',
        'hostname',
        'ip_version',
        'provider',
        'provider_id',
        'provider_region',
        'ptr_record',
        'ptr_verified',
        'ptr_verified_at',
        'warming_status',
        'warming_started_at',
        'warming_completed_at',
        'warming_day',
        'warming_daily_limit',
        'total_sent',
        'total_delivered',
        'total_bounced',
        'total_complaints',
        'sent_today',
        'sent_today_date',
        'reputation_score',
        'blacklist_status',
        'blacklist_checked_at',
        'dkim_selector',
        'dkim_private_key',
        'dkim_public_key',
        'dkim_generated_at',
        'dkim_rotated_at',
        'is_active',
        'status_message',
    ];

    protected $casts = [
        'ptr_verified' => 'boolean',
        'ptr_verified_at' => 'datetime',
        'warming_started_at' => 'datetime',
        'warming_completed_at' => 'datetime',
        'warming_day' => 'integer',
        'warming_daily_limit' => 'integer',
        'total_sent' => 'integer',
        'total_delivered' => 'integer',
        'total_bounced' => 'integer',
        'total_complaints' => 'integer',
        'sent_today' => 'integer',
        'sent_today_date' => 'date',
        'reputation_score' => 'decimal:2',
        'blacklist_status' => 'array',
        'blacklist_checked_at' => 'datetime',
        'dkim_generated_at' => 'datetime',
        'dkim_rotated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'dkim_private_key',
    ];

    /**
     * Warming status constants
     */
    public const WARMING_NEW = 'new';
    public const WARMING_WARMING = 'warming';
    public const WARMING_WARMED = 'warmed';
    public const WARMING_PAUSED = 'paused';

    /**
     * IP providers
     */
    public const PROVIDER_VULTR = 'vultr';
    public const PROVIDER_LINODE = 'linode';
    public const PROVIDER_DIGITALOCEAN = 'digitalocean';
    public const PROVIDER_MANUAL = 'manual';

    /**
     * Blacklist services to check
     */
    public const BLACKLISTS = [
        'spamhaus' => 'zen.spamhaus.org',
        'spamcop' => 'bl.spamcop.net',
        'barracuda' => 'b.barracudacentral.org',
        'sorbs' => 'dnsbl.sorbs.net',
        'uceprotect' => 'dnsbl-1.uceprotect.net',
    ];

    /**
     * Relationships
     */
    public function pool()
    {
        return $this->belongsTo(IpPool::class, 'ip_pool_id');
    }

    public function domainConfiguration()
    {
        return $this->belongsTo(DomainConfiguration::class);
    }

    public function mailboxes()
    {
        return $this->hasMany(Mailbox::class, 'dedicated_ip_id');
    }

    /**
     * Set DKIM private key (auto-encrypt)
     */
    public function setDkimPrivateKeyAttribute($value): void
    {
        $this->attributes['dkim_private_key'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Get decrypted DKIM private key
     */
    public function getDecryptedDkimPrivateKey(): ?string
    {
        if (!$this->dkim_private_key) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['dkim_private_key']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calculate delivery rate
     */
    public function getDeliveryRate(): float
    {
        if ($this->total_sent === 0) {
            return 100.0;
        }

        return round(($this->total_delivered / $this->total_sent) * 100, 2);
    }

    /**
     * Calculate bounce rate
     */
    public function getBounceRate(): float
    {
        if ($this->total_sent === 0) {
            return 0.0;
        }

        return round(($this->total_bounced / $this->total_sent) * 100, 2);
    }

    /**
     * Calculate complaint rate
     */
    public function getComplaintRate(): float
    {
        if ($this->total_sent === 0) {
            return 0.0;
        }

        return round(($this->total_complaints / $this->total_sent) * 100, 4);
    }

    /**
     * Check if IP is on any blacklist
     */
    public function isBlacklisted(): bool
    {
        if (!$this->blacklist_status) {
            return false;
        }

        return in_array(true, $this->blacklist_status, true);
    }

    /**
     * Get current warming daily limit
     */
    public function getCurrentWarmingLimit(): int
    {
        if ($this->warming_status !== self::WARMING_WARMING) {
            return PHP_INT_MAX; // No limit for warmed IPs
        }

        $schedule = $this->pool?->getWarmingScheduleWithDefaults() ?? IpPool::DEFAULT_WARMING_SCHEDULE;
        $day = $this->warming_day;

        // If day exceeds schedule, use last day's limit
        $maxDay = max(array_keys($schedule));
        if ($day > $maxDay) {
            return $schedule[$maxDay];
        }

        return $schedule[$day] ?? 50;
    }

    /**
     * Can send more emails today
     */
    public function canSendMore(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Reset counter if new day
        if (!$this->sent_today_date?->isToday()) {
            return true;
        }

        return $this->sent_today < $this->getCurrentWarmingLimit();
    }

    /**
     * Increment sent counter
     */
    public function incrementSentCount(bool $delivered = true): void
    {
        $today = now()->toDateString();

        if (!$this->sent_today_date?->isToday()) {
            $this->sent_today = 1;
            $this->sent_today_date = $today;

            // Advance warming day
            if ($this->warming_status === self::WARMING_WARMING) {
                $this->warming_day++;
            }
        } else {
            $this->sent_today++;
        }

        $this->total_sent++;
        if ($delivered) {
            $this->total_delivered++;
        }

        $this->save();
    }

    /**
     * Record a bounce
     */
    public function recordBounce(): void
    {
        $this->total_bounced++;
        $this->updateReputationScore();
        $this->save();
    }

    /**
     * Record a complaint
     */
    public function recordComplaint(): void
    {
        $this->total_complaints++;
        $this->updateReputationScore();
        $this->save();
    }

    /**
     * Update reputation score based on metrics
     */
    protected function updateReputationScore(): void
    {
        $bounceRate = $this->getBounceRate();
        $complaintRate = $this->getComplaintRate();

        // Reputation formula:
        // Start at 100, subtract penalties
        $score = 100.0;

        // Bounce rate penalty (up to -30 points)
        if ($bounceRate > 2) {
            $score -= min(30, ($bounceRate - 2) * 5);
        }

        // Complaint rate penalty (up to -50 points)
        // Industry standard: should be under 0.1%
        if ($complaintRate > 0.1) {
            $score -= min(50, ($complaintRate - 0.1) * 500);
        }

        // Blacklist penalty (-20 points)
        if ($this->isBlacklisted()) {
            $score -= 20;
        }

        $this->reputation_score = max(0, $score);
    }

    /**
     * Start IP warming
     */
    public function startWarming(): void
    {
        $this->warming_status = self::WARMING_WARMING;
        $this->warming_started_at = now();
        $this->warming_day = 1;
        $this->save();
    }

    /**
     * Complete IP warming
     */
    public function completeWarming(): void
    {
        $this->warming_status = self::WARMING_WARMED;
        $this->warming_completed_at = now();
        $this->save();
    }

    /**
     * Pause IP warming
     */
    public function pauseWarming(): void
    {
        $this->warming_status = self::WARMING_PAUSED;
        $this->save();
    }

    /**
     * Resume IP warming
     */
    public function resumeWarming(): void
    {
        $this->warming_status = self::WARMING_WARMING;
        $this->save();
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWarmed($query)
    {
        return $query->where('warming_status', self::WARMING_WARMED);
    }

    public function scopeWarming($query)
    {
        return $query->where('warming_status', self::WARMING_WARMING);
    }

    public function scopeForPool($query, int $poolId)
    {
        return $query->where('ip_pool_id', $poolId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('domain_configuration_id');
    }

    public function scopeHealthy($query)
    {
        return $query->where('reputation_score', '>=', 70);
    }
}

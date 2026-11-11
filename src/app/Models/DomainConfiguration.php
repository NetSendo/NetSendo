<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class DomainConfiguration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'domain',
        'cname_selector',
        'cname_verified',
        'cname_verified_at',
        'spf_status',
        'dkim_status',
        'dkim_selector',
        'dmarc_status',
        'dmarc_policy',
        'dmarc_upgraded_at',
        'overall_status',
        'last_check_at',
        'next_check_at',
        'consecutive_failures',
        'dns_records',
        'check_history',
        'alerts_enabled',
        'last_alert_at',
        'mailbox_id',
        // NetSendo Mail Infrastructure: the domain's own login to the MTA
        'dedicated_ip_id',
        'nmi_enabled',
        'nmi_enabled_at',
        'nmi_smtp_username',
        'nmi_smtp_password',
        'nmi_credentials_rotated_at',
    ];

    protected $hidden = [
        'nmi_smtp_password',
    ];

    protected $casts = [
        'cname_verified' => 'boolean',
        'cname_verified_at' => 'datetime',
        'dmarc_upgraded_at' => 'datetime',
        'last_check_at' => 'datetime',
        'next_check_at' => 'datetime',
        'consecutive_failures' => 'integer',
        'dns_records' => 'array',
        'check_history' => 'array',
        'alerts_enabled' => 'boolean',
        'last_alert_at' => 'datetime',
        'nmi_enabled' => 'boolean',
        'nmi_enabled_at' => 'datetime',
        'nmi_credentials_rotated_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_VALID = 'valid';
    public const STATUS_WARNING = 'warning';
    public const STATUS_CRITICAL = 'critical';

    public const OVERALL_PENDING = 'pending';
    public const OVERALL_SAFE = 'safe';
    public const OVERALL_WARNING = 'warning';
    public const OVERALL_CRITICAL = 'critical';

    public const DMARC_NONE = 'none';
    public const DMARC_QUARANTINE = 'quarantine';
    public const DMARC_REJECT = 'reject';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->cname_selector)) {
                $model->cname_selector = 'ns-' . Str::random(12);
            }
        });

        // A removed domain must not keep its login to the NetSendo MTA (and gets it
        // back when restored): rewrite the MTA's login file
        $syncMtaLogins = function ($model) {
            // The instance may predate the login (nmi_enabled still false in memory):
            // where the MTA is switched on, rewrite the file on every removal
            if (! $model->nmi_enabled && ! Mailbox::nmiAvailable()) {
                return;
            }

            try {
                app(\App\Services\Nmi\MtaCredentialService::class)->sync();
            } catch (\RuntimeException $e) {
                report($e);
            }
        };

        static::deleted($syncMtaLogins);
        static::restored($syncMtaLogins);
    }

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mailbox()
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function simulations()
    {
        return $this->hasMany(InboxSimulation::class);
    }

    /**
     * Mailboxes of the "NetSendo MTA" provider that send as this domain
     * (mailboxes.domain_configuration_id; `mailbox()` above is the Deliverability link).
     */
    public function nmiMailboxes()
    {
        return $this->hasMany(Mailbox::class, 'domain_configuration_id');
    }

    /**
     * Set the NMI SMTP password (auto-encrypt)
     */
    public function setNmiSmtpPasswordAttribute($value): void
    {
        $this->attributes['nmi_smtp_password'] = filled($value) ? Crypt::encryptString($value) : null;
    }

    /**
     * The domain's NMI SMTP password in clear text, null when none was issued
     */
    public function getDecryptedNmiPassword(): ?string
    {
        if (empty($this->attributes['nmi_smtp_password'])) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['nmi_smtp_password']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Whether the domain has a login to the NetSendo MTA
     */
    public function hasNmiCredentials(): bool
    {
        return filled($this->nmi_smtp_username) && $this->getDecryptedNmiPassword() !== null;
    }

    /**
     * Get CNAME instruction for the user
     */
    public function getCnameInstruction(): array
    {
        return [
            'record_type' => 'CNAME',
            'host' => '_netsendo.' . $this->domain,
            'value' => $this->cname_selector . '.' . parse_url(config('app.url'), PHP_URL_HOST),
            'ttl' => 3600,
        ];
    }

    /**
     * Get user-friendly status information
     */
    public function getStatusInfo(): array
    {
        $statusLabels = [
            self::OVERALL_PENDING => [
                'color' => 'yellow',
                'icon' => 'clock',
                'label_key' => 'deliverability.status.pending',
            ],
            self::OVERALL_SAFE => [
                'color' => 'green',
                'icon' => 'check-circle',
                'label_key' => 'deliverability.status.safe',
            ],
            self::OVERALL_WARNING => [
                'color' => 'yellow',
                'icon' => 'exclamation-triangle',
                'label_key' => 'deliverability.status.warning',
            ],
            self::OVERALL_CRITICAL => [
                'color' => 'red',
                'icon' => 'x-circle',
                'label_key' => 'deliverability.status.critical',
            ],
        ];

        return $statusLabels[$this->overall_status] ?? $statusLabels[self::OVERALL_PENDING];
    }

    /**
     * Calculate overall status from individual statuses
     */
    public function calculateOverallStatus(): string
    {
        // If not verified, always pending
        if (!$this->cname_verified) {
            return self::OVERALL_PENDING;
        }

        $statuses = [$this->spf_status, $this->dkim_status, $this->dmarc_status];

        // Any critical = overall critical
        if (in_array(self::STATUS_CRITICAL, $statuses)) {
            return self::OVERALL_CRITICAL;
        }

        // Any warning = overall warning
        if (in_array(self::STATUS_WARNING, $statuses)) {
            return self::OVERALL_WARNING;
        }

        // Any pending = overall pending
        if (in_array(self::STATUS_PENDING, $statuses)) {
            return self::OVERALL_PENDING;
        }

        // All valid = safe
        return self::OVERALL_SAFE;
    }

    /**
     * Update overall status and save
     */
    public function recalculateStatus(): void
    {
        $this->overall_status = $this->calculateOverallStatus();
        $this->save();
    }

    /**
     * Check if domain needs DNS check
     */
    public function needsCheck(): bool
    {
        if ($this->next_check_at === null) {
            return true;
        }

        return $this->next_check_at->isPast();
    }

    /**
     * Schedule next check
     */
    public function scheduleNextCheck(): void
    {
        // Verified domains: check every 6 hours
        // Unverified domains: check every 1 hour
        $hours = $this->cname_verified ? 6 : 1;

        $this->next_check_at = now()->addHours($hours);
        $this->save();
    }

    /**
     * Add check result to history
     */
    public function addCheckToHistory(array $result): void
    {
        $history = $this->check_history ?? [];

        // Keep last 10 checks
        array_unshift($history, [
            'timestamp' => now()->toIso8601String(),
            'result' => $result,
        ]);

        $this->check_history = array_slice($history, 0, 10);
        $this->save();
    }

    /**
     * Scopes
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeVerified($query)
    {
        return $query->where('cname_verified', true);
    }

    public function scopeNeedsCheck($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('next_check_at')
              ->orWhere('next_check_at', '<=', now());
        });
    }

    public function scopeCritical($query)
    {
        return $query->where('overall_status', self::OVERALL_CRITICAL);
    }

    public function scopeWithWarnings($query)
    {
        return $query->whereIn('overall_status', [self::OVERALL_WARNING, self::OVERALL_CRITICAL]);
    }

    /**
     * Get domains that are ready for DMARC upgrade
     */
    public function scopeReadyForDmarcUpgrade($query)
    {
        return $query->verified()
            ->where('overall_status', self::OVERALL_SAFE)
            ->where('dmarc_policy', '!=', self::DMARC_REJECT)
            ->where(function ($q) {
                $q->whereNull('dmarc_upgraded_at')
                  ->orWhere('dmarc_upgraded_at', '<=', now()->subDays(7));
            });
    }
}

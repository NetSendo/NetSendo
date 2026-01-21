<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Affiliate extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'user_id',
        'parent_affiliate_id',
        'email',
        'name',
        'password',
        'status',
        'company_name',
        'country',
        'website',
        'payout_method',
        'payout_details',
        'referral_code',
        'joined_at',
        'approved_at',
        'last_login_at',
        'meta',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'payout_details' => 'array',
        'meta' => 'array',
        'joined_at' => 'datetime',
        'approved_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($affiliate) {
            if (empty($affiliate->referral_code)) {
                $affiliate->referral_code = strtoupper(Str::random(8));
            }
            if (empty($affiliate->joined_at)) {
                $affiliate->joined_at = now();
            }
        });
    }

    // Relationships

    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'parent_affiliate_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Affiliate::class, 'parent_affiliate_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(AffiliateLink::class, 'affiliate_id');
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(AffiliateCoupon::class, 'affiliate_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class, 'affiliate_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'affiliate_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class, 'affiliate_id');
    }

    // Scopes

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForProgram($query, $programId)
    {
        return $query->where('program_id', $programId);
    }

    // Accessors

    public function getTotalClicksAttribute(): int
    {
        return $this->clicks()->count();
    }

    public function getTotalLeadsAttribute(): int
    {
        return $this->conversions()->where('type', 'lead')->count();
    }

    public function getTotalSalesAttribute(): int
    {
        return $this->conversions()->where('type', 'purchase')->count();
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->conversions()->where('type', 'purchase')->sum('amount');
    }

    public function getTotalEarningsAttribute(): float
    {
        return $this->commissions()->sum('commission_amount');
    }

    public function getPendingEarningsAttribute(): float
    {
        return $this->commissions()
            ->whereIn('status', ['pending', 'approved'])
            ->sum('commission_amount');
    }

    public function getPayableEarningsAttribute(): float
    {
        return $this->commissions()
            ->where('status', 'payable')
            ->sum('commission_amount');
    }

    public function getPaidEarningsAttribute(): float
    {
        return $this->commissions()
            ->where('status', 'paid')
            ->sum('commission_amount');
    }

    public function getEpcAttribute(): float
    {
        $clicks = $this->total_clicks;
        if ($clicks === 0) {
            return 0;
        }
        return round($this->total_revenue / $clicks, 2);
    }

    public function getConversionRateAttribute(): float
    {
        $clicks = $this->total_clicks;
        if ($clicks === 0) {
            return 0;
        }
        return round(($this->total_sales / $clicks) * 100, 2);
    }

    // Helper Methods

    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function block(): void
    {
        $this->update([
            'status' => 'blocked',
        ]);
    }

    /**
     * Get or create link for a specific offer.
     */
    public function getLinkForOffer(AffiliateOffer $offer): AffiliateLink
    {
        return $this->links()->firstOrCreate(
            ['offer_id' => $offer->id],
            ['code' => strtoupper(Str::random(10))]
        );
    }
}

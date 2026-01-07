<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AffiliateProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'status',
        'terms_text',
        'terms_url',
        'cookie_days',
        'attribution_model',
        'currency',
        'default_commission_percent',
        'default_commission_fixed',
        'auto_approve_affiliates',
        'max_levels',
        'settings',
    ];

    protected $casts = [
        'cookie_days' => 'integer',
        'default_commission_percent' => 'decimal:2',
        'default_commission_fixed' => 'decimal:2',
        'auto_approve_affiliates' => 'boolean',
        'max_levels' => 'integer',
        'settings' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($program) {
            if (empty($program->slug)) {
                $program->slug = Str::slug($program->name) . '-' . Str::random(6);
            }
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(AffiliateOffer::class, 'program_id');
    }

    public function affiliates(): HasMany
    {
        return $this->hasMany(Affiliate::class, 'program_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class, 'program_id');
    }

    public function levelRules(): HasMany
    {
        return $this->hasMany(AffiliateLevelRule::class, 'program_id');
    }

    public function attributionRule()
    {
        return $this->hasOne(AffiliateAttributionRule::class, 'program_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Accessors

    public function getActiveAffiliatesCountAttribute(): int
    {
        return $this->affiliates()->where('status', 'approved')->count();
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->offers()
            ->join('affiliate_conversions', 'affiliate_offers.id', '=', 'affiliate_conversions.offer_id')
            ->where('affiliate_conversions.type', 'purchase')
            ->sum('affiliate_conversions.amount');
    }

    public function getTotalCommissionsAttribute(): float
    {
        return $this->offers()
            ->join('affiliate_commissions', 'affiliate_offers.id', '=', 'affiliate_commissions.offer_id')
            ->sum('affiliate_commissions.commission_amount');
    }
}

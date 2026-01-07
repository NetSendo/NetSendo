<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AffiliateLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'offer_id',
        'code',
        'utm_defaults',
        'custom_slug',
        'clicks_count',
    ];

    protected $casts = [
        'utm_defaults' => 'array',
        'clicks_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($link) {
            if (empty($link->code)) {
                $link->code = strtoupper(Str::random(10));
            }
        });
    }

    // Relationships

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'offer_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class, 'link_id');
    }

    // Accessors

    public function getTrackingUrlAttribute(): string
    {
        $baseUrl = $this->offer->offer_url;
        if (!$baseUrl) {
            return route('affiliate.redirect', ['code' => $this->code]);
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $separator . 'ref=' . $this->code;
    }

    public function getRedirectUrlAttribute(): string
    {
        return route('affiliate.redirect', ['code' => $this->code]);
    }

    public function incrementClicks(): void
    {
        $this->increment('clicks_count');
    }
}

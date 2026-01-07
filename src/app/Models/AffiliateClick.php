<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateClick extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'link_id',
        'affiliate_id',
        'offer_id',
        'ip_hash',
        'ua_hash',
        'referrer',
        'landing_url',
        'session_id',
        'cookie_id',
        'utm_data',
        'is_unique',
        'created_at',
    ];

    protected $casts = [
        'utm_data' => 'array',
        'is_unique' => 'boolean',
        'created_at' => 'datetime',
    ];

    // Relationships

    public function link(): BelongsTo
    {
        return $this->belongsTo(AffiliateLink::class, 'link_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(AffiliateOffer::class, 'offer_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class, 'click_id');
    }

    public function fraudFlags(): HasMany
    {
        return $this->hasMany(AffiliateFraudFlag::class, 'click_id');
    }

    // Static Methods

    public static function recordClick(
        int $linkId,
        int $affiliateId,
        int $offerId,
        string $ipHash,
        string $uaHash,
        ?string $referrer,
        ?string $landingUrl,
        ?string $sessionId,
        ?string $cookieId,
        ?array $utmData = null
    ): self {
        // Check if this is a unique click (not seen this IP + UA combo recently)
        $isUnique = !self::where('ip_hash', $ipHash)
            ->where('ua_hash', $uaHash)
            ->where('affiliate_id', $affiliateId)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        return self::create([
            'link_id' => $linkId,
            'affiliate_id' => $affiliateId,
            'offer_id' => $offerId,
            'ip_hash' => $ipHash,
            'ua_hash' => $uaHash,
            'referrer' => $referrer ? substr($referrer, 0, 500) : null,
            'landing_url' => $landingUrl ? substr($landingUrl, 0, 500) : null,
            'session_id' => $sessionId,
            'cookie_id' => $cookieId,
            'utm_data' => $utmData,
            'is_unique' => $isUnique,
            'created_at' => now(),
        ]);
    }
}

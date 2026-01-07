<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateFraudFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'click_id',
        'conversion_id',
        'type',
        'reason',
        'severity',
        'is_reviewed',
        'reviewed_at',
        'reviewed_by',
        'meta',
    ];

    protected $casts = [
        'severity' => 'integer',
        'is_reviewed' => 'boolean',
        'reviewed_at' => 'datetime',
        'meta' => 'array',
    ];

    // Relationships

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function click(): BelongsTo
    {
        return $this->belongsTo(AffiliateClick::class, 'click_id');
    }

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(AffiliateConversion::class, 'conversion_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes

    public function scopeUnreviewed($query)
    {
        return $query->where('is_reviewed', false);
    }

    public function scopeHighSeverity($query, $minSeverity = 7)
    {
        return $query->where('severity', '>=', $minSeverity);
    }

    // Methods

    public function markAsReviewed(int $userId): void
    {
        $this->update([
            'is_reviewed' => true,
            'reviewed_at' => now(),
            'reviewed_by' => $userId,
        ]);
    }

    // Static Methods

    public static function flag(
        int $affiliateId,
        string $type,
        string $reason,
        int $severity = 5,
        ?int $clickId = null,
        ?int $conversionId = null,
        ?array $meta = null
    ): self {
        return self::create([
            'affiliate_id' => $affiliateId,
            'click_id' => $clickId,
            'conversion_id' => $conversionId,
            'type' => $type,
            'reason' => $reason,
            'severity' => $severity,
            'meta' => $meta,
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliatePayoutItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payout_id',
        'commission_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // Relationships

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'payout_id');
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(AffiliateCommission::class, 'commission_id');
    }
}

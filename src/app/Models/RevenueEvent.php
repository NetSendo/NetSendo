<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Brain 2.0 — unified revenue event (one completed payment/conversion).
 *
 * Amounts are stored in MINOR units (grosze/cents); use amount_major for
 * display. Populated by RevenueEventService (gateway sync) and by the
 * WooCommerce/purchase webhooks at intake time.
 */
class RevenueEvent extends Model
{
    public const SOURCE_STRIPE = 'stripe';
    public const SOURCE_POLAR = 'polar';
    public const SOURCE_TPAY = 'tpay';
    public const SOURCE_WOOCOMMERCE = 'woocommerce';
    public const SOURCE_PURCHASE_WEBHOOK = 'purchase_webhook';
    public const SOURCE_FUNNEL_GOAL = 'funnel_goal';

    public const ATTRIBUTION_LAST_CLICK = 'last_click';
    public const ATTRIBUTION_FUNNEL = 'funnel';
    public const ATTRIBUTION_NONE = 'none';

    protected $fillable = [
        'user_id',
        'source',
        'source_id',
        'subscriber_id',
        'customer_email',
        'amount',
        'currency',
        'occurred_at',
        'attributed_message_id',
        'attributed_funnel_id',
        'attribution_type',
        'attributed_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'occurred_at' => 'datetime',
        'attributed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function attributedMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'attributed_message_id');
    }

    /**
     * Amount in major units (e.g. 49.99).
     */
    public function getAmountMajorAttribute(): float
    {
        return round($this->amount / 100, 2);
    }

    // --- Scopes ---

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('occurred_at', [$from, $to]);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('occurred_at', '>=', now()->subDays($days));
    }

    public function scopeAttributed($query)
    {
        return $query->whereNotNull('attributed_message_id');
    }

    public function scopeUnattributed($query)
    {
        return $query->whereNull('attribution_type');
    }
}

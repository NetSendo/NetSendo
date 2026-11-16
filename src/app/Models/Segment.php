<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A saved dynamic segment of an account: conditions combined with AND
 * (match = all) or OR (match = any), evaluated whenever the segment is used
 * (SegmentQueryService). subscribers_count is the last count, for lists.
 */
class Segment extends Model
{
    public const MATCH_ALL = 'all';
    public const MATCH_ANY = 'any';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'match',
        'conditions',
        'subscribers_count',
        'counted_at',
    ];

    protected $casts = [
        'conditions' => 'array',
        'subscribers_count' => 'integer',
        'counted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

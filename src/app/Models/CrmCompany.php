<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CrmCompany extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'domain',
        'industry',
        'size',
        'phone',
        'website',
        'address',
        'notes',
    ];

    /**
     * Get the user that owns this company.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all contacts associated with this company.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class);
    }

    /**
     * Get all deals associated with this company.
     */
    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }

    /**
     * Get all activities for this company.
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject');
    }

    /**
     * Scope a query to only include companies for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the total value of open deals.
     */
    public function getOpenDealsValueAttribute(): float
    {
        return $this->deals()->where('status', 'open')->sum('value');
    }

    /**
     * Get the count of contacts.
     */
    public function getContactsCountAttribute(): int
    {
        return $this->contacts()->count();
    }
}

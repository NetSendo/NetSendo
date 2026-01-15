<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPipeline extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get the user that owns this pipeline.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all stages in this pipeline.
     */
    public function stages(): HasMany
    {
        return $this->hasMany(CrmStage::class)->orderBy('order');
    }

    /**
     * Get all deals in this pipeline.
     */
    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }

    /**
     * Scope a query to only include pipelines for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the default pipeline for a user.
     */
    public static function getDefaultForUser(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Set this pipeline as the default (and unset others).
     */
    public function setAsDefault(): void
    {
        // Unset other defaults
        self::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get the first stage of this pipeline.
     */
    public function getFirstStage(): ?CrmStage
    {
        return $this->stages()->orderBy('order')->first();
    }

    /**
     * Get the won stage of this pipeline.
     */
    public function getWonStage(): ?CrmStage
    {
        return $this->stages()->where('is_won', true)->first();
    }

    /**
     * Get the lost stage of this pipeline.
     */
    public function getLostStage(): ?CrmStage
    {
        return $this->stages()->where('is_lost', true)->first();
    }
}

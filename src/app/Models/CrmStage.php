<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'crm_pipeline_id',
        'name',
        'color',
        'order',
        'is_won',
        'is_lost',
        'auto_task',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_won' => 'boolean',
        'is_lost' => 'boolean',
        'auto_task' => 'array',
    ];

    /**
     * Get the pipeline this stage belongs to.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'crm_pipeline_id');
    }

    /**
     * Get all deals in this stage.
     */
    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }

    /**
     * Check if this is a closing stage (won or lost).
     */
    public function isClosingStage(): bool
    {
        return $this->is_won || $this->is_lost;
    }

    /**
     * Get the deal count for this stage.
     */
    public function getDealsCountAttribute(): int
    {
        return $this->deals()->where('status', 'open')->count();
    }

    /**
     * Get the total value of deals in this stage.
     */
    public function getDealsValueAttribute(): float
    {
        return $this->deals()->where('status', 'open')->sum('value');
    }
}

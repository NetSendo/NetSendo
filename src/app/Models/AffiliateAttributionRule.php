<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateAttributionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'model',
        'window_days',
        'cross_device_tracking',
        'settings',
    ];

    protected $casts = [
        'window_days' => 'integer',
        'cross_device_tracking' => 'boolean',
        'settings' => 'array',
    ];

    // Relationships

    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    // Accessors

    public function getModelLabelAttribute(): string
    {
        return match ($this->model) {
            'first_click' => 'First Click',
            'last_click' => 'Last Click',
            'linear' => 'Linear',
            'time_decay' => 'Time Decay',
            default => $this->model,
        };
    }
}

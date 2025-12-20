<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModel extends Model
{
    protected $fillable = [
        'ai_integration_id',
        'model_id',
        'display_name',
        'is_custom',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
    ];

    /**
     * Get the integration that owns this model.
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(AiIntegration::class, 'ai_integration_id');
    }
}

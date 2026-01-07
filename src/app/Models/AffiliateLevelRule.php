<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateLevelRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'level',
        'commission_type',
        'commission_value',
        'min_sales_required',
        'conditions',
    ];

    protected $casts = [
        'level' => 'integer',
        'commission_value' => 'decimal:2',
        'min_sales_required' => 'integer',
        'conditions' => 'array',
    ];

    // Relationships

    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    // Accessors

    public function getFormattedCommissionAttribute(): string
    {
        if ($this->commission_type === 'percent') {
            return $this->commission_value . '%';
        }
        return number_format($this->commission_value, 2) . ' ' . ($this->program->currency ?? 'PLN');
    }
}

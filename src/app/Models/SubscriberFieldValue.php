<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriberFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscriber_id',
        'custom_field_id',
        'value',
    ];

    protected static function booted(): void
    {
        // "field_updated" automations (FieldChangeNotifier decides if any listen)
        static::saved(fn (SubscriberFieldValue $value) => \App\Services\Automation\FieldChangeNotifier::fieldValueSaved($value));
    }

    /**
     * Get the subscriber this value belongs to
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Get the custom field definition
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }
}

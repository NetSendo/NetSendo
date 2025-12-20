<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailOpen extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'subscriber_id',
        'ip_address',
        'user_agent',
        'opened_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }
}

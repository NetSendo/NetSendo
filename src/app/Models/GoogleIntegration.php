<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'client_id',
        'client_secret',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

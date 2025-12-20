<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contactLists()
    {
        return $this->morphedByMany(ContactList::class, 'taggable');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactListGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_id',
        'timezone',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contactLists()
    {
        return $this->hasMany(ContactList::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /**
     * Get all messages tagged with this tag (for campaign statistics).
     */
    public function messages()
    {
        return $this->morphedByMany(Message::class, 'taggable');
    }

    /**
     * Get all subscribers that have this tag.
     */
    public function subscribers()
    {
        return $this->belongsToMany(Subscriber::class, 'subscriber_tag')
            ->withTimestamps();
    }
}

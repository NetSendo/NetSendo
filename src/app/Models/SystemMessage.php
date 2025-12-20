<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'contact_list_id',
        'name',
        'title',
        'content',
    ];

    public function contactList()
    {
        return $this->belongsTo(ContactList::class);
    }

    /**
     * Get system message by slug and optional list ID.
     */
    public static function getBySlug($slug, $listId = null)
    {
        // Try to find for specific list first
        if ($listId) {
            $message = self::where('slug', $slug)->where('contact_list_id', $listId)->first();
            if ($message) {
                return $message;
            }
        }
        
        // Fallback to global (null list_id)
        return self::where('slug', $slug)->whereNull('contact_list_id')->first();
    }
}

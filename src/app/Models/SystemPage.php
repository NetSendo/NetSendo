<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'contact_list_id',
        'name',
        'title',
        'content',
        'access',
    ];

    public function contactList()
    {
        return $this->belongsTo(ContactList::class);
    }

    /**
     * Get system page by slug and optional list ID.
     */
    public static function getBySlug($slug, $listId = null)
    {
        // Try to find for specific list first
        if ($listId) {
            $page = self::where('slug', $slug)->where('contact_list_id', $listId)->first();
            if ($page) {
                return $page;
            }
        }
        
        // Fallback to global (null list_id)
        return self::where('slug', $slug)->whereNull('contact_list_id')->first();
    }

    /**
     * Get the public URL for this system page
     */
    public function getUrlAttribute(): string
    {
        return url("/p/{$this->slug}");
    }
}

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
        'parent_id',
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

    /**
     * Parent group relationship.
     */
    public function parent()
    {
        return $this->belongsTo(ContactListGroup::class, 'parent_id');
    }

    /**
     * Direct children groups.
     */
    public function children()
    {
        return $this->hasMany(ContactListGroup::class, 'parent_id');
    }

    /**
     * Recursive children (all descendants).
     */
    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    /**
     * Get all descendant IDs (for filtering).
     */
    public function getAllDescendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }
        return $ids;
    }

    /**
     * Get the full path (e.g., "Marketing > Newsletter > Promocje").
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
            array_unshift($path, $current->name);
        }

        return implode(' > ', $path);
    }

    /**
     * Get depth level (0 = root).
     */
    public function getDepthAttribute(): int
    {
        $depth = 0;
        $current = $this;

        while ($current->parent) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }
}

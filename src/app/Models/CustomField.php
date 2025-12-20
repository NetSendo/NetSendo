<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'description',
        'type',
        'options',
        'default_value',
        'is_public',
        'is_required',
        'is_static',
        'scope',
        'contact_list_id',
        'sort_order',
        'user_id',
    ];

    protected $casts = [
        'options' => 'array',
        'is_public' => 'boolean',
        'is_required' => 'boolean',
        'is_static' => 'boolean',
    ];

    /**
     * Reserved field names that cannot be used for custom fields
     */
    public const RESERVED_NAMES = [
        'email', 'first_name', 'last_name', 'phone', 'device',
        'ip_address', 'user_agent', 'subscribed_at', 'confirmed_at',
        'last_opened_at', 'last_clicked_at', 'opens_count', 'clicks_count',
        'source', 'tags', 'status', 'id', 'created_at', 'updated_at',
        'unsubscribe_link', 'unsubscribe_url', 'activate', 'unsubscribe'
    ];

    /**
     * Get the user who owns this field
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the contact list this field belongs to (for list-specific fields)
     */
    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    /**
     * Get all values for this field across subscribers
     */
    public function values(): HasMany
    {
        return $this->hasMany(SubscriberFieldValue::class);
    }

    /**
     * Scope: Only global fields
     */
    public function scopeGlobal($query)
    {
        return $query->where('scope', 'global');
    }

    /**
     * Scope: Only list-specific fields
     */
    public function scopeForList($query, int $listId)
    {
        return $query->where(function ($q) use ($listId) {
            $q->where('scope', 'global')
              ->orWhere(function ($q2) use ($listId) {
                  $q2->where('scope', 'list')
                     ->where('contact_list_id', $listId);
              });
        });
    }

    /**
     * Scope: Only public fields (visible in forms)
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Get the placeholder syntax for this field
     */
    public function getPlaceholderAttribute(): string
    {
        return '[[' . $this->name . ']]';
    }

    /**
     * Check if a field name is reserved
     */
    public static function isReservedName(string $name): bool
    {
        return in_array(strtolower($name), array_map('strtolower', self::RESERVED_NAMES));
    }

    /**
     * Validate field name format (only alphanumeric and underscore)
     */
    public static function isValidName(string $name): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*$/i', $name) === 1;
    }
}

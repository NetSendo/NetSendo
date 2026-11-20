<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'contact_list_id',
        'name',
        'subject',
        'content',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function contactList()
    {
        return $this->belongsTo(ContactList::class);
    }

    /**
     * Get system email by slug and optional list ID.
     */
    public static function getBySlug($slug, $listId = null)
    {
        // Try to find for specific list first. A list override switched off
        // disables the email for that list; it must not fall back to the
        // (always active) global default.
        if ($listId) {
            $email = self::where('slug', $slug)
                ->where('contact_list_id', $listId)
                ->first();
            if ($email) {
                return $email->is_active ? $email : null;
            }
        }
        
        // Fallback to global (null list_id)
        return self::where('slug', $slug)
            ->whereNull('contact_list_id')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Account-wide switch for the "new subscriber" admin notification
     * (settings.subscription.notify_admin). On unless switched off.
     */
    public static function newSubscriberNotificationsEnabled(ContactList $list): bool
    {
        $userSettings = $list->user?->settings ?? [];

        return (bool) ($userSettings['subscription']['notify_admin'] ?? true);
    }

    /**
     * Get notification email address for a given list with fallback logic.
     * An empty address means the account's own email, not "off" — the
     * notification is switched off with notify_admin or per list in
     * Settings → System emails.
     */
    public static function getNotificationEmail(ContactList $list): ?string
    {
        $user = $list->user;

        // 1. Check list-specific notification email
        $listSettings = $list->settings ?? [];
        $listNotificationEmail = $listSettings['subscription']['notification_email'] ?? null;
        
        if (!empty($listNotificationEmail)) {
            return $listNotificationEmail;
        }

        // 2. Check user's default notification email
        $userSettings = $user->settings ?? [];
        $userNotificationEmail = $userSettings['subscription']['notification_email'] ?? null;
        
        if (!empty($userNotificationEmail)) {
            return $userNotificationEmail;
        }

        // 3. Fallback to user's main email
        return $user->email;
    }
}

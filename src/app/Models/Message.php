<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class Message extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * Attributes to include in activity log
     */
    protected $activityLogAttributes = ['subject', 'status', 'channel'];

    protected $fillable = [
        'user_id',
        'channel', // email, sms
        'mailbox_id',
        'template_id',
        'type', // broadcast, autoresponder
        'day', // day offset
        'subject',
        'preheader',
        'content',
        'status',
        'timezone',
        'send_at',
        'time_of_day',
        // A/B Testing
        'ab_enabled',
        'ab_variant_subject',
        'ab_variant_content',
        'ab_split_percentage',
        // Triggers
        'trigger_type',
        'trigger_config',
    ];

    protected $casts = [
        'send_at' => 'datetime',
        'ab_enabled' => 'boolean',
        'ab_split_percentage' => 'integer',
        'trigger_config' => 'array',
    ];

    public function scopeEmail($query)
    {
        return $query->where('channel', 'email');
    }

    public function scopeSms($query)
    {
        return $query->where('channel', 'sms');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mailbox()
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function contactLists()
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_message');
    }

    // TODO: Implement tracking models when stats feature is ready
    // public function opens()
    // {
    //     return $this->hasMany(MessageOpen::class);
    // }

    // public function clicks()
    // {
    //     return $this->hasMany(MessageClick::class);
    // }

    /**
     * Get the effective mailbox for the message using hierarchical resolution.
     * Priority: Message -> List -> User Default
     */
    public function getEffectiveMailbox(): ?Mailbox
    {
        // 1. Explicit mailbox for this message
        if ($this->mailbox_id) {
            return $this->mailbox;
        }

        // 2. Default mailbox from the first contact list
        $list = $this->contactLists->first();
        if ($list && $list->default_mailbox_id) {
            return Mailbox::find($list->default_mailbox_id);
        }

        // 3. User's global default mailbox
        return Mailbox::getDefaultFor($this->user_id);
    }

    /**
     * Get the effective timezone for the message.
     * Hierarchy: Message -> List (first) -> Group -> User -> Account Default (UTC)
     */
    public function getEffectiveTimezoneAttribute()
    {
        if ($this->timezone) {
            return $this->timezone;
        }

        // Check primary contact list (if any)
        $list = $this->contactLists->first();
        if ($list) {
            if ($list->timezone) {
                return $list->timezone;
            }
            if ($list->group && $list->group->timezone) {
                return $list->group->timezone;
            }
        }

        if ($this->user && $this->user->timezone) {
            return $this->user->timezone;
        }

        return 'UTC';
    }
}


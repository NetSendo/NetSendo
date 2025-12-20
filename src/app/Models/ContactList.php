<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;

class ContactList extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * Activity log name prefix
     */
    protected $activityLogName = 'list';

    protected $fillable = [
        'name',
        'type', // email, sms
        'description',
        'contact_list_group_id',
        'default_mailbox_id',
        'is_public',
        'timezone',
        'settings',
        'user_id',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_public' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscribers()
    {
        return $this->hasMany(Subscriber::class);
    }

    public function group()
    {
        return $this->belongsTo(ContactListGroup::class, 'contact_list_group_id');
    }

    public function defaultMailbox()
    {
        return $this->belongsTo(Mailbox::class, 'default_mailbox_id');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function scopeEmail($query)
    {
        return $query->where('type', 'email');
    }

    public function scopeSms($query)
    {
        return $query->where('type', 'sms');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Relacja do ustawień CRON dla tej listy
     */
    public function cronSettings()
    {
        return $this->hasOne(ContactListCronSetting::class);
    }

    /**
     * Sprawdź czy wysyłka jest dozwolona dla tej listy w danym momencie
     */
    public function isDispatchAllowedNow(?\DateTimeInterface $dateTime = null): bool
    {
        $settings = $this->cronSettings ?? ContactListCronSetting::getOrCreateForList($this->id);
        return $settings->isDispatchAllowedNow($dateTime);
    }
}


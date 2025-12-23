<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LogsActivity;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        // Integration settings
        'api_key',
        'webhook_url',
        'webhook_events',
        // Co-registration / Advanced
        'parent_list_id',
        'sync_settings',
        'max_subscribers',
        'signups_blocked',
        'required_fields',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_public' => 'boolean',
        'webhook_events' => 'array',
        'sync_settings' => 'array',
        'required_fields' => 'array',
        'signups_blocked' => 'boolean',
        'max_subscribers' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscribers()
    {
        return $this->belongsToMany(Subscriber::class, 'contact_list_subscriber')
            ->withPivot('status', 'subscribed_at', 'unsubscribed_at')
            ->withTimestamps();
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

    /**
     * Parent list for co-registration
     */
    public function parentList()
    {
        return $this->belongsTo(ContactList::class, 'parent_list_id');
    }

    /**
     * Child lists (sublists)
     */
    public function childLists()
    {
        return $this->hasMany(ContactList::class, 'parent_list_id');
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

    /**
     * Generate a new API key for this list
     */
    public function generateApiKey(): string
    {
        $key = 'ml_' . $this->id . '_' . Str::random(32);
        $this->update(['api_key' => $key]);
        return $key;
    }

    /**
     * Trigger webhook for an event
     */
    public function triggerWebhook(string $event, array $data = []): bool
    {
        if (empty($this->webhook_url)) {
            return false;
        }

        $events = $this->webhook_events ?? [];
        if (!in_array($event, $events)) {
            return false;
        }

        $payload = [
            'event' => $event,
            'list_id' => $this->id,
            'list_name' => $this->name,
            'timestamp' => now()->toISOString(),
            'data' => $data,
        ];

        try {
            $response = Http::timeout(10)->post($this->webhook_url, $payload);
            
            Log::info('Webhook triggered', [
                'list_id' => $this->id,
                'event' => $event,
                'url' => $this->webhook_url,
                'status' => $response->status(),
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Webhook failed', [
                'list_id' => $this->id,
                'event' => $event,
                'url' => $this->webhook_url,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if signups are allowed
     */
    public function canAcceptSignups(): bool
    {
        if ($this->signups_blocked) {
            return false;
        }

        if ($this->max_subscribers > 0) {
            $currentCount = $this->subscribers()->wherePivot('status', 'active')->count();
            if ($currentCount >= $this->max_subscribers) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sync subscriber to parent list (co-registration)
     */
    public function syncToParentList(Subscriber $subscriber, string $action = 'subscribe'): void
    {
        if (!$this->parent_list_id) {
            return;
        }

        $syncSettings = $this->sync_settings ?? [];
        
        // Check if sync is enabled for this action
        $syncOnSubscribe = $syncSettings['sync_on_subscribe'] ?? true;
        $syncOnUnsubscribe = $syncSettings['sync_on_unsubscribe'] ?? false;

        if ($action === 'subscribe' && !$syncOnSubscribe) {
            return;
        }

        if ($action === 'unsubscribe' && !$syncOnUnsubscribe) {
            return;
        }

        $parentList = $this->parentList;
        if (!$parentList) {
            return;
        }

        if ($action === 'subscribe') {
            // Add to parent list if not already there
            if (!$parentList->subscribers()->where('subscriber_id', $subscriber->id)->exists()) {
                $parentList->subscribers()->attach($subscriber->id, [
                    'status' => 'active',
                    'subscribed_at' => now(),
                ]);
            }
        } elseif ($action === 'unsubscribe') {
            // Update status in parent list
            $parentList->subscribers()->updateExistingPivot($subscriber->id, [
                'status' => 'unsubscribed',
                'unsubscribed_at' => now(),
            ]);
        }
    }
}

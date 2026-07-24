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
        'default_sms_provider_id',
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
        // Resubscription behavior
        'resubscription_behavior', // reset_date or keep_original_date
        'reset_autoresponders_on_resubscription', // Reset autoresponder queue on resubscription
    ];

    protected $casts = [
        'settings' => 'array',
        'is_public' => 'boolean',
        'webhook_events' => 'array',
        'sync_settings' => 'array',
        'required_fields' => 'array',
        'signups_blocked' => 'boolean',
        'max_subscribers' => 'integer',
        'reset_autoresponders_on_resubscription' => 'boolean',
    ];

    /**
     * Canonical webhook events selectable for a list's webhook (list
     * settings → Integration). These mirror the subscriber-lifecycle events
     * dispatched by DispatchWebhooksListener so a list's webhook_url fires on
     * real events, not only the legacy 'subscribe'/'unsubscribe' paths.
     */
    public const WEBHOOK_EVENTS = [
        'subscriber.created',
        'subscriber.subscribed',
        'subscriber.unsubscribed',
        'subscriber.bounced',
        'subscriber.tag_added',
        'subscriber.tag_removed',
    ];

    /**
     * Legacy short event names → canonical event. Older lists stored the
     * short names; keep them working so upgrading never silently drops a
     * configured delivery.
     */
    public const LEGACY_WEBHOOK_EVENT_ALIASES = [
        'subscribe' => 'subscriber.subscribed',
        'unsubscribe' => 'subscriber.unsubscribed',
        'bounce' => 'subscriber.bounced',
    ];

    /**
     * All accepted webhook event values (canonical + legacy), used for
     * request validation.
     */
    public static function acceptedWebhookEvents(): array
    {
        return array_merge(
            self::WEBHOOK_EVENTS,
            array_keys(self::LEGACY_WEBHOOK_EVENT_ALIASES),
            ['subscriber.updated', 'update'] // tolerated for backward compatibility
        );
    }

    /**
     * Whether this list's webhook is subscribed to a canonical event,
     * honouring legacy short-name aliases still stored on older lists.
     */
    public function subscribedToWebhookEvent(string $event): bool
    {
        $events = $this->webhook_events ?? [];

        if (in_array($event, $events, true)) {
            return true;
        }

        foreach (self::LEGACY_WEBHOOK_EVENT_ALIASES as $legacy => $canonical) {
            if ($canonical === $event && in_array($legacy, $events, true)) {
                return true;
            }
        }

        return false;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscribers()
    {
        return $this->belongsToMany(Subscriber::class, 'contact_list_subscriber')
            ->withPivot('status', 'source', 'subscribed_at', 'unsubscribed_at', 'confirmed_at', 'resubscribed_at', 'soft_bounce_count')
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

    /**
     * Default SMS provider for this list
     */
    public function defaultSmsProvider()
    {
        return $this->belongsTo(SmsProvider::class, 'default_sms_provider_id');
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

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
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
     * Trigger webhook for an event (legacy inline callers: API list
     * subscribe/unsubscribe and the "test webhook" button).
     *
     * Accepts the legacy short event names and resolves them to canonical
     * ones so a list configured with the new event vocabulary
     * (subscriber.subscribed, …) still matches.
     */
    public function triggerWebhook(string $event, array $data = []): bool
    {
        if (empty($this->webhook_url)) {
            return false;
        }

        // The 'test' event bypasses the subscription check (testWebhook()
        // sets webhook_events = ['test'] before calling).
        $canonical = self::LEGACY_WEBHOOK_EVENT_ALIASES[$event] ?? $event;
        if ($event !== 'test' && !$this->subscribedToWebhookEvent($canonical)) {
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

                // Start autoresponder sequences / automations on the parent list
                event(new \App\Events\SubscriberSignedUp($subscriber, $parentList, null, 'parent_list_sync'));
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

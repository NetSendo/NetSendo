<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'trigger_event',
        'trigger_config',
        'conditions',
        'condition_logic',
        'actions',
        'is_active',
        'execution_count',
        'last_executed_at',
        'limit_per_subscriber',
        'limit_count',
        'limit_period',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'limit_per_subscriber' => 'boolean',
        'last_executed_at' => 'datetime',
    ];

    /**
     * Available trigger events
     */
    public const TRIGGER_EVENTS = [
        'subscriber_signup' => 'Zapis na listę',
        'subscriber_activated' => 'Aktywacja subskrybenta',
        'email_opened' => 'Otwarcie emaila',
        'email_clicked' => 'Kliknięcie w link',
        'subscriber_unsubscribed' => 'Wypisanie z listy',
        'email_bounced' => 'Odbicie emaila',
        'form_submitted' => 'Wypełnienie formularza',
        'tag_added' => 'Dodanie taga',
        'tag_removed' => 'Usunięcie taga',
        'field_updated' => 'Zmiana pola',
    ];

    /**
     * Available action types
     */
    public const ACTION_TYPES = [
        'send_email' => 'Wyślij email',
        'add_tag' => 'Dodaj tag',
        'remove_tag' => 'Usuń tag',
        'move_to_list' => 'Przenieś do listy',
        'copy_to_list' => 'Skopiuj do listy',
        'unsubscribe' => 'Wypisz z listy',
        'call_webhook' => 'Wywołaj webhook',
        'start_funnel' => 'Uruchom lejek',
        'update_field' => 'Zaktualizuj pole',
        'notify_admin' => 'Powiadom administratora',
    ];

    /**
     * Available condition types
     */
    public const CONDITION_TYPES = [
        'list_is' => 'Lista jest',
        'list_is_not' => 'Lista nie jest',
        'tag_exists' => 'Ma tag',
        'tag_not_exists' => 'Nie ma taga',
        'field_equals' => 'Pole równe',
        'field_not_equals' => 'Pole różne od',
        'field_contains' => 'Pole zawiera',
        'field_is_empty' => 'Pole jest puste',
        'field_is_not_empty' => 'Pole nie jest puste',
        'email_opened_message' => 'Otworzył wiadomość',
        'email_clicked_message' => 'Kliknął w wiadomość',
        'subscribed_days_ago' => 'Zapisał się X dni temu',
        'source_is' => 'Źródło zapisu jest',
    ];

    /**
     * Rate limit periods
     */
    public const LIMIT_PERIODS = [
        'hour' => 'na godzinę',
        'day' => 'dziennie',
        'week' => 'tygodniowo',
        'month' => 'miesięcznie',
        'ever' => 'w ogóle',
    ];

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationRuleLog::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTrigger($query, string $triggerEvent)
    {
        return $query->where('trigger_event', $triggerEvent);
    }

    // Helper methods

    /**
     * Check if automation can execute for a subscriber (rate limiting)
     */
    public function canExecuteForSubscriber(int $subscriberId): bool
    {
        if (!$this->limit_per_subscriber || !$this->limit_count) {
            return true;
        }

        $query = $this->logs()
            ->where('subscriber_id', $subscriberId)
            ->where('status', 'success');

        if ($this->limit_period !== 'ever') {
            $since = match ($this->limit_period) {
                'hour' => now()->subHour(),
                'day' => now()->subDay(),
                'week' => now()->subWeek(),
                'month' => now()->subMonth(),
                default => now()->subDay(),
            };
            $query->where('executed_at', '>=', $since);
        }

        return $query->count() < $this->limit_count;
    }

    /**
     * Increment execution count
     */
    public function incrementExecutionCount(): void
    {
        $this->increment('execution_count');
        $this->update(['last_executed_at' => now()]);
    }

    /**
     * Duplicate the automation rule
     */
    public function duplicate(): self
    {
        $newRule = $this->replicate();
        $newRule->name = '[KOPIA] ' . $this->name;
        $newRule->is_active = false;
        $newRule->execution_count = 0;
        $newRule->last_executed_at = null;
        $newRule->save();

        return $newRule;
    }

    /**
     * Get trigger event label
     */
    public function getTriggerEventLabelAttribute(): string
    {
        return self::TRIGGER_EVENTS[$this->trigger_event] ?? $this->trigger_event;
    }

    /**
     * Get actions count
     */
    public function getActionsCountAttribute(): int
    {
        return is_array($this->actions) ? count($this->actions) : 0;
    }

    /**
     * Get stats for dashboard
     */
    public function getStats(int $days = 7): array
    {
        $since = now()->subDays($days);
        
        return [
            'total_executions' => $this->logs()->where('executed_at', '>=', $since)->count(),
            'successful' => $this->logs()->where('executed_at', '>=', $since)->where('status', 'success')->count(),
            'failed' => $this->logs()->where('executed_at', '>=', $since)->where('status', 'failed')->count(),
            'unique_subscribers' => $this->logs()->where('executed_at', '>=', $since)->distinct('subscriber_id')->count('subscriber_id'),
        ];
    }

    /**
     * Get all trigger events for frontend
     */
    public static function getTriggerEvents(): array
    {
        return self::TRIGGER_EVENTS;
    }

    /**
     * Get all action types for frontend
     */
    public static function getActionTypes(): array
    {
        return self::ACTION_TYPES;
    }

    /**
     * Get all condition types for frontend
     */
    public static function getConditionTypes(): array
    {
        return self::CONDITION_TYPES;
    }
}

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
        'is_system',
        'system_key',
        'execution_count',
        'last_executed_at',
        'limit_per_subscriber',
        'limit_count',
        'limit_period',
        'trigger_source',      // 'message', 'funnel', 'manual'
        'trigger_source_id',   // ID of the source (message_id, funnel_id)
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'limit_per_subscriber' => 'boolean',
        'last_executed_at' => 'datetime',
    ];

    /**
     * Available trigger events
     */
    public const TRIGGER_EVENTS = [
        'subscriber_signup' => 'Zapis na listę',
        'subscriber_activated' => 'Aktywacja subskrybenta',
        'list_join' => 'Dołączenie do listy',
        'email_opened' => 'Otwarcie emaila',
        'email_clicked' => 'Kliknięcie w link',
        'subscriber_unsubscribed' => 'Wypisanie z listy',
        'email_bounced' => 'Odbicie emaila',
        'form_submitted' => 'Wypełnienie formularza',
        'tag_added' => 'Dodanie taga',
        'tag_removed' => 'Usunięcie taga',
        'field_updated' => 'Zmiana pola',
        // Page/Time triggers
        'page_visited' => 'Odwiedziny strony',
        'specific_link_clicked' => 'Kliknięcie w konkretny link',
        'date_reached' => 'Osiągnięcie daty',
        'read_time_threshold' => 'Próg czasu czytania',
        'subscriber_birthday' => 'Urodziny subskrybenta',
        'subscription_anniversary' => 'Rocznica zapisu',
        'subscriber_inactive' => 'Brak aktywności',
        // E-commerce / Purchase triggers
        'purchase' => '🛒 Zakup (webhook)',
        // Pixel tracking triggers (e-commerce)
        'pixel_page_visited' => '🎯 Pixel: Odwiedziny strony',
        'pixel_product_viewed' => '🎯 Pixel: Wyświetlenie produktu',
        'pixel_add_to_cart' => '🎯 Pixel: Dodanie do koszyka',
        'pixel_checkout_started' => '🎯 Pixel: Rozpoczęcie checkout',
        'pixel_cart_abandoned' => '🎯 Pixel: Porzucony koszyk',
        'pixel_return_visit' => '🎯 Pixel: Powrót na stronę',
        // CRM Sales Triggers
        'crm_deal_stage_changed' => '💼 CRM: Zmiana etapu deala',
        'crm_deal_won' => '💼 CRM: Deal wygrany',
        'crm_deal_lost' => '💼 CRM: Deal przegrany',
        'crm_deal_created' => '💼 CRM: Utworzenie deala',
        'crm_deal_idle' => '💼 CRM: Brak aktywności w dealu',
        'crm_task_completed' => '💼 CRM: Ukończenie zadania',
        'crm_task_overdue' => '💼 CRM: Zadanie przeterminowane',
        'crm_contact_created' => '💼 CRM: Utworzenie kontaktu',
        'crm_contact_status_changed' => '💼 CRM: Zmiana statusu kontaktu',
        'crm_score_threshold' => '💼 CRM: Próg lead score',
        'crm_activity_logged' => '💼 CRM: Nowa aktywność',
        'crm_contact_replied' => '💼 CRM: Kontakt odpowiedział',
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
        'stop_funnel' => 'Zatrzymaj lejek',
        'start_sequence' => 'Uruchom sekwencję',
        'stop_sequence' => 'Zatrzymaj sekwencję',
        'update_field' => 'Zaktualizuj pole',
        'add_score' => 'Dodaj punkty scoring',
        'notify_admin' => 'Powiadom administratora',
        // CRM Actions
        'crm_create_task' => '💼 CRM: Utwórz zadanie',
        'crm_update_score' => '💼 CRM: Zmień lead score',
        'crm_move_deal' => '💼 CRM: Przesuń deal do etapu',
        'crm_assign_owner' => '💼 CRM: Przypisz właściciela',
        'crm_convert_to_contact' => '💼 CRM: Konwertuj na kontakt CRM',
        'crm_log_activity' => '💼 CRM: Zaloguj aktywność',
        'crm_update_contact_status' => '💼 CRM: Zmień status kontaktu',
        'crm_create_deal' => '💼 CRM: Utwórz deal',
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
        // CRM Conditions
        'crm_deal_in_stage' => '💼 CRM: Deal w etapie',
        'crm_contact_has_deals' => '💼 CRM: Kontakt ma otwarte deale',
        'crm_score_above' => '💼 CRM: Score powyżej',
        'crm_score_below' => '💼 CRM: Score poniżej',
        'crm_contact_status_is' => '💼 CRM: Status kontaktu jest',
        'crm_owned_by' => '💼 CRM: Przypisany do',
        'crm_pipeline_is' => '💼 CRM: Pipeline jest',
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

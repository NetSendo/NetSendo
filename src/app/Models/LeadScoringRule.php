<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class LeadScoringRule extends Model
{
    use HasFactory;

    /**
     * Available event types for scoring.
     */
    public const EVENT_TYPES = [
        'email_opened' => 'Otwarcie emaila',
        'email_clicked' => 'Kliknięcie w link',
        'email_replied' => 'Odpowiedź na email',
        'form_submitted' => 'Wypełnienie formularza',
        'page_visited' => 'Wizyta na stronie',
        'product_viewed' => 'Przeglądanie produktu',
        'add_to_cart' => 'Dodanie do koszyka',
        'checkout_started' => 'Rozpoczęcie checkout',
        'tag_added' => 'Dodanie tagu',
        'tag_removed' => 'Usunięcie tagu',
        'contact_created' => 'Utworzenie kontaktu',
        'decay_7_days' => 'Brak aktywności 7 dni',
        'decay_30_days' => 'Brak aktywności 30 dni',
    ];

    /**
     * Available condition operators.
     */
    public const CONDITION_OPERATORS = [
        'equals' => 'Równa się',
        'not_equals' => 'Nie równa się',
        'contains' => 'Zawiera',
        'not_contains' => 'Nie zawiera',
        'starts_with' => 'Zaczyna się od',
        'ends_with' => 'Kończy się na',
        'regex' => 'Regex',
        'greater_than' => 'Większe niż',
        'less_than' => 'Mniejsze niż',
    ];

    protected $fillable = [
        'user_id',
        'event_type',
        'name',
        'description',
        'points',
        'condition_field',
        'condition_operator',
        'condition_value',
        'cooldown_minutes',
        'max_daily_occurrences',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'points' => 'integer',
        'cooldown_minutes' => 'integer',
        'max_daily_occurrences' => 'integer',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get the user that owns this rule.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get history entries that used this rule.
     */
    public function scoreHistory(): HasMany
    {
        return $this->hasMany(LeadScoreHistory::class);
    }

    // ==================== SCOPES ====================

    /**
     * Scope to only include active rules.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeForEvent(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to get rules for a specific user, ordered by priority.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)->orderByDesc('priority');
    }

    // ==================== HELPERS ====================

    /**
     * Check if the condition matches the given context.
     */
    public function matchesCondition(array $context): bool
    {
        // No condition = always matches
        if (empty($this->condition_field) || empty($this->condition_operator)) {
            return true;
        }

        $value = $context[$this->condition_field] ?? null;

        if ($value === null) {
            return false;
        }

        return match ($this->condition_operator) {
            'equals' => $value === $this->condition_value,
            'not_equals' => $value !== $this->condition_value,
            'contains' => str_contains((string) $value, $this->condition_value),
            'not_contains' => !str_contains((string) $value, $this->condition_value),
            'starts_with' => str_starts_with((string) $value, $this->condition_value),
            'ends_with' => str_ends_with((string) $value, $this->condition_value),
            'regex' => (bool) preg_match($this->condition_value, (string) $value),
            'greater_than' => (float) $value > (float) $this->condition_value,
            'less_than' => (float) $value < (float) $this->condition_value,
            default => true,
        };
    }

    /**
     * Get default scoring rules for a new user.
     *
     * Names and descriptions are stored, so they are written in the given
     * locale (the account owner's), or in the current one when null.
     */
    public static function getDefaultRules(?string $locale = null): array
    {
        $t = fn (string $key): string => __("crm.scoring.defaults.{$key}", [], $locale);

        return [
            // Email events
            [
                'event_type' => 'email_opened',
                'name' => $t('email_opened.name'),
                'description' => $t('email_opened.description'),
                'points' => 3,
                'cooldown_minutes' => 60,
                'priority' => 10,
            ],
            [
                'event_type' => 'email_clicked',
                'name' => $t('email_clicked.name'),
                'description' => $t('email_clicked.description'),
                'points' => 10,
                'cooldown_minutes' => 60,
                'priority' => 20,
            ],
            [
                'event_type' => 'email_replied',
                'name' => $t('email_replied.name'),
                'description' => $t('email_replied.description'),
                'points' => 25,
                'cooldown_minutes' => 0,
                'priority' => 30,
            ],

            // Contact creation
            [
                'event_type' => 'contact_created',
                'name' => $t('contact_created.name'),
                'description' => $t('contact_created.description'),
                'points' => 5,
                'cooldown_minutes' => 0,
                'priority' => 35,
            ],

            // Form events
            [
                'event_type' => 'form_submitted',
                'name' => $t('form_submitted.name'),
                'description' => $t('form_submitted.description'),
                'points' => 20,
                'cooldown_minutes' => 0,
                'priority' => 40,
            ],

            // Page visits
            [
                'event_type' => 'page_visited',
                'name' => $t('page_visited.name'),
                'description' => $t('page_visited.description'),
                'points' => 2,
                'cooldown_minutes' => 60,
                'priority' => 5,
            ],
            [
                'event_type' => 'page_visited',
                'name' => $t('page_visited_pricing.name'),
                'description' => $t('page_visited_pricing.description'),
                'points' => 15,
                'condition_field' => 'page_url',
                'condition_operator' => 'contains',
                'condition_value' => '/pricing',
                'cooldown_minutes' => 1440, // 24h
                'priority' => 50,
            ],

            // E-commerce events
            [
                'event_type' => 'product_viewed',
                'name' => $t('product_viewed.name'),
                'description' => $t('product_viewed.description'),
                'points' => 5,
                'cooldown_minutes' => 60,
                'priority' => 15,
            ],
            [
                'event_type' => 'add_to_cart',
                'name' => $t('add_to_cart.name'),
                'description' => $t('add_to_cart.description'),
                'points' => 20,
                'cooldown_minutes' => 0,
                'priority' => 60,
            ],
            [
                'event_type' => 'checkout_started',
                'name' => $t('checkout_started.name'),
                'description' => $t('checkout_started.description'),
                'points' => 30,
                'cooldown_minutes' => 0,
                'priority' => 70,
            ],

            // Tag events
            [
                'event_type' => 'tag_added',
                'name' => $t('tag_added_hot.name'),
                'description' => $t('tag_added_hot.description'),
                'points' => 25,
                'condition_field' => 'tag_name',
                'condition_operator' => 'equals',
                'condition_value' => 'hot',
                'cooldown_minutes' => 0,
                'priority' => 80,
            ],
            [
                'event_type' => 'tag_added',
                'name' => $t('tag_added_vip.name'),
                'description' => $t('tag_added_vip.description'),
                'points' => 50,
                'condition_field' => 'tag_name',
                'condition_operator' => 'equals',
                'condition_value' => 'vip',
                'cooldown_minutes' => 0,
                'priority' => 90,
            ],

            // Decay rules (negative points)
            [
                'event_type' => 'decay_7_days',
                'name' => $t('decay_7_days.name'),
                'description' => $t('decay_7_days.description'),
                'points' => -5,
                'cooldown_minutes' => 10080, // 7 days
                'priority' => 1,
            ],
            [
                'event_type' => 'decay_30_days',
                'name' => $t('decay_30_days.name'),
                'description' => $t('decay_30_days.description'),
                'points' => -15,
                'cooldown_minutes' => 43200, // 30 days
                'priority' => 2,
            ],
        ];
    }

    /**
     * Seed default rules for a user.
     */
    public static function seedDefaultsForUser(int $userId): void
    {
        $locale = User::whereKey($userId)->value('locale') ?: null;

        foreach (self::getDefaultRules($locale) as $ruleData) {
            self::create(array_merge($ruleData, ['user_id' => $userId]));
        }
    }
}

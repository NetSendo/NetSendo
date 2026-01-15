<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunnelStep extends Model
{
    use HasFactory;

    // Step type constants
    public const TYPE_START = 'start';
    public const TYPE_EMAIL = 'email';
    public const TYPE_SMS = 'sms';
    public const TYPE_DELAY = 'delay';
    public const TYPE_WAIT_UNTIL = 'wait_until';
    public const TYPE_CONDITION = 'condition';
    public const TYPE_ACTION = 'action';
    public const TYPE_SPLIT = 'split';
    public const TYPE_GOAL = 'goal';
    public const TYPE_END = 'end';

    // Wait Until type constants
    public const WAIT_UNTIL_SPECIFIC_DATE = 'specific_date';
    public const WAIT_UNTIL_DAY_OF_WEEK = 'day_of_week';
    public const WAIT_UNTIL_BUSINESS_HOURS = 'business_hours';

    // Goal type constants
    public const GOAL_PURCHASE = 'purchase';
    public const GOAL_SIGNUP = 'signup';
    public const GOAL_PAGE_VISIT = 'page_visit';
    public const GOAL_TAG_ADDED = 'tag_added';
    public const GOAL_CUSTOM = 'custom';
    public const GOAL_WEBHOOK = 'webhook';

    // Delay unit constants
    public const DELAY_MINUTES = 'minutes';
    public const DELAY_HOURS = 'hours';
    public const DELAY_DAYS = 'days';
    public const DELAY_WEEKS = 'weeks';

    // Condition type constants
    public const CONDITION_EMAIL_OPENED = 'email_opened';
    public const CONDITION_EMAIL_CLICKED = 'email_clicked';
    public const CONDITION_LINK_CLICKED = 'link_clicked';
    public const CONDITION_TAG_EXISTS = 'tag_exists';
    public const CONDITION_FIELD_VALUE = 'field_value';
    public const CONDITION_TASK_COMPLETED = 'task_completed';

    // Retry interval unit constants
    public const RETRY_UNIT_HOURS = 'hours';
    public const RETRY_UNIT_DAYS = 'days';

    // Retry exhausted action constants
    public const RETRY_ACTION_CONTINUE = 'continue';
    public const RETRY_ACTION_EXIT = 'exit';
    public const RETRY_ACTION_UNSUBSCRIBE = 'unsubscribe';

    // Action type constants
    public const ACTION_ADD_TAG = 'add_tag';
    public const ACTION_REMOVE_TAG = 'remove_tag';
    public const ACTION_MOVE_TO_LIST = 'move_to_list';
    public const ACTION_COPY_TO_LIST = 'copy_to_list';
    public const ACTION_WEBHOOK = 'webhook';
    public const ACTION_UNSUBSCRIBE = 'unsubscribe';
    public const ACTION_NOTIFY = 'notify';

    protected $fillable = [
        'funnel_id',
        'type',
        'name',
        'message_id',
        'delay_value',
        'delay_unit',
        'condition_type',
        'condition_config',
        'action_type',
        'action_config',
        'position_x',
        'position_y',
        'next_step_id',
        'next_step_yes_id',
        'next_step_no_id',
        'order',
        // Retry settings
        'wait_for_condition',
        'retry_enabled',
        'retry_max_attempts',
        'retry_interval_value',
        'retry_interval_unit',
        'retry_message_id',
        'retry_exhausted_action',
        // New enhanced step fields
        'sms_content',
        'wait_until_date',
        'wait_until_time',
        'wait_until_timezone',
        'wait_until_type',
        'goal_name',
        'goal_type',
        'goal_value',
        'goal_config',
        'split_variants',
        'node_color',
        'node_icon',
    ];

    protected $casts = [
        'condition_config' => 'array',
        'action_config' => 'array',
        'delay_value' => 'integer',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'order' => 'integer',
        'wait_for_condition' => 'boolean',
        'retry_enabled' => 'boolean',
        'retry_max_attempts' => 'integer',
        'retry_interval_value' => 'integer',
        'goal_config' => 'array',
        'split_variants' => 'array',
        'goal_value' => 'decimal:2',
        'wait_until_date' => 'datetime',
    ];

    // =====================================
    // Relationships
    // =====================================

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function nextStep(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'next_step_id');
    }

    public function nextStepYes(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'next_step_yes_id');
    }

    public function nextStepNo(): BelongsTo
    {
        return $this->belongsTo(FunnelStep::class, 'next_step_no_id');
    }

    public function retryMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'retry_message_id');
    }

    public function retries(): HasMany
    {
        return $this->hasMany(FunnelStepRetry::class);
    }

    // =====================================
    // Type checkers
    // =====================================

    public function isStart(): bool
    {
        return $this->type === self::TYPE_START;
    }

    public function isEmail(): bool
    {
        return $this->type === self::TYPE_EMAIL;
    }

    public function isDelay(): bool
    {
        return $this->type === self::TYPE_DELAY;
    }

    public function isCondition(): bool
    {
        return $this->type === self::TYPE_CONDITION;
    }

    public function isAction(): bool
    {
        return $this->type === self::TYPE_ACTION;
    }

    public function isEnd(): bool
    {
        return $this->type === self::TYPE_END;
    }

    public function isSms(): bool
    {
        return $this->type === self::TYPE_SMS;
    }

    public function isWaitUntil(): bool
    {
        return $this->type === self::TYPE_WAIT_UNTIL;
    }

    public function isSplit(): bool
    {
        return $this->type === self::TYPE_SPLIT;
    }

    public function isGoal(): bool
    {
        return $this->type === self::TYPE_GOAL;
    }

    /**
     * Check if this step type requires branching (has yes/no paths)
     */
    public function hasBranching(): bool
    {
        return $this->isCondition() || $this->isSplit();
    }

    // =====================================
    // Accessors
    // =====================================

    public function getDelayInSecondsAttribute(): ?int
    {
        if (!$this->isDelay() || !$this->delay_value || !$this->delay_unit) {
            return null;
        }

        return match ($this->delay_unit) {
            self::DELAY_MINUTES => $this->delay_value * 60,
            self::DELAY_HOURS => $this->delay_value * 3600,
            self::DELAY_DAYS => $this->delay_value * 86400,
            self::DELAY_WEEKS => $this->delay_value * 604800,
            default => null,
        };
    }

    public function getDelayDisplayAttribute(): ?string
    {
        if (!$this->isDelay() || !$this->delay_value || !$this->delay_unit) {
            return null;
        }

        $units = [
            self::DELAY_MINUTES => ['minuta', 'minuty', 'minut'],
            self::DELAY_HOURS => ['godzina', 'godziny', 'godzin'],
            self::DELAY_DAYS => ['dzień', 'dni', 'dni'],
            self::DELAY_WEEKS => ['tydzień', 'tygodnie', 'tygodni'],
        ];

        $forms = $units[$this->delay_unit] ?? null;
        if (!$forms) {
            return "{$this->delay_value} {$this->delay_unit}";
        }

        $n = $this->delay_value;
        if ($n === 1) {
            return "1 {$forms[0]}";
        } elseif ($n >= 2 && $n <= 4) {
            return "{$n} {$forms[1]}";
        } else {
            return "{$n} {$forms[2]}";
        }
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->name) {
            return $this->name;
        }

        return match ($this->type) {
            self::TYPE_START => 'Start',
            self::TYPE_EMAIL => $this->message?->subject ?? 'Email',
            self::TYPE_DELAY => $this->delay_display ?? 'Opóźnienie',
            self::TYPE_CONDITION => $this->getConditionDisplayName(),
            self::TYPE_ACTION => $this->getActionDisplayName(),
            self::TYPE_END => 'Koniec',
            default => 'Krok',
        };
    }

    // =====================================
    // Methods
    // =====================================

    public function getConditionConfig(string $key, $default = null)
    {
        return $this->condition_config[$key] ?? $default;
    }

    public function getActionConfig(string $key, $default = null)
    {
        return $this->action_config[$key] ?? $default;
    }

    public function getNextStepForCondition(bool $conditionMet): ?FunnelStep
    {
        if (!$this->isCondition()) {
            return $this->nextStep;
        }

        return $conditionMet ? $this->nextStepYes : $this->nextStepNo;
    }

    protected function getConditionDisplayName(): string
    {
        return match ($this->condition_type) {
            self::CONDITION_EMAIL_OPENED => 'Jeśli otworzył email',
            self::CONDITION_EMAIL_CLICKED => 'Jeśli kliknął w email',
            self::CONDITION_LINK_CLICKED => 'Jeśli kliknął link',
            self::CONDITION_TAG_EXISTS => 'Jeśli ma tag',
            self::CONDITION_FIELD_VALUE => 'Jeśli pole ma wartość',
            default => 'Warunek',
        };
    }

    protected function getActionDisplayName(): string
    {
        return match ($this->action_type) {
            self::ACTION_ADD_TAG => 'Dodaj tag',
            self::ACTION_REMOVE_TAG => 'Usuń tag',
            self::ACTION_MOVE_TO_LIST => 'Przenieś na listę',
            self::ACTION_COPY_TO_LIST => 'Skopiuj na listę',
            self::ACTION_WEBHOOK => 'Wyślij webhook',
            self::ACTION_UNSUBSCRIBE => 'Wypisz',
            self::ACTION_NOTIFY => 'Powiadom',
            default => 'Akcja',
        };
    }

    // =====================================
    // Static helpers
    // =====================================

    public static function getTypes(): array
    {
        return [
            self::TYPE_START => 'Start',
            self::TYPE_EMAIL => 'Email',
            self::TYPE_SMS => 'SMS',
            self::TYPE_DELAY => 'Opóźnienie',
            self::TYPE_WAIT_UNTIL => 'Czekaj do',
            self::TYPE_CONDITION => 'Warunek',
            self::TYPE_ACTION => 'Akcja',
            self::TYPE_SPLIT => 'Test A/B',
            self::TYPE_GOAL => 'Cel',
            self::TYPE_END => 'Koniec',
        ];
    }

    public static function getGoalTypes(): array
    {
        return [
            self::GOAL_PURCHASE => 'Zakup',
            self::GOAL_SIGNUP => 'Rejestracja',
            self::GOAL_PAGE_VISIT => 'Wizyta na stronie',
            self::GOAL_TAG_ADDED => 'Dodanie tagu',
            self::GOAL_CUSTOM => 'Niestandardowy',
            self::GOAL_WEBHOOK => 'Webhook',
        ];
    }

    public static function getWaitUntilTypes(): array
    {
        return [
            self::WAIT_UNTIL_SPECIFIC_DATE => 'Konkretna data',
            self::WAIT_UNTIL_DAY_OF_WEEK => 'Dzień tygodnia',
            self::WAIT_UNTIL_BUSINESS_HOURS => 'Godziny pracy',
        ];
    }

    public static function getDelayUnits(): array
    {
        return [
            self::DELAY_MINUTES => 'Minuty',
            self::DELAY_HOURS => 'Godziny',
            self::DELAY_DAYS => 'Dni',
            self::DELAY_WEEKS => 'Tygodnie',
        ];
    }

    public static function getConditionTypes(): array
    {
        return [
            self::CONDITION_EMAIL_OPENED => 'Email został otwarty',
            self::CONDITION_EMAIL_CLICKED => 'Kliknięto w email',
            self::CONDITION_LINK_CLICKED => 'Kliknięto konkretny link',
            self::CONDITION_TAG_EXISTS => 'Subskrybent ma tag',
            self::CONDITION_FIELD_VALUE => 'Pole ma określoną wartość',
            self::CONDITION_TASK_COMPLETED => 'Zadanie zostało zaliczone',
        ];
    }

    public static function getRetryIntervalUnits(): array
    {
        return [
            self::RETRY_UNIT_HOURS => 'Godziny',
            self::RETRY_UNIT_DAYS => 'Dni',
        ];
    }

    public static function getRetryExhaustedActions(): array
    {
        return [
            self::RETRY_ACTION_CONTINUE => 'Kontynuuj lejek',
            self::RETRY_ACTION_EXIT => 'Oznacz jako nieaktywny',
            self::RETRY_ACTION_UNSUBSCRIBE => 'Wypisz z listy',
        ];
    }

    // =====================================
    // Retry helpers
    // =====================================

    public function hasRetryEnabled(): bool
    {
        return $this->wait_for_condition && $this->retry_enabled;
    }

    public function getRetryIntervalInSecondsAttribute(): ?int
    {
        if (!$this->retry_enabled || !$this->retry_interval_value || !$this->retry_interval_unit) {
            return null;
        }

        return match ($this->retry_interval_unit) {
            self::RETRY_UNIT_HOURS => $this->retry_interval_value * 3600,
            self::RETRY_UNIT_DAYS => $this->retry_interval_value * 86400,
            default => null,
        };
    }

    public static function getActionTypes(): array
    {
        return [
            self::ACTION_ADD_TAG => 'Dodaj tag',
            self::ACTION_REMOVE_TAG => 'Usuń tag',
            self::ACTION_MOVE_TO_LIST => 'Przenieś na listę',
            self::ACTION_COPY_TO_LIST => 'Skopiuj na listę',
            self::ACTION_WEBHOOK => 'Wyślij webhook',
            self::ACTION_UNSUBSCRIBE => 'Wypisz z listy',
            self::ACTION_NOTIFY => 'Wyślij powiadomienie',
        ];
    }
}

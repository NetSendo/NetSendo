<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelStep extends Model
{
    use HasFactory;

    // Step type constants
    public const TYPE_START = 'start';
    public const TYPE_EMAIL = 'email';
    public const TYPE_DELAY = 'delay';
    public const TYPE_CONDITION = 'condition';
    public const TYPE_ACTION = 'action';
    public const TYPE_END = 'end';

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
    ];

    protected $casts = [
        'condition_config' => 'array',
        'action_config' => 'array',
        'delay_value' => 'integer',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'order' => 'integer',
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
            self::TYPE_DELAY => 'Opóźnienie',
            self::TYPE_CONDITION => 'Warunek',
            self::TYPE_ACTION => 'Akcja',
            self::TYPE_END => 'Koniec',
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
        ];
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

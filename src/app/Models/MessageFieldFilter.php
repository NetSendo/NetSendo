<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single condition on a subscriber custom field, attached to a message.
 *
 * mode = include narrows the audience to subscribers matching the condition;
 * mode = exclude narrows the *exclusion* down to the matching subscribers, so
 * "exclude list X, city = Kraków" drops only the Kraków people from list X
 * instead of the whole list.
 */
class MessageFieldFilter extends Model
{
    use HasFactory;

    public const MODE_INCLUDE = 'include';
    public const MODE_EXCLUDE = 'exclude';

    public const MODES = [self::MODE_INCLUDE, self::MODE_EXCLUDE];

    public const MATCH_ALL = 'all';
    public const MATCH_ANY = 'any';

    public const MATCH_MODES = [self::MATCH_ALL, self::MATCH_ANY];

    /** Value taken from a list of choices (multi-value, OR between them) */
    public const OP_ANY_OF = 'any_of';
    public const OP_NONE_OF = 'none_of';
    /** Free-text matching */
    public const OP_CONTAINS = 'contains';
    public const OP_NOT_CONTAINS = 'not_contains';
    public const OP_STARTS_WITH = 'starts_with';
    public const OP_ENDS_WITH = 'ends_with';
    /** Presence */
    public const OP_IS_SET = 'is_set';
    public const OP_IS_EMPTY = 'is_empty';
    /** Ordered comparisons (number / date) */
    public const OP_GT = 'gt';
    public const OP_GTE = 'gte';
    public const OP_LT = 'lt';
    public const OP_LTE = 'lte';
    public const OP_BETWEEN = 'between';

    public const OPERATORS = [
        self::OP_ANY_OF, self::OP_NONE_OF,
        self::OP_CONTAINS, self::OP_NOT_CONTAINS, self::OP_STARTS_WITH, self::OP_ENDS_WITH,
        self::OP_IS_SET, self::OP_IS_EMPTY,
        self::OP_GT, self::OP_GTE, self::OP_LT, self::OP_LTE, self::OP_BETWEEN,
    ];

    /** Operators that need no value at all */
    public const VALUELESS_OPERATORS = [self::OP_IS_SET, self::OP_IS_EMPTY];

    /** Operators offered per custom field type */
    public const OPERATORS_BY_TYPE = [
        'text' => [
            self::OP_ANY_OF, self::OP_NONE_OF, self::OP_CONTAINS, self::OP_NOT_CONTAINS,
            self::OP_STARTS_WITH, self::OP_ENDS_WITH, self::OP_IS_SET, self::OP_IS_EMPTY,
        ],
        'number' => [
            self::OP_ANY_OF, self::OP_NONE_OF, self::OP_GT, self::OP_GTE,
            self::OP_LT, self::OP_LTE, self::OP_BETWEEN, self::OP_IS_SET, self::OP_IS_EMPTY,
        ],
        'date' => [
            self::OP_ANY_OF, self::OP_NONE_OF, self::OP_GT, self::OP_GTE,
            self::OP_LT, self::OP_LTE, self::OP_BETWEEN, self::OP_IS_SET, self::OP_IS_EMPTY,
        ],
        'select' => [self::OP_ANY_OF, self::OP_NONE_OF, self::OP_IS_SET, self::OP_IS_EMPTY],
        'radio' => [self::OP_ANY_OF, self::OP_NONE_OF, self::OP_IS_SET, self::OP_IS_EMPTY],
        'checkbox' => [self::OP_ANY_OF, self::OP_NONE_OF, self::OP_IS_SET, self::OP_IS_EMPTY],
    ];

    protected $fillable = [
        'message_id',
        'custom_field_id',
        'mode',
        'operator',
        'values',
        'sort_order',
    ];

    protected $casts = [
        'values' => 'array',
        'sort_order' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    public function scopeInclude($query)
    {
        return $query->where('mode', self::MODE_INCLUDE);
    }

    public function scopeExclude($query)
    {
        return $query->where('mode', self::MODE_EXCLUDE);
    }

    /**
     * Operators available for a custom field type, falling back to the text set
     * for any type we do not know about.
     */
    public static function operatorsForType(?string $type): array
    {
        return self::OPERATORS_BY_TYPE[$type] ?? self::OPERATORS_BY_TYPE['text'];
    }

    /**
     * Whether this operator takes any value at all.
     */
    public static function needsValue(string $operator): bool
    {
        return !in_array($operator, self::VALUELESS_OPERATORS, true);
    }

    /**
     * Non-empty values, reindexed. Empty strings are dropped so a half-filled
     * row never silently matches everything.
     */
    public function cleanValues(): array
    {
        return array_values(array_filter(
            array_map(
                fn ($value) => is_string($value) ? trim($value) : $value,
                $this->values ?? []
            ),
            fn ($value) => $value !== null && $value !== ''
        ));
    }

    /**
     * A filter is usable only when it carries the values its operator needs.
     */
    public function isUsable(): bool
    {
        if (!self::needsValue($this->operator)) {
            return true;
        }

        $values = $this->cleanValues();

        if ($this->operator === self::OP_BETWEEN) {
            return count($values) >= 2;
        }

        return count($values) >= 1;
    }
}

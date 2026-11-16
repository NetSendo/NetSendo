<?php

namespace App\Services\Automation;

use App\Events\SubscriberFieldUpdated;
use App\Models\AutomationRule;
use App\Models\CustomField;
use App\Models\Subscriber;
use App\Models\SubscriberFieldValue;

/**
 * Raises "field_updated" when a field of an existing subscriber changes:
 * - a standard field (Subscriber model update of a watched column);
 * - a custom field value (SubscriberFieldValue saved with another value).
 *
 * Only accounts with an active field_updated rule pay for it: the check is
 * one query, remembered for a minute. A field set while the subscriber is
 * being created (signup, import of a new address) is not a change. A rule
 * whose action writes a field does not raise the event again (no loops).
 */
class FieldChangeNotifier
{
    /** Standard columns whose change counts (the rest are bookkeeping). */
    public const WATCHED_STANDARD_FIELDS = ['email', 'first_name', 'last_name', 'phone', 'gender', 'language', 'source'];

    /** A subscriber created this recently is still being set up. */
    private const NEW_SUBSCRIBER_SECONDS = 10;

    private static int $depth = 0;

    /** @var array<int, array{bool, float}> */
    private static array $hasRules = [];

    public static function subscriberUpdated(Subscriber $subscriber): void
    {
        foreach (self::WATCHED_STANDARD_FIELDS as $column) {
            if (!$subscriber->wasChanged($column)) {
                continue;
            }

            self::raise(
                $subscriber,
                $column,
                self::text($subscriber->getOriginal($column)),
                self::text($subscriber->getAttribute($column)),
            );
        }
    }

    public static function fieldValueSaved(SubscriberFieldValue $value): void
    {
        $old = $value->wasRecentlyCreated ? null : self::text($value->getOriginal('value'));
        $new = self::text($value->value);

        if (!$value->wasRecentlyCreated && !$value->wasChanged('value')) {
            return;
        }

        if ($old === $new) {
            return;
        }

        // Most installations have no field rule at all: stop before any lookup.
        if (!self::anyAccountHasRules()) {
            return;
        }

        $subscriber = $value->relationLoaded('subscriber') ? $value->subscriber : Subscriber::find($value->subscriber_id);

        if (!$subscriber || !self::accountHasRules((int) $subscriber->user_id)) {
            return;
        }

        $name = CustomField::whereKey($value->custom_field_id)->value('name');

        if ($name) {
            self::raise($subscriber, $name, $old, $new);
        }
    }

    private static function raise(Subscriber $subscriber, string $field, ?string $old, ?string $new): void
    {
        if (self::$depth > 0 || $old === $new || !$subscriber->exists) {
            return;
        }

        if ($subscriber->created_at && $subscriber->created_at->gt(now()->subSeconds(self::NEW_SUBSCRIBER_SECONDS))) {
            return;
        }

        if (!self::accountHasRules((int) $subscriber->user_id)) {
            return;
        }

        self::$depth++;

        try {
            event(new SubscriberFieldUpdated($subscriber->id, (int) $subscriber->user_id, $field, $old, $new));
        } finally {
            self::$depth--;
        }
    }

    private static function accountHasRules(int $userId): bool
    {
        [$has, $at] = self::$hasRules[$userId] ?? [null, 0.0];

        if ($has === null || microtime(true) - $at > 60) {
            $has = AutomationRule::active()->forTrigger('field_updated')->forUser($userId)->exists();
            self::$hasRules[$userId] = [$has, microtime(true)];
        }

        return $has;
    }

    private static ?array $anyRules = null;

    private static function anyAccountHasRules(): bool
    {
        if (self::$anyRules === null || microtime(true) - self::$anyRules[1] > 60) {
            self::$anyRules = [AutomationRule::active()->forTrigger('field_updated')->exists(), microtime(true)];
        }

        return self::$anyRules[0];
    }

    /** Forget remembered rule checks (tests, long-running workers after a rule change). */
    public static function flush(): void
    {
        self::$hasRules = [];
        self::$anyRules = null;
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}

<?php

namespace App\Services\Automation;

use App\Models\AutomationRule;
use App\Models\ContactList;
use App\Models\CrmCompany;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CustomField;
use App\Models\Funnel;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\SubscriptionForm;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates an automation rule sent over the API: the web builder's rules
 * (AutomationRule::validationRules()) plus what the builder ensures through
 * its pickers — the trigger_config keys, conditions and action configs the
 * engine understands (AutomationCatalog), and ids that belong to the account.
 */
class AutomationRuleValidator
{
    /** Fields update_field writes directly on the subscriber. */
    protected const UPDATABLE_STANDARD_FIELDS = ['first_name', 'last_name', 'phone'];

    protected User $user;

    /** @var array<string, list<string>> */
    protected array $errors = [];

    /**
     * Validate the rule and return its attributes, with integer ids cast to int.
     *
     * @param  array  $payload  the complete rule (on update: the stored rule merged with the request)
     * @param  list<string>|null  $sentKeys  keys the request sent; the contents of the others are
     *                                       the stored rule's and are not checked again (null: all)
     *
     * @throws ValidationException
     */
    public function validate(array $payload, User $user, ?array $sentKeys = null): array
    {
        $this->user = $user;
        $this->errors = [];

        $rules = AutomationRule::validationRules() + [
            'conditions.*' => 'array',
            'conditions.*.type' => 'required|string',
            'actions.*' => 'array',
        ];

        $validator = Validator::make($payload, $rules);
        $validator->validate();

        $sent = fn (string $key) => $sentKeys === null || in_array($key, $sentKeys, true);
        $attributes = Arr::only($payload, array_keys(AutomationRule::validationRules()));

        if ($sent('trigger_event') || $sent('trigger_config')) {
            $attributes['trigger_config'] = $this->checkTrigger($payload['trigger_event'], $payload['trigger_config'] ?? []);
        }

        if ($sent('conditions')) {
            $attributes['conditions'] = $this->checkConditions($payload['conditions'] ?? []);
        }

        if ($sent('actions')) {
            $attributes['actions'] = $this->checkActions($payload['actions']);
        }

        if ($this->errors) {
            throw ValidationException::withMessages($this->errors);
        }

        return $attributes;
    }

    protected function checkTrigger(string $event, array $config): array
    {
        $spec = AutomationCatalog::triggers()[$event];

        if (!$spec['available']) {
            $usable = collect(AutomationCatalog::triggers())->filter(fn ($t) => $t['available'])->keys()->implode(', ');
            $this->fail('trigger_event', "The trigger {$event} is never emitted by NetSendo, so the rule would never run. Use one of: {$usable}.");
        }

        return $this->checkConfig('trigger_config', $config, $spec['config'], "trigger {$event}");
    }

    protected function checkConditions(array $conditions): array
    {
        $catalog = AutomationCatalog::conditions();
        $result = [];

        foreach (array_values($conditions) as $i => $condition) {
            $attribute = "conditions.{$i}";
            $type = $condition['type'];
            $spec = $catalog[$type] ?? null;

            if (!$spec) {
                $this->fail("{$attribute}.type", "Unknown condition type {$type}. Use one of: " . implode(', ', $this->availableKeys($catalog)) . '.');
                continue;
            }

            if (!$spec['available']) {
                $this->fail("{$attribute}.type", "The condition {$type} is not evaluated by the automation engine yet (it would always pass). Use one of: " . implode(', ', $this->availableKeys($catalog)) . '.');
                continue;
            }

            $normalized = ['type' => $type];

            if (isset($spec['field_spec'])) {
                $normalized['field'] = $this->checkValue("{$attribute}.field", $condition['field'] ?? null, $spec['field_spec']);
            }

            if ($spec['value_spec']) {
                $normalized['value'] = $this->checkValue("{$attribute}.value", $condition['value'] ?? null, $spec['value_spec']);
            }

            $result[] = $normalized;
        }

        return $result;
    }

    protected function checkActions(array $actions): array
    {
        $catalog = AutomationCatalog::actions();
        $result = [];

        foreach (array_values($actions) as $i => $action) {
            $attribute = "actions.{$i}";
            $type = $action['type'];
            $spec = $catalog[$type] ?? null;

            if (!$spec) {
                $this->fail("{$attribute}.type", "Unknown action type {$type}. Use one of: " . implode(', ', array_keys($catalog)) . '.');
                continue;
            }

            $config = $this->checkConfig("{$attribute}.config", $action['config'] ?? [], $spec['config'], "action {$type}", $type);

            if (!empty($spec['one_of_required']) && !Arr::hasAny(array_filter($config, fn ($v) => $v !== null && $v !== ''), $spec['one_of_required'])) {
                $this->fail("{$attribute}.config", "The action {$type} needs one of: " . implode(', ', $spec['one_of_required']) . '.');
            }

            $result[] = ['type' => $type, 'config' => $config];
        }

        return $result;
    }

    /**
     * Check a config object against its field specs: no unknown keys, required
     * keys present, values of the right type and owned by the account. Empty
     * values (null, '') mean "not set" and are dropped.
     */
    protected function checkConfig(string $attribute, array $config, array $fields, string $owner, ?string $actionType = null): array
    {
        $result = [];

        foreach ($config as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (!isset($fields[$key])) {
                $allowed = $fields ? implode(', ', array_keys($fields)) : 'none';
                $this->fail("{$attribute}.{$key}", "{$attribute}.{$key} is not used by the {$owner} (allowed keys: {$allowed}).");
                continue;
            }

            $result[$key] = $this->checkValue("{$attribute}.{$key}", $value, $fields[$key], $actionType);
        }

        foreach ($fields as $key => $spec) {
            if ($spec['required'] && !array_key_exists($key, $result) && !isset($this->errors["{$attribute}.{$key}"])) {
                $this->fail("{$attribute}.{$key}", "{$attribute}.{$key} is required for the {$owner}: {$spec['description']}.");
            }
        }

        return $result;
    }

    /**
     * Check one value against its spec and return it normalized.
     */
    protected function checkValue(string $attribute, mixed $value, array $spec, ?string $actionType = null): mixed
    {
        if ($value === null || $value === '') {
            if ($spec['required']) {
                $this->fail($attribute, "{$attribute} is required: {$spec['description']}.");
            }

            return null;
        }

        $valid = match ($spec['type']) {
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false && !is_bool($value),
            'number' => is_numeric($value),
            'string' => is_string($value) || is_int($value) || is_float($value),
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1'], true),
            'date' => is_string($value) && ($date = \DateTime::createFromFormat('!Y-m-d', $value)) && $date->format('Y-m-d') === $value,
            'enum' => is_string($value) && in_array($value, $spec['values'], true),
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL),
            'object' => is_array($value) && !array_is_list($value) && collect($value)->every(fn ($v) => is_scalar($v)),
            default => true,
        };

        if (!$valid) {
            $expected = match ($spec['type']) {
                'enum' => 'one of: ' . implode(', ', $spec['values']),
                'date' => 'a date as YYYY-MM-DD',
                'object' => 'an object of text values',
                'url' => 'an http(s) URL',
                'email' => 'an email address',
                default => "a {$spec['type']}",
            };
            $this->fail($attribute, "{$attribute} must be {$expected}.");

            return $value;
        }

        if ($spec['type'] === 'integer') {
            $value = (int) $value;
        } elseif ($spec['type'] === 'string') {
            $value = (string) $value;
        } elseif ($spec['type'] === 'boolean') {
            $value = (bool) $value;
        }

        if (isset($spec['ref']) && !$this->ownsReference($spec['ref'], $value, $actionType)) {
            $this->fail($attribute, $this->referenceError($attribute, $spec['ref'], $value, $actionType));
        }

        return $value;
    }

    protected function ownsReference(string $ref, mixed $value, ?string $actionType): bool
    {
        $userId = $this->user->id;

        return match ($ref) {
            'list' => ContactList::forUser($userId)->whereKey($value)->exists(),
            'tag' => Tag::where('user_id', $userId)->whereKey($value)->exists(),
            'message' => Message::where('user_id', $userId)->where('status', '!=', 'draft')->whereKey($value)->exists(),
            'funnel' => Funnel::forUser($userId)->whereKey($value)->exists(),
            'form' => SubscriptionForm::where('user_id', $userId)->whereKey($value)->exists(),
            'crm_pipeline' => CrmPipeline::where('user_id', $userId)->whereKey($value)->exists(),
            'crm_stage' => CrmStage::whereKey($value)->whereHas('pipeline', fn ($q) => $q->where('user_id', $userId))->exists(),
            'crm_company' => CrmCompany::where('user_id', $userId)->whereKey($value)->exists(),
            'user' => $this->isAccountUser((int) $value),
            'field' => $this->isKnownField((string) $value, $actionType === 'update_field'),
            default => true,
        };
    }

    protected function referenceError(string $attribute, string $ref, mixed $value, ?string $actionType): string
    {
        if ($ref === 'message' && Message::where('user_id', $this->user->id)->whereKey($value)->exists()) {
            return "{$attribute}: message {$value} is a draft. Use a message that is not a draft.";
        }

        if ($ref === 'field') {
            $standard = $actionType === 'update_field' ? self::UPDATABLE_STANDARD_FIELDS : Subscriber::STANDARD_FIELDS;

            return "{$attribute}: unknown field {$value}. Use one of " . implode(', ', $standard) . ' or a custom field name of your account (list_custom_fields).';
        }

        return "{$attribute}: no " . AutomationCatalog::REFERENCES[$ref] . " {$value} in your account.";
    }

    protected function isAccountUser(int $id): bool
    {
        $adminId = $this->user->getAdminUserId();

        return User::whereKey($id)
            ->where(fn ($q) => $q->where('id', $adminId)->orWhere('admin_user_id', $adminId))
            ->exists();
    }

    protected function isKnownField(string $name, bool $forUpdate): bool
    {
        $standard = $forUpdate
            ? self::UPDATABLE_STANDARD_FIELDS
            : array_merge(Subscriber::STANDARD_FIELDS, array_keys(Subscriber::STANDARD_FIELD_ALIASES));

        return in_array($name, $standard, true)
            || CustomField::where('user_id', $this->user->id)->where('name', $name)->exists();
    }

    protected function availableKeys(array $catalog): array
    {
        return array_keys(array_filter($catalog, fn ($entry) => $entry['available']));
    }

    protected function fail(string $attribute, string $message): void
    {
        $this->errors[$attribute][] = $message;
    }
}

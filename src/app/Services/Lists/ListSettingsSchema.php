<?php

namespace App\Services\Lists;

use Illuminate\Validation\Rule;

/**
 * The documented structure of a contact list's `settings` JSON (and of the
 * account-level list defaults that pre-fill it), shared by the API so lists
 * and defaults are validated exactly like the list editor in the browser, and
 * partial updates are merged instead of replacing the whole document.
 */
class ListSettingsSchema
{
    /**
     * Pages a list can redirect to after subscription events (list settings →
     * Pages). Each one is {type: system|custom|external, url, external_page_id}.
     */
    public const PAGES = [
        'confirmation',
        'success',
        'error',
        'exists_active',
        'exists_inactive',
        'activation_success',
        'activation_error',
        'unsubscribe',
        'unsubscribe_confirm',
        'unsubscribe_error',
        'unsubscribe_link_sent',
    ];

    public const PAGE_TYPES = ['system', 'custom', 'external'];

    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * Sections of the account defaults (Settings → Defaults). CRON is kept
     * out: it is stored as instance-wide CRON settings, not on the user.
     */
    public const DEFAULT_SECTIONS = ['subscription', 'sending', 'pages', 'advanced'];

    /**
     * Validation rules for a settings document under $prefix. Ownership of
     * referenced mailboxes, SMS providers and external pages is enforced, which
     * the browser form leaves to the UI.
     *
     * @param  bool  $forList  false for the account defaults, which have no
     *                         SMS sending or security options
     */
    public static function rules(int $userId, string $prefix = 'settings', bool $forList = true): array
    {
        $p = $prefix;

        $rules = [
            $p => ['nullable', 'array'],

            // Subscription
            "{$p}.subscription" => ['nullable', 'array'],
            "{$p}.subscription.double_optin" => ['boolean'],
            "{$p}.subscription.notification_email" => ['nullable', 'email'],
            "{$p}.subscription.delete_unconfirmed" => ['boolean'],
            "{$p}.subscription.delete_unconfirmed_after_days" => ['nullable', 'integer', 'min:1', 'max:365'],

            // Sending
            "{$p}.sending" => ['nullable', 'array'],
            "{$p}.sending.mailbox_id" => [
                'nullable', 'integer',
                Rule::exists('mailboxes', 'id')->where('user_id', $userId),
            ],
            "{$p}.sending.from_name" => ['nullable', 'string', 'max:255'],
            "{$p}.sending.reply_to" => ['nullable', 'email'],
            "{$p}.sending.company_name" => ['nullable', 'string', 'max:255'],
            "{$p}.sending.company_address" => ['nullable', 'string', 'max:255'],
            "{$p}.sending.company_city" => ['nullable', 'string', 'max:255'],
            "{$p}.sending.company_zip" => ['nullable', 'string', 'max:20'],
            "{$p}.sending.company_country" => ['nullable', 'string', 'max:255'],
            "{$p}.sending.headers" => ['nullable', 'array'],
            "{$p}.sending.headers.list_unsubscribe" => ['nullable', 'string'],
            "{$p}.sending.headers.list_unsubscribe_post" => ['nullable', 'string'],

            // Pages (redirects after signup / activation / unsubscribe)
            "{$p}.pages" => ['nullable', 'array', function (string $attribute, mixed $value, \Closure $fail) {
                if (!is_array($value)) {
                    return;
                }
                $unknown = array_diff(array_keys($value), self::PAGES);
                if ($unknown) {
                    $fail('Unknown page(s): ' . implode(', ', $unknown) . '. Allowed: ' . implode(', ', self::PAGES) . '.');
                }
            }],
            "{$p}.pages.*" => ['nullable', 'array'],
            "{$p}.pages.*.type" => ['nullable', 'string', Rule::in(self::PAGE_TYPES)],
            "{$p}.pages.*.url" => ['nullable', 'string', 'max:2048'],
            "{$p}.pages.*.external_page_id" => [
                'nullable', 'integer',
                Rule::exists('external_pages', 'id')->where('user_id', $userId),
            ],

            // Advanced
            "{$p}.advanced" => ['nullable', 'array'],
            "{$p}.advanced.facebook_integration" => ['nullable', 'string'],
            "{$p}.advanced.queue_days" => ['nullable', 'array'],
            "{$p}.advanced.queue_days.*" => ['string', Rule::in(self::DAYS)],
            "{$p}.advanced.bounce_analysis" => ['boolean'],
            "{$p}.advanced.bounce_scope" => ['nullable', 'in:list,global'],
            "{$p}.advanced.soft_bounce_threshold" => ['nullable', 'integer', 'min:1', 'max:10'],
        ];

        if ($forList) {
            $rules += [
                "{$p}.subscription.security_options" => ['nullable', 'array'],
                "{$p}.sending.sms_provider_id" => [
                    'nullable', 'integer',
                    Rule::exists('sms_providers', 'id')->where('user_id', $userId),
                ],
                "{$p}.sending.sms_settings" => ['nullable', 'string'],
            ];
        }

        return $rules;
    }

    /**
     * Deep-merge a partial settings document into the stored one: nested
     * objects are merged key by key, while lists (queue_days,
     * security_options...) and scalars are replaced as a whole — merging a
     * list by index would leave stale entries behind. An empty object sent for
     * a stored object (JSON `{}` decodes to an empty array) changes nothing.
     */
    public static function merge(array $base, array $patch): array
    {
        foreach ($patch as $key => $value) {
            $stored = $base[$key] ?? null;
            $storedIsObject = is_array($stored) && $stored !== [] && !array_is_list($stored);

            if ($storedIsObject && is_array($value) && ($value === [] || !array_is_list($value))) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}

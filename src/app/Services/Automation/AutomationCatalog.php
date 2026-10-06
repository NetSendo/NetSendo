<?php

namespace App\Services\Automation;

use App\Models\AutomationRule;
use App\Models\Subscriber;

/**
 * Machine-readable description of what an automation rule can contain: every
 * trigger event with the trigger_config keys AutomationService matches, every
 * condition type with what AutomationService::evaluateSingleCondition() reads,
 * and every action type with the config keys AutomationActionExecutor uses.
 *
 * It backs GET /api/v1/automations/options and the API's validation of a
 * rule's contents (AutomationRuleValidator), so agents build rules that can
 * actually fire. The labels are the builder's (AutomationRule constants).
 *
 * A field spec: type (integer|number|string|boolean|date|enum|url|email|object),
 * required, description, example, optional `values` (enum), optional `ref`
 * (the resource an id points at; it must belong to the rule's account).
 */
class AutomationCatalog
{
    /** Resource an id refers to, and the tool that lists it. */
    public const REFERENCES = [
        'list' => 'contact list id (list_contact_lists)',
        'tag' => 'tag id (list_tags)',
        'message' => 'message id — an email campaign or autoresponder that is not a draft (list_campaigns)',
        'funnel' => 'funnel id (list_funnels)',
        'form' => 'subscription form id',
        'crm_pipeline' => 'CRM pipeline id',
        'crm_stage' => 'CRM pipeline stage id',
        'crm_company' => 'CRM company id',
        'user' => 'user id of the account owner or one of its team members',
        'field' => 'subscriber field name: a standard field or a custom field name (list_custom_fields)',
    ];

    /** Placeholders filled into CRM task/deal titles and descriptions. */
    public const CRM_PLACEHOLDERS = [
        '{{subscriber_email}}', '{{subscriber_name}}', '{{first_name}}', '{{last_name}}',
        '{{deal_name}}', '{{deal_value}}', '{{stage_name}}', '{{pipeline_name}}',
        '{{task_title}}', '{{date}}', '{{datetime}}',
    ];

    /** Placeholders filled into the notify_admin message. */
    public const NOTIFY_PLACEHOLDERS = [
        '{{subscriber_email}}', '{{subscriber_name}}', '{{list_name}}', '{{trigger_event}}',
    ];

    public const CRM_TASK_TYPES = ['call', 'email', 'meeting', 'follow_up'];
    public const CRM_CONTACT_STATUSES = ['lead', 'prospect', 'customer', 'churned'];
    public const CRM_ACTIVITY_TYPES = ['note', 'call', 'email', 'meeting'];
    public const LINK_CATEGORIES = ['ai', 'marketing', 'sales', 'product', 'pricing', 'content'];

    /**
     * Trigger events: group, whether anything in NetSendo emits the event
     * (`available`), what it means, and the trigger_config keys that filter it.
     * An empty trigger_config matches every event of that type in the account.
     */
    public static function triggers(): array
    {
        $list = fn (string $what = 'the list the event happened on') => self::field('integer', false, "Only when {$what} is this list", 12, 'list');
        $message = self::field('integer', false, 'Only for this message', 345, 'message');
        $urlPattern = self::field('string', false, 'Page URL filter: exact URL, a pattern with * wildcards (https://shop.example.com/product/*), or text the URL contains when it has no http prefix (/pricing)', '/pricing');
        $productId = self::field('string', false, 'Only for this product id (exact text match)', 'SKU-123');
        $pipeline = self::field('integer', false, 'Only deals in this pipeline', 3, 'crm_pipeline');
        $valueMin = self::field('number', false, 'Only deals worth at least this much', 1000);
        $valueMax = self::field('number', false, 'Only deals worth at most this much', 50000);
        $owner = self::field('integer', false, 'Only when the CRM owner is this user', 1, 'user');
        $taskType = self::enum(self::CRM_TASK_TYPES, false, 'Only tasks of this type');

        $triggers = [
            'subscriber_signup' => ['group' => 'subscribers', 'description' => 'A subscriber joins a list (form, API, import, manual or bulk add, copy/move, resubscription)', 'config' => [
                'list_id' => $list('the list joined'),
                'form_id' => self::field('integer', false, 'Only signups through this form', 7, 'form'),
            ]],
            'subscriber_activated' => ['group' => 'subscribers', 'available' => false, 'description' => 'Not emitted by NetSendo yet — use subscriber_signup', 'config' => []],
            'list_join' => ['group' => 'subscribers', 'available' => false, 'description' => 'Not emitted by NetSendo yet — use subscriber_signup', 'config' => []],
            'subscriber_unsubscribed' => ['group' => 'subscribers', 'description' => 'A subscriber leaves a list', 'config' => [
                'list_id' => $list('the list left'),
            ]],
            'form_submitted' => ['group' => 'subscribers', 'description' => 'A subscription form is submitted', 'config' => [
                'form_id' => self::field('integer', false, 'Only this form', 7, 'form'),
                'list_id' => $list("the form's list"),
            ]],
            'tag_added' => ['group' => 'subscribers', 'description' => 'A tag is added to a subscriber', 'config' => [
                'tag_id' => self::field('integer', false, 'Only this tag', 5, 'tag'),
            ]],
            'tag_removed' => ['group' => 'subscribers', 'description' => 'A tag is removed from a subscriber', 'config' => [
                'tag_id' => self::field('integer', false, 'Only this tag', 5, 'tag'),
            ]],
            'field_updated' => ['group' => 'subscribers', 'available' => false, 'description' => 'Not emitted by NetSendo yet', 'config' => []],
            'subscriber_inactive' => ['group' => 'subscribers', 'available' => false, 'description' => 'Not emitted by NetSendo yet', 'config' => []],
            'email_opened' => ['group' => 'email', 'description' => 'A subscriber opens an email', 'config' => [
                'message_id' => $message,
                'list_id' => $list("the message's list"),
            ]],
            'email_clicked' => ['group' => 'email', 'description' => 'A subscriber clicks a link in an email', 'config' => [
                'message_id' => $message,
                'list_id' => $list("the message's list"),
                'link_category' => self::enum(self::LINK_CATEGORIES, false, 'Only links of this category'),
            ]],
            'email_bounced' => ['group' => 'email', 'description' => 'An email to the subscriber bounces', 'config' => [
                'message_id' => $message,
                'list_id' => $list("the message's list"),
            ]],
            'read_time_threshold' => ['group' => 'email', 'description' => 'A subscriber reads an email for at least the threshold', 'config' => [
                'read_time_threshold' => self::field('integer', false, 'Minimum read time in seconds', 30),
                'message_id' => $message,
            ]],
            'specific_link_clicked' => ['group' => 'email', 'available' => false, 'description' => 'Not emitted by NetSendo yet — use email_clicked', 'config' => []],
            'page_visited' => ['group' => 'tracking', 'description' => 'A known subscriber visits a tracked page', 'config' => [
                'url_pattern' => $urlPattern,
            ]],
            'date_reached' => ['group' => 'dates', 'description' => 'Runs once on the given date (daily CRON) for every subscriber of the account', 'config' => [
                'date' => self::field('date', true, 'The day to run, YYYY-MM-DD', '2026-12-24'),
            ]],
            'subscriber_birthday' => ['group' => 'dates', 'description' => "Runs daily for subscribers whose birthday is today (needs the subscriber's birthday field)", 'config' => []],
            'subscription_anniversary' => ['group' => 'dates', 'description' => 'Runs daily for subscribers whose signup anniversary is today', 'config' => []],
            'purchase' => ['group' => 'ecommerce', 'description' => 'A purchase is reported to the purchase webhook (POST /api/webhooks/purchase)', 'config' => [
                'product_id' => $productId,
                'product_category' => self::field('string', false, 'Only this product category (exact text match)', 'courses'),
                'min_value' => self::field('number', false, 'Only orders worth at least this much', 100),
                'max_value' => self::field('number', false, 'Only orders worth at most this much', 1000),
            ]],
            'pixel_page_visited' => ['group' => 'ecommerce', 'description' => 'NetSendo Pixel: an identified visitor views a page', 'config' => [
                'url_pattern' => $urlPattern,
            ]],
            'pixel_product_viewed' => ['group' => 'ecommerce', 'description' => 'NetSendo Pixel: an identified visitor views a product', 'config' => [
                'product_id' => $productId,
                'url_pattern' => $urlPattern,
            ]],
            'pixel_add_to_cart' => ['group' => 'ecommerce', 'description' => 'NetSendo Pixel: a product is added to the cart', 'config' => [
                'product_id' => $productId,
            ]],
            'pixel_checkout_started' => ['group' => 'ecommerce', 'description' => 'NetSendo Pixel: checkout is started', 'config' => []],
            'pixel_cart_abandoned' => ['group' => 'ecommerce', 'description' => 'NetSendo Pixel: a cart is abandoned', 'config' => [
                'product_id' => $productId,
            ]],
            'pixel_return_visit' => ['group' => 'ecommerce', 'available' => false, 'description' => 'Not emitted by NetSendo yet', 'config' => []],
            'crm_deal_stage_changed' => ['group' => 'crm', 'description' => 'A deal moves to another stage', 'config' => [
                'pipeline_id' => $pipeline,
                'from_stage_id' => self::field('integer', false, 'Only moves out of this stage', 10, 'crm_stage'),
                'to_stage_id' => self::field('integer', false, 'Only moves into this stage', 11, 'crm_stage'),
                'deal_value_min' => $valueMin,
                'deal_value_max' => $valueMax,
            ]],
            'crm_deal_won' => ['group' => 'crm', 'description' => 'A deal moves into a won stage', 'config' => [
                'pipeline_id' => $pipeline,
                'deal_value_min' => $valueMin,
                'deal_value_max' => $valueMax,
            ]],
            'crm_deal_lost' => ['group' => 'crm', 'description' => 'A deal moves into a lost stage', 'config' => [
                'pipeline_id' => $pipeline,
                'deal_value_min' => $valueMin,
                'deal_value_max' => $valueMax,
            ]],
            'crm_deal_created' => ['group' => 'crm', 'description' => 'A deal is created', 'config' => [
                'pipeline_id' => $pipeline,
                'stage_id' => self::field('integer', false, 'Only deals created in this stage', 10, 'crm_stage'),
                'owner_id' => $owner,
                'deal_value_min' => $valueMin,
                'deal_value_max' => $valueMax,
            ]],
            'crm_deal_idle' => ['group' => 'crm', 'description' => 'A deal has had no activity for a while (daily check)', 'config' => [
                'pipeline_id' => $pipeline,
                'stage_id' => self::field('integer', false, 'Only deals in this stage', 10, 'crm_stage'),
                'idle_days' => self::field('integer', false, 'Minimum days without activity', 14),
                'owner_id' => $owner,
                'deal_value_min' => $valueMin,
                'deal_value_max' => $valueMax,
            ]],
            'crm_task_completed' => ['group' => 'crm', 'description' => 'A CRM task is completed', 'config' => [
                'task_type' => $taskType,
                'owner_id' => $owner,
            ]],
            'crm_task_overdue' => ['group' => 'crm', 'description' => 'A CRM task becomes overdue', 'config' => [
                'task_type' => $taskType,
                'owner_id' => $owner,
            ]],
            'crm_contact_created' => ['group' => 'crm', 'description' => 'A CRM contact is created', 'config' => [
                'contact_status' => self::enum(self::CRM_CONTACT_STATUSES, false, 'Only contacts created with this status'),
                'owner_id' => $owner,
            ]],
            'crm_contact_status_changed' => ['group' => 'crm', 'description' => "A CRM contact's status changes", 'config' => [
                'contact_status' => self::enum(self::CRM_CONTACT_STATUSES, false, 'Only changes to this status'),
                'owner_id' => $owner,
            ]],
            'crm_score_threshold' => ['group' => 'crm', 'description' => "A contact's lead score crosses a threshold", 'config' => [
                'score_threshold' => self::field('integer', false, 'Score to compare the new score with', 50),
                'score_direction' => self::enum(['above', 'below'], false, 'above: new score >= threshold (default); below: new score <= threshold'),
            ]],
            'crm_activity_logged' => ['group' => 'crm', 'description' => 'An activity is logged on a CRM contact or deal', 'config' => [
                'activity_type' => self::enum(self::CRM_ACTIVITY_TYPES, false, 'Only activities of this type'),
            ]],
            'crm_contact_replied' => ['group' => 'crm', 'description' => 'A CRM contact replies to an email', 'config' => []],
        ];

        return self::withLabels($triggers, AutomationRule::TRIGGER_EVENTS);
    }

    /**
     * Condition types. Each condition is {type, value} — field conditions are
     * {type, field, value}. `value_spec` describes `value`, null when unused.
     */
    public static function conditions(): array
    {
        $fieldSpec = self::field('string', true, 'Field to test: a standard field (' . implode(', ', Subscriber::STANDARD_FIELDS) . ') or a custom field name', 'first_name', 'field');
        $textValue = self::field('string', true, 'Text to compare with (trimmed, case-insensitive)', 'Anna');

        $conditions = [
            'list_is' => ['description' => 'The list of the triggering event is this list', 'value_spec' => self::field('integer', true, 'List id', 12, 'list')],
            'list_is_not' => ['description' => 'The list of the triggering event is not this list', 'value_spec' => self::field('integer', true, 'List id', 12, 'list')],
            'tag_exists' => ['description' => 'The subscriber has this tag', 'value_spec' => self::field('integer', true, 'Tag id', 5, 'tag')],
            'tag_not_exists' => ['description' => 'The subscriber does not have this tag', 'value_spec' => self::field('integer', true, 'Tag id', 5, 'tag')],
            'field_equals' => ['description' => 'The field equals the value', 'field_spec' => $fieldSpec, 'value_spec' => $textValue],
            'field_not_equals' => ['description' => 'The field differs from the value', 'field_spec' => $fieldSpec, 'value_spec' => $textValue],
            'field_contains' => ['description' => 'The field contains the value', 'field_spec' => $fieldSpec, 'value_spec' => $textValue],
            'field_is_empty' => ['description' => 'The field is empty', 'field_spec' => $fieldSpec, 'value_spec' => null],
            'field_is_not_empty' => ['description' => 'The field is not empty', 'field_spec' => $fieldSpec, 'value_spec' => null],
            'email_opened_message' => ['description' => 'The subscriber has opened this message', 'value_spec' => self::field('integer', true, 'Message id', 345, 'message')],
            'email_clicked_message' => ['description' => 'The subscriber has clicked a link in this message', 'value_spec' => self::field('integer', true, 'Message id', 345, 'message')],
            'subscribed_days_ago' => ['description' => 'The subscriber joined the event\'s list at least this many days ago (needs an event with a list)', 'value_spec' => self::field('integer', true, 'Days', 7)],
            'source_is' => ['description' => 'The signup source of the event is this value (subscriber_signup)', 'value_spec' => self::field('string', true, 'Source, e.g. form, api, import, manual, bulk_add, bulk_copy, bulk_move, coregistration, webinar_registration', 'form')],
        ];

        foreach (['crm_deal_in_stage', 'crm_contact_has_deals', 'crm_score_above', 'crm_score_below', 'crm_contact_status_is', 'crm_owned_by', 'crm_pipeline_is'] as $type) {
            $conditions[$type] = [
                'available' => false,
                'description' => 'Not evaluated by the automation engine yet (it would always pass), so it cannot be used',
                'value_spec' => null,
            ];
        }

        return self::withLabels($conditions, AutomationRule::CONDITION_TYPES);
    }

    /**
     * Action types. Each action is {type, config}. `one_of_required` lists
     * keys of which at least one must be set.
     */
    public static function actions(): array
    {
        $funnel = self::field('integer', true, 'Funnel id', 4, 'funnel');
        $owner = self::field('integer', false, 'CRM owner (default: the deal/contact owner, else the account)', 1, 'user');

        $actions = [
            'send_email' => ['description' => 'Queue an email message to the subscriber (skipped for undeliverable subscribers)', 'config' => [
                'message_id' => self::field('integer', true, 'Message to send (not a draft)', 345, 'message'),
            ]],
            'add_tag' => ['description' => 'Add a tag to the subscriber', 'one_of_required' => ['tag_id', 'tag_name'], 'config' => [
                'tag_id' => self::field('integer', false, 'Existing tag id', 5, 'tag'),
                'tag_name' => self::field('string', false, 'Tag name; created in the account when missing', 'vip'),
            ]],
            'remove_tag' => ['description' => 'Remove a tag from the subscriber', 'config' => [
                'tag_id' => self::field('integer', true, 'Tag id', 5, 'tag'),
            ]],
            'move_to_list' => ['description' => "Add the subscriber to the list and remove them from the event's list", 'config' => [
                'list_id' => self::field('integer', true, 'Target list', 12, 'list'),
            ]],
            'copy_to_list' => ['description' => 'Add the subscriber to another list too', 'config' => [
                'list_id' => self::field('integer', true, 'Target list', 12, 'list'),
            ]],
            'unsubscribe' => ['description' => 'Unsubscribe the subscriber from a list', 'config' => [
                'list_id' => self::field('integer', false, "List to leave (default: the event's list)", 12, 'list'),
            ]],
            'call_webhook' => ['description' => 'Send the event and the subscriber (id, email, names, phone) as JSON to a URL', 'config' => [
                'url' => self::field('url', true, 'Webhook URL', 'https://example.com/hooks/netsendo'),
                'method' => self::enum(['POST', 'PUT', 'PATCH', 'GET', 'DELETE'], false, 'HTTP method (default POST)'),
                'headers' => self::field('object', false, 'Extra HTTP headers', ['X-Secret' => 'abc']),
            ]],
            'start_funnel' => ['description' => 'Enroll the subscriber in a funnel', 'config' => ['funnel_id' => $funnel]],
            'start_sequence' => ['description' => 'Alias of start_funnel', 'config' => ['funnel_id' => $funnel]],
            'stop_funnel' => ['description' => 'Remove the subscriber from a funnel', 'config' => ['funnel_id' => $funnel]],
            'stop_sequence' => ['description' => 'Alias of stop_funnel', 'config' => ['funnel_id' => $funnel]],
            'update_field' => ['description' => 'Set a subscriber field', 'config' => [
                'field' => self::field('string', true, 'first_name, last_name, phone, or a custom field name of the account', 'company', 'field'),
                'value' => self::field('string', false, 'New value (default: empty)', 'ACME'),
            ]],
            'add_score' => ['description' => "Add points to the subscriber's CRM lead score (creates the CRM contact when auto-convert is on)", 'config' => [
                'points' => self::field('integer', true, 'Points to add (negative to subtract)', 10),
            ]],
            'notify_admin' => ['description' => 'Email a notification', 'config' => [
                'email' => self::field('email', true, 'Recipient', 'owner@example.com'),
                'subject' => self::field('string', false, 'Subject', 'New VIP signup'),
                'message' => self::field('string', false, 'Plain-text body; placeholders: ' . implode(', ', self::NOTIFY_PLACEHOLDERS), '{{subscriber_email}} joined {{list_name}}'),
            ]],
            'crm_create_task' => ['description' => 'Create a CRM task', 'config' => [
                'title' => self::field('string', false, 'Title; placeholders: ' . implode(', ', self::CRM_PLACEHOLDERS), 'Call {{first_name}}'),
                'description' => self::field('string', false, 'Description (same placeholders)', 'Follow up on {{deal_name}}'),
                'task_type' => self::enum(self::CRM_TASK_TYPES, false, 'Task type (default follow_up)'),
                'priority' => self::enum(['high', 'medium', 'low'], false, 'Priority (default medium)'),
                'due_days' => self::field('integer', false, 'Due in this many days (default 2)', 2),
                'owner_id' => $owner,
            ]],
            'crm_update_score' => ['description' => "Change the CRM contact's lead score", 'config' => [
                'score_delta' => self::field('integer', false, 'Points to add or subtract', 10),
                'set_absolute' => self::field('boolean', false, 'Set the score to absolute_score instead', false),
                'absolute_score' => self::field('integer', false, 'Score to set when set_absolute is true', 50),
            ]],
            'crm_move_deal' => ['description' => "Move the event's deal to a stage", 'config' => [
                'stage_id' => self::field('integer', true, 'Target stage', 11, 'crm_stage'),
            ]],
            'crm_assign_owner' => ['description' => "Assign the event's deal or contact to a user", 'config' => [
                'owner_id' => self::field('integer', true, 'New owner', 1, 'user'),
            ]],
            'crm_convert_to_contact' => ['description' => 'Create a CRM contact from the subscriber', 'config' => [
                'status' => self::enum(self::CRM_CONTACT_STATUSES, false, 'Status (default lead)'),
                'source' => self::field('string', false, 'Source (default: the event source or automation)', 'automation'),
                'owner_id' => $owner,
                'company_id' => self::field('integer', false, 'CRM company', 2, 'crm_company'),
            ]],
            'crm_log_activity' => ['description' => "Log an activity on the event's deal or contact", 'config' => [
                'activity_type' => self::enum(self::CRM_ACTIVITY_TYPES, false, 'Activity type (default note)'),
                'content' => self::field('string', false, 'Activity text', 'Joined the webinar list'),
            ]],
            'crm_update_contact_status' => ['description' => "Change the event's CRM contact status", 'config' => [
                'status' => self::enum(self::CRM_CONTACT_STATUSES, true, 'New status'),
            ]],
            'crm_create_deal' => ['description' => 'Create a CRM deal for the subscriber (creates the CRM contact when auto-convert is on)', 'config' => [
                'name' => self::field('string', false, 'Deal name; placeholders: ' . implode(', ', self::CRM_PLACEHOLDERS), 'Deal: {{subscriber_name}}'),
                'value' => self::field('number', false, 'Deal value', 1500),
                'currency' => self::field('string', false, 'Currency (default PLN)', 'PLN'),
                'pipeline_id' => self::field('integer', false, "Pipeline (default: the account's first)", 3, 'crm_pipeline'),
                'stage_id' => self::field('integer', false, "Stage (default: the pipeline's first)", 10, 'crm_stage'),
                'owner_id' => $owner,
                'expected_close_days' => self::field('integer', false, 'Expected close in this many days', 30),
                'company_id' => self::field('integer', false, 'CRM company', 2, 'crm_company'),
            ]],
        ];

        return self::withLabels($actions, AutomationRule::ACTION_TYPES);
    }

    /**
     * The whole catalogue, as GET /api/v1/automations/options returns it.
     */
    public static function options(): array
    {
        $listed = fn (array $entries) => collect($entries)
            ->map(fn (array $entry, string $key) => ['key' => $key] + $entry)
            ->values()
            ->all();

        return [
            'rule_shape' => [
                'name' => 'string, required, max 255',
                'description' => 'string, optional, max 1000',
                'trigger_event' => 'one of triggers[].key (available: true)',
                'trigger_config' => 'object with the chosen trigger\'s config keys; {} matches every event of that type in your account',
                'conditions' => 'array of {type, value} ({type, field, value} for field_* types); [] for none',
                'condition_logic' => 'all (every condition must pass, default) or any (at least one)',
                'actions' => 'array of {type, config}, at least one; run in order',
                'is_active' => 'boolean (default true)',
                'limit_per_subscriber' => 'boolean: limit how often the rule runs per subscriber',
                'limit_count' => 'integer >= 1: successful runs allowed per subscriber in limit_period',
                'limit_period' => implode('|', array_keys(AutomationRule::LIMIT_PERIODS)),
            ],
            'example' => [
                'name' => 'Welcome VIP signups',
                'trigger_event' => 'subscriber_signup',
                'trigger_config' => ['list_id' => 12],
                'conditions' => [['type' => 'field_contains', 'field' => 'email', 'value' => '@acme.com']],
                'condition_logic' => 'all',
                'actions' => [
                    ['type' => 'add_tag', 'config' => ['tag_name' => 'vip']],
                    ['type' => 'send_email', 'config' => ['message_id' => 345]],
                ],
                'is_active' => true,
            ],
            'triggers' => $listed(self::triggers()),
            'conditions' => $listed(self::conditions()),
            'actions' => $listed(self::actions()),
            'condition_logic' => ['all', 'any'],
            'limit_periods' => array_keys(AutomationRule::LIMIT_PERIODS),
            'references' => self::REFERENCES,
            'standard_fields' => Subscriber::STANDARD_FIELDS,
        ];
    }

    protected static function field(string $type, bool $required, string $description, mixed $example = null, ?string $ref = null): array
    {
        return array_filter([
            'type' => $type,
            'required' => $required,
            'description' => $description,
            'example' => $example,
            'ref' => $ref,
        ], fn ($value) => $value !== null);
    }

    protected static function enum(array $values, bool $required, string $description): array
    {
        return [
            'type' => 'enum',
            'required' => $required,
            'description' => $description,
            'values' => $values,
            'example' => $values[0],
        ];
    }

    /**
     * Add the builder label and the `available` default to every entry, and
     * keep the order of the model constants (any key missing there is kept).
     */
    protected static function withLabels(array $entries, array $labels): array
    {
        $result = [];

        foreach (array_keys($labels + $entries) as $key) {
            $entry = $entries[$key] ?? ['description' => '', 'config' => []];
            $result[$key] = ['label' => $labels[$key] ?? $key, 'available' => $entry['available'] ?? true] + $entry;
        }

        return $result;
    }
}

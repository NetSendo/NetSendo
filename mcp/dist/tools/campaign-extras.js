/**
 * NetSendo MCP Server - Campaign editor extras
 *
 * The editor fields shared by create_campaign / update_campaign (trigger,
 * tags, translations, tracked links, CRM contacts, audience field filters,
 * A/B config) and the campaign actions: test send, preview, duplicate,
 * activation, recipient count, re-sending failed / missed recipients.
 */
import { z } from 'zod';
import { ok, fail, compact } from './helpers.js';
export const TRIGGER_TYPES = [
    'signup',
    'anniversary',
    'birthday',
    'inactivity',
    'page_visit',
    'custom',
    'recent_subscribers',
    'opened_message',
    'not_opened_message',
];
const FIELD_FILTER_OPERATORS = [
    'any_of', 'none_of', 'contains', 'not_contains', 'starts_with', 'ends_with',
    'is_set', 'is_empty', 'gt', 'gte', 'lt', 'lte', 'between',
];
const fieldFilter = z.object({
    custom_field_id: z.number().describe('Custom field ID (list_custom_fields)'),
    operator: z.enum(FIELD_FILTER_OPERATORS),
    values: z
        .array(z.string())
        .optional()
        .describe('Values to compare with; omit for is_set/is_empty; two values for between'),
});
/**
 * Optional editor fields accepted by POST/PUT /messages. Every key is
 * optional; on update an omitted key leaves the stored value untouched and an
 * empty array clears it.
 */
export const campaignEditorShape = {
    template_id: z
        .number()
        .optional()
        .describe('Template ID (list_templates) — your own or a public system template. It is only a REFERENCE: sending always uses the campaign\'s own `content`. To build from a template, call get_template and copy its `html` into `content` (then edit it).'),
    send_in_subscriber_timezone: z
        .boolean()
        .optional()
        .describe('Send at time_of_day in each subscriber\'s own timezone instead of the campaign timezone'),
    trigger_type: z
        .enum(TRIGGER_TYPES)
        .nullable()
        .optional()
        .describe(`Optional trigger (mostly for autoresponders). Saving it creates/updates an automation rule "Auto: <subject>" that sends this message; null removes the trigger and its rule. The rule is active only while the message is scheduled/active.
- signup: when someone joins the list (trigger_config.list_id, defaults to the first recipient list)
- anniversary: yearly on the subscription date
- birthday: on the subscriber's birthday field
- inactivity: after trigger_config.inactive_days without activity (7/14/30/60/90)
- page_visit: when a tracked page matching trigger_config.url_pattern is visited
- custom: when the tag trigger_config.tag_id is added
- recent_subscribers: audience filter — only people who joined in the last trigger_config.recent_days days
- opened_message / not_opened_message: audience filter — only people who did / did not open trigger_config.message_id`),
    trigger_config: z
        .object({
        list_id: z.number().optional(),
        message_id: z.number().optional().describe('For opened_message / not_opened_message: a sent campaign ID'),
        tag_id: z.number().optional().describe('For custom (list_tags)'),
        inactive_days: z.number().optional().describe('For inactivity'),
        recent_days: z.number().optional().describe('For recent_subscribers'),
        url_pattern: z.string().optional().describe('For page_visit, e.g. "/pricing*"'),
    })
        .nullable()
        .optional()
        .describe('Settings for trigger_type (see trigger_type). All referenced IDs must belong to this account.'),
    tag_ids: z
        .array(z.number())
        .optional()
        .describe('Campaign tags for reporting (list_tags). Replaces the current set.'),
    translations: z
        .array(z.object({
        language: z.string().max(5).describe('Language code, e.g. "en", "de"'),
        subject: z.string(),
        preheader: z.string().optional(),
        content: z.string().optional().describe('HTML for this language; omit to reuse the main content'),
    }))
        .optional()
        .describe('Multi-language versions (subscriber language picks one). Replaces all translations; [] removes them.'),
    tracked_links: z
        .array(z.object({
        url: z.string().describe('Exact link URL as it appears in the content'),
        tracking_enabled: z.boolean().optional().describe('Default true'),
        share_data_enabled: z.boolean().optional().describe('Append subscriber data to the URL'),
        shared_fields: z.array(z.string()).optional().describe('Fields to append, e.g. ["email","first_name"]'),
        subscribe_to_list_ids: z.array(z.number()).optional().describe('Add the clicker to these lists'),
        unsubscribe_from_list_ids: z.array(z.number()).optional().describe('Remove the clicker from these lists'),
    }))
        .optional()
        .describe('Per-link click settings. Replaces the whole set: links not listed lose their settings.'),
    crm_contact_ids: z.array(z.number()).optional().describe('Individual CRM contacts to include besides the lists'),
    excluded_crm_contact_ids: z.array(z.number()).optional().describe('CRM contacts to exclude'),
    include_field_filters: z
        .array(fieldFilter)
        .optional()
        .describe('Audience narrowing by custom field: only list members matching these are sent to. [] clears.'),
    include_field_filter_match: z.enum(['all', 'any']).optional().describe('Combine include filters with AND (all) or OR (any)'),
    exclude_field_filters: z
        .array(fieldFilter)
        .optional()
        .describe('Drop subscribers matching these (combined with excluded lists: only matching members of those lists are dropped). [] clears.'),
    exclude_field_filter_match: z.enum(['all', 'any']).optional(),
    ab_test_config: z
        .object({
        enabled: z.boolean(),
        test_type: z.enum(['subject', 'content', 'sender', 'send_time', 'full']).optional(),
        winning_metric: z.enum(['open_rate', 'click_rate', 'conversion_rate']).optional(),
        sample_percentage: z.number().min(5).max(50).optional(),
        test_duration_hours: z.number().min(1).max(72).optional(),
        auto_select_winner: z.boolean().optional(),
        confidence_threshold: z.number().min(60).max(99).optional(),
        variants: z
            .array(z.object({
            variant_letter: z.string().max(1).describe('"A", "B", ...'),
            subject: z.string().optional(),
            preheader: z.string().optional(),
            is_control: z.boolean().optional().describe('Control uses the campaign subject/preheader'),
        }))
            .optional(),
    })
        .optional()
        .describe('Inline A/B subject test (needs enabled=true and at least 2 variants; enabled=false removes a draft test). For full control use the *_ab_test tools.'),
};
/** Compact, agent-oriented view of a campaign returned by the API. */
export function summarizeCampaign(c, contentChars = 500) {
    const content = typeof c.content === 'string' ? c.content : '';
    const lists = c.contact_lists ?? [];
    const excluded = c.excluded_lists ?? [];
    const translations = c.translations ?? [];
    return {
        id: c.id,
        subject: c.subject,
        preheader: c.preheader,
        channel: c.channel,
        type: c.type,
        status: c.status,
        is_active: c.is_active,
        mailbox_id: c.mailbox_id,
        template_id: c.template_id,
        day: c.day,
        time_of_day: c.time_of_day,
        timezone: c.timezone,
        send_in_subscriber_timezone: c.send_in_subscriber_timezone,
        scheduled_at: c.scheduled_at,
        trigger_type: c.trigger_type,
        trigger_config: c.trigger_config,
        automation_rule: c.automation_rule,
        contact_lists: lists.map((l) => ({ id: l.id, name: l.name })),
        excluded_lists: excluded.map((l) => ({ id: l.id, name: l.name })),
        crm_contact_ids: c.crm_contact_ids,
        excluded_crm_contact_ids: c.excluded_crm_contact_ids,
        field_filters: c.field_filters,
        include_field_filter_match: c.include_field_filter_match,
        exclude_field_filter_match: c.exclude_field_filter_match,
        tag_ids: c.tag_ids,
        translations: translations.map((t) => ({ language: t.language, subject: t.subject, preheader: t.preheader })),
        tracked_links: c.tracked_links,
        ab_test_config: c.ab_test_config,
        sent_count: c.sent_count,
        planned_recipients: c.planned_recipients_count,
        content_length: content.length,
        content: contentChars > 0 && content.length > contentChars ? `${content.substring(0, contentChars)}...` : content,
    };
}
export function registerCampaignExtraTools(server, api) {
    server.tool('send_campaign_test', `Send a TEST email of a campaign to up to 5 addresses (subject gets a "[TEST] " prefix). Nothing is queued and stats are not affected.

Placeholders ([[first_name]], [[email]], ...) are filled from: subscriber_id if given, else the subscriber with the test address, else the first subscriber on the campaign's lists, else sample data (Jan Kowalski). Uses the campaign's mailbox (or its list/user default) unless mailbox_id is given. Email campaigns only. Needs messages:write.`, {
        campaign_id: z.number().describe('Campaign ID'),
        emails: z.array(z.string().email()).min(1).max(5).describe('Recipient addresses for the test'),
        subscriber_id: z.number().optional().describe('Personalise with this subscriber\'s data'),
        mailbox_id: z.number().optional().describe('Send through this mailbox instead (list_mailboxes)'),
        language: z.string().optional().describe('Send this translation (e.g. "en") instead of the main version'),
    }, async ({ campaign_id, ...body }) => {
        try {
            const res = await api.request('post', `/messages/${campaign_id}/test`, { data: compact(body) });
            return ok({ success: true, message: res.message, ...res.data });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('preview_campaign', `Render a campaign exactly as a recipient sees it: subject and full HTML with placeholders resolved and the preheader injected. Read-only (messages:read).

Personalised with subscriber_id / subscriber_email if given (must be on this account), else the first subscriber on the campaign's lists, else sample data. The result lists unresolved_placeholders (unknown [[tags]]) — fix those before sending. Use language to preview a translation.`, {
        campaign_id: z.number().describe('Campaign ID'),
        subscriber_id: z.number().optional(),
        subscriber_email: z.string().email().optional(),
        language: z.string().optional().describe('Preview this translation, e.g. "en"'),
        max_html_chars: z
            .number()
            .optional()
            .describe('Truncate the returned HTML to this many characters (default 20000, 0 = no limit)'),
    }, async ({ campaign_id, max_html_chars, ...params }) => {
        try {
            const res = await api.request('post', `/messages/${campaign_id}/preview`, { data: compact(params) });
            const limit = max_html_chars ?? 20000;
            const html = res.data.html ?? '';
            return ok({
                ...res.data,
                html_length: html.length,
                html: limit > 0 && html.length > limit ? `${html.substring(0, limit)}... [truncated]` : html,
            });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('duplicate_campaign', 'Copy a campaign as a new DRAFT (subject "[KOPIA] <subject>" unless you pass one). Lists, exclusions, field filters, tracked links, translations, trigger settings and content are copied; sending state and stats are not. Edit the copy with update_campaign.', {
        campaign_id: z.number().describe('Campaign ID to copy'),
        subject: z.string().optional().describe('Subject for the copy'),
    }, async ({ campaign_id, subject }) => {
        try {
            const res = await api.request('post', `/messages/${campaign_id}/duplicate`, {
                data: compact({ subject }),
            });
            return ok({ success: true, message: res.message, campaign: summarizeCampaign(res.data, 0) });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('set_campaign_active', `Activate or pause an AUTORESPONDER (queue message). Activating a draft promotes it to "scheduled" so new subscribers get it after its day offset, schedules the current subscribers whose send time is still ahead, and enables its trigger rule. Deactivating stops further sends. Omit is_active to toggle. Broadcasts are rejected — use send_campaign / schedule_campaign.`, {
        campaign_id: z.number().describe('Autoresponder campaign ID'),
        is_active: z.boolean().optional().describe('true = activate, false = pause; omit to toggle'),
    }, async ({ campaign_id, is_active }) => {
        try {
            const res = await api.request('post', `/messages/${campaign_id}/toggle-active`, {
                data: compact({ is_active }),
            });
            return ok({ success: true, message: res.message, ...res.data, warnings: res.warnings });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('get_campaign_recipient_count', 'Count who a campaign would go to right now: its lists plus selected CRM contacts, minus exclusions, field filters and inactive/unsubscribed people (deduplicated by email). For autoresponders also skipped_count (subscribers who joined before its day offset and missed it) and the queue breakdown. Read-only.', {
        campaign_id: z.number().describe('Campaign ID'),
    }, async ({ campaign_id }) => {
        try {
            const res = await api.request('get', `/messages/${campaign_id}/recipients-count`);
            return ok(res.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('resend_campaign_failed', 'Re-queue every recipient whose delivery of this campaign FAILED (e.g. provider quota hit). Real emails are sent: call once without confirm to see failed_count, ask the user, then repeat with confirm=true.', {
        campaign_id: z.number().describe('Campaign ID'),
        confirm: z.boolean().optional().describe('Must be true to actually re-queue'),
    }, async ({ campaign_id, confirm }) => {
        try {
            const res = await api.request('post', `/messages/${campaign_id}/resend-failed`, {
                data: compact({ confirm }),
            });
            return ok({ success: true, message: res.message, ...res.data });
        }
        catch (error) {
            return fail(error, 'Nothing was sent. Tell the user how many failed recipients would be re-sent and, once they approve, repeat with confirm=true.');
        }
    });
    server.tool('send_campaign_to_missed', 'For an ACTIVE autoresponder: send it now to the subscribers who missed it (they were already on the list longer than its day offset when it was created/activated). Real emails are sent: call once without confirm to see missed_count, ask the user, then repeat with confirm=true.', {
        campaign_id: z.number().describe('Autoresponder campaign ID'),
        confirm: z.boolean().optional().describe('Must be true to actually send'),
    }, async ({ campaign_id, confirm }) => {
        try {
            const res = await api.request('post', `/messages/${campaign_id}/send-to-missed`, {
                data: compact({ confirm }),
            });
            return ok({ success: true, message: res.message, ...res.data });
        }
        catch (error) {
            return fail(error, 'Nothing was sent. Tell the user how many missed subscribers would receive it and, once they approve, repeat with confirm=true.');
        }
    });
}
//# sourceMappingURL=campaign-extras.js.map
/**
 * NetSendo MCP Server - Campaign editor extras
 *
 * The editor fields shared by create_campaign / update_campaign (trigger,
 * tags, translations, tracked links, CRM contacts, audience field filters,
 * A/B config) and the campaign actions: test send, preview, duplicate,
 * activation, recipient count, re-sending failed / missed recipients.
 */
import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
export declare const TRIGGER_TYPES: readonly ["signup", "anniversary", "birthday", "inactivity", "page_visit", "custom", "recent_subscribers", "opened_message", "not_opened_message"];
/**
 * Optional editor fields accepted by POST/PUT /messages. Every key is
 * optional; on update an omitted key leaves the stored value untouched and an
 * empty array clears it.
 */
export declare const campaignEditorShape: {
    template_id: z.ZodOptional<z.ZodNumber>;
    send_in_subscriber_timezone: z.ZodOptional<z.ZodBoolean>;
    trigger_type: z.ZodOptional<z.ZodNullable<z.ZodEnum<{
        signup: "signup";
        page_visit: "page_visit";
        custom: "custom";
        anniversary: "anniversary";
        birthday: "birthday";
        inactivity: "inactivity";
        recent_subscribers: "recent_subscribers";
        opened_message: "opened_message";
        not_opened_message: "not_opened_message";
    }>>>;
    trigger_config: z.ZodOptional<z.ZodNullable<z.ZodObject<{
        list_id: z.ZodOptional<z.ZodNumber>;
        message_id: z.ZodOptional<z.ZodNumber>;
        tag_id: z.ZodOptional<z.ZodNumber>;
        inactive_days: z.ZodOptional<z.ZodNumber>;
        recent_days: z.ZodOptional<z.ZodNumber>;
        url_pattern: z.ZodOptional<z.ZodString>;
    }, z.core.$strip>>>;
    tag_ids: z.ZodOptional<z.ZodArray<z.ZodNumber>>;
    translations: z.ZodOptional<z.ZodArray<z.ZodObject<{
        language: z.ZodString;
        subject: z.ZodString;
        preheader: z.ZodOptional<z.ZodString>;
        content: z.ZodOptional<z.ZodString>;
    }, z.core.$strip>>>;
    tracked_links: z.ZodOptional<z.ZodArray<z.ZodObject<{
        url: z.ZodString;
        tracking_enabled: z.ZodOptional<z.ZodBoolean>;
        share_data_enabled: z.ZodOptional<z.ZodBoolean>;
        shared_fields: z.ZodOptional<z.ZodArray<z.ZodString>>;
        subscribe_to_list_ids: z.ZodOptional<z.ZodArray<z.ZodNumber>>;
        unsubscribe_from_list_ids: z.ZodOptional<z.ZodArray<z.ZodNumber>>;
    }, z.core.$strip>>>;
    crm_contact_ids: z.ZodOptional<z.ZodArray<z.ZodNumber>>;
    excluded_crm_contact_ids: z.ZodOptional<z.ZodArray<z.ZodNumber>>;
    include_field_filters: z.ZodOptional<z.ZodArray<z.ZodObject<{
        custom_field_id: z.ZodNumber;
        operator: z.ZodEnum<{
            any_of: "any_of";
            none_of: "none_of";
            contains: "contains";
            not_contains: "not_contains";
            starts_with: "starts_with";
            ends_with: "ends_with";
            is_set: "is_set";
            is_empty: "is_empty";
            gt: "gt";
            gte: "gte";
            lt: "lt";
            lte: "lte";
            between: "between";
        }>;
        values: z.ZodOptional<z.ZodArray<z.ZodString>>;
    }, z.core.$strip>>>;
    include_field_filter_match: z.ZodOptional<z.ZodEnum<{
        all: "all";
        any: "any";
    }>>;
    exclude_field_filters: z.ZodOptional<z.ZodArray<z.ZodObject<{
        custom_field_id: z.ZodNumber;
        operator: z.ZodEnum<{
            any_of: "any_of";
            none_of: "none_of";
            contains: "contains";
            not_contains: "not_contains";
            starts_with: "starts_with";
            ends_with: "ends_with";
            is_set: "is_set";
            is_empty: "is_empty";
            gt: "gt";
            gte: "gte";
            lt: "lt";
            lte: "lte";
            between: "between";
        }>;
        values: z.ZodOptional<z.ZodArray<z.ZodString>>;
    }, z.core.$strip>>>;
    exclude_field_filter_match: z.ZodOptional<z.ZodEnum<{
        all: "all";
        any: "any";
    }>>;
    ab_test_config: z.ZodOptional<z.ZodObject<{
        enabled: z.ZodBoolean;
        test_type: z.ZodOptional<z.ZodEnum<{
            subject: "subject";
            content: "content";
            sender: "sender";
            send_time: "send_time";
            full: "full";
        }>>;
        winning_metric: z.ZodOptional<z.ZodEnum<{
            open_rate: "open_rate";
            click_rate: "click_rate";
            conversion_rate: "conversion_rate";
        }>>;
        sample_percentage: z.ZodOptional<z.ZodNumber>;
        test_duration_hours: z.ZodOptional<z.ZodNumber>;
        auto_select_winner: z.ZodOptional<z.ZodBoolean>;
        confidence_threshold: z.ZodOptional<z.ZodNumber>;
        variants: z.ZodOptional<z.ZodArray<z.ZodObject<{
            variant_letter: z.ZodString;
            subject: z.ZodOptional<z.ZodString>;
            preheader: z.ZodOptional<z.ZodString>;
            is_control: z.ZodOptional<z.ZodBoolean>;
        }, z.core.$strip>>>;
    }, z.core.$strip>>;
};
/** Compact, agent-oriented view of a campaign returned by the API. */
export declare function summarizeCampaign(c: Record<string, unknown>, contentChars?: number): {
    id: unknown;
    subject: unknown;
    preheader: unknown;
    channel: unknown;
    type: unknown;
    status: unknown;
    is_active: unknown;
    mailbox_id: unknown;
    template_id: unknown;
    day: unknown;
    time_of_day: unknown;
    timezone: unknown;
    send_in_subscriber_timezone: unknown;
    scheduled_at: unknown;
    trigger_type: unknown;
    trigger_config: unknown;
    automation_rule: unknown;
    contact_lists: {
        id: number;
        name: string;
    }[];
    excluded_lists: {
        id: number;
        name: string;
    }[];
    crm_contact_ids: unknown;
    excluded_crm_contact_ids: unknown;
    field_filters: unknown;
    include_field_filter_match: unknown;
    exclude_field_filter_match: unknown;
    tag_ids: unknown;
    translations: {
        language: unknown;
        subject: unknown;
        preheader: unknown;
    }[];
    tracked_links: unknown;
    ab_test_config: unknown;
    sent_count: unknown;
    planned_recipients: unknown;
    content_length: number;
    content: string;
};
export declare function registerCampaignExtraTools(server: McpServer, api: NetSendoApiClient): void;
//# sourceMappingURL=campaign-extras.d.ts.map
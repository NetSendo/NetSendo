/**
 * NetSendo MCP Server - List & Tag Tools
 * 
 * Tools for managing contact lists and tags
 */

import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
import { ok, fail, compact } from './helpers.js';

type DataEnvelope<T = unknown> = { data: T };

/** Tag as returned by GET /v1/tags */
interface ApiTag {
  id: number;
  name: string;
  color: string | null;
  description: string | null;
}

/** Custom field as returned by GET /v1/custom-fields */
interface ApiCustomField {
  id: number;
  name: string;
  label: string | null;
  description: string | null;
  type: string;
  placeholder: string | null;
  options: unknown;
  default_value: unknown;
  is_public: boolean;
  is_required: boolean;
  is_static: boolean;
  scope: string;
  contact_list_id: number | null;
  sort_order: number | null;
}

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as const;

const PAGE_KEYS = [
  'confirmation', 'success', 'error', 'exists_active', 'exists_inactive',
  'activation_success', 'activation_error', 'unsubscribe', 'unsubscribe_confirm',
  'unsubscribe_error', 'unsubscribe_link_sent',
] as const;

const pageSchema = z.object({
  type: z.enum(['system', 'custom', 'external']).optional()
    .describe('system = NetSendo\'s own page (default); custom = redirect to "url"; external = an External Page by "external_page_id"'),
  url: z.string().max(2048).nullable().optional().describe('custom: URL to redirect to'),
  external_page_id: z.number().int().nullable().optional().describe('external: ID of one of the account\'s External Pages'),
}).passthrough();

const pagesShape = Object.fromEntries(PAGE_KEYS.map((key) => [key, pageSchema.optional()])) as Record<(typeof PAGE_KEYS)[number], z.ZodOptional<typeof pageSchema>>;

/** Settings sections shared by a list and the account defaults. */
const subscriptionShape = {
  double_optin: z.boolean().optional().describe('Require confirmation by email (double opt-in)'),
  notification_email: z.string().email().nullable().optional().describe('Address notified about each new subscriber'),
  delete_unconfirmed: z.boolean().optional().describe('Delete contacts that never confirmed'),
  delete_unconfirmed_after_days: z.number().int().min(1).max(365).nullable().optional().describe('...after this many days (1-365)'),
};

const sendingShape = {
  mailbox_id: z.number().int().nullable().optional().describe('Sender mailbox (see list_mailboxes). Same as default_mailbox_id; both are kept in sync'),
  from_name: z.string().max(255).nullable().optional().describe('Sender name'),
  reply_to: z.string().email().nullable().optional().describe('Reply-To address'),
  company_name: z.string().max(255).nullable().optional().describe('Company name for the footer / legal block'),
  company_address: z.string().max(255).nullable().optional(),
  company_city: z.string().max(255).nullable().optional(),
  company_zip: z.string().max(20).nullable().optional(),
  company_country: z.string().max(255).nullable().optional(),
  headers: z.object({
    list_unsubscribe: z.string().nullable().optional().describe('List-Unsubscribe header, e.g. "<mailto:unsubscribe@example.com?subject=unsubscribe>, <[[unsubscribe_url]]>"'),
    list_unsubscribe_post: z.string().nullable().optional().describe('List-Unsubscribe-Post header, normally "List-Unsubscribe=One-Click"'),
  }).passthrough().optional(),
};

const advancedShape = {
  facebook_integration: z.string().nullable().optional().describe('Facebook integration identifier'),
  queue_days: z.array(z.enum(DAYS)).optional().describe('Days on which autoresponder queue messages may go out (replaces the stored list)'),
  bounce_analysis: z.boolean().optional().describe('Process bounces for this list'),
  bounce_scope: z.enum(['list', 'global']).nullable().optional().describe('A hard bounce unsubscribes from this list only, or from all lists'),
  soft_bounce_threshold: z.number().int().min(1).max(10).nullable().optional().describe('Soft bounces before a contact is treated as bounced (1-10)'),
};

const listSettingsSchema = z.object({
  subscription: z.object({
    ...subscriptionShape,
    security_options: z.array(z.unknown()).nullable().optional().describe('Signup security options (list replaced as a whole)'),
  }).passthrough().optional(),
  sending: z.object({
    ...sendingShape,
    sms_provider_id: z.number().int().nullable().optional().describe('SMS provider (see list_sms_providers); kept in sync with default_sms_provider_id'),
    sms_settings: z.string().nullable().optional(),
  }).passthrough().optional(),
  pages: z.object(pagesShape).optional()
    .describe(`Where contacts land after an event. Keys: ${PAGE_KEYS.join(', ')}. confirmation = after a double opt-in signup ("check your inbox"), success = after a single opt-in signup, activation_success/activation_error = after clicking the confirmation link, exists_active/exists_inactive = signup of an existing contact, unsubscribe* = unsubscribe flow`),
  advanced: z.object(advancedShape).passthrough().optional(),
}).passthrough().describe('The list settings document. Deep-merged into what is stored: only the keys you send change; arrays (queue_days, security_options) are replaced whole. Get the current values with get_contact_list.');

const defaultsSettingsSchema = z.object({
  subscription: z.object(subscriptionShape).passthrough().optional(),
  sending: z.object(sendingShape).passthrough().optional(),
  pages: z.object(pagesShape).optional().describe(`Default redirect pages; keys: ${PAGE_KEYS.join(', ')}`),
  advanced: z.object(advancedShape).passthrough().optional(),
});

const dayWindow = z.object({
  enabled: z.boolean().optional().describe('Sending allowed on this day'),
  start: z.number().int().min(0).max(1440).optional().describe('Window start, minutes from midnight (480 = 08:00)'),
  end: z.number().int().min(0).max(1440).optional().describe('Window end, minutes from midnight (1440 = end of day); must be >= start'),
});

const scheduleSchema = z.object(Object.fromEntries(DAYS.map((d) => [d, dayWindow.optional()])) as Record<(typeof DAYS)[number], z.ZodOptional<typeof dayWindow>>)
  .describe('Weekly sending windows per day (monday..sunday). Days you omit are kept; keys omitted inside a day are kept. Windows cannot span midnight.');

/** Columns of a list shared by create and update. */
const listConfigShape = {
  description: z.string().max(1000).optional().describe('What this audience is'),
  contact_list_group_id: z.number().nullable().optional().describe('Group to file the list under'),
  default_mailbox_id: z.number().nullable().optional().describe('Default sender mailbox for email campaigns (see list_mailboxes)'),
  default_sms_provider_id: z.number().nullable().optional().describe('Default SMS provider (see list_sms_providers)'),
  tags: z.array(z.number()).optional().describe('IDs of the account\'s tags to label the list with (replaces the current set; see list_tags)'),
  double_opt_in: z.boolean().optional().describe('Shortcut for settings.subscription.double_optin'),
  resubscription_behavior: z.enum(['reset_date', 'keep_original_date']).optional().describe('Whether a returning contact gets a fresh signup date, which also restarts date-based autoresponders (default: reset_date)'),
  reset_autoresponders_on_resubscription: z.boolean().optional().describe('Restart the autoresponder queue when a contact resubscribes (default: true)'),
  max_subscribers: z.number().min(0).optional().describe('Cap on members; 0 means unlimited'),
  signups_blocked: z.boolean().optional().describe('Stop accepting new signups'),
  required_fields: z.array(z.unknown()).optional().describe('Fields a signup must provide'),
  is_public: z.boolean().optional().describe('Whether the list is publicly selectable in preference centres'),
  timezone: z.string().optional().describe('List timezone, e.g. "Europe/Warsaw"'),
  webhook_url: z.string().url().nullable().optional().describe('URL notified about this list\'s subscriber events'),
  webhook_events: z.array(z.string()).optional().describe('Events to deliver: subscriber.created, subscriber.subscribed, subscriber.unsubscribed, subscriber.bounced, subscriber.tag_added, subscriber.tag_removed'),
  parent_list_id: z.number().nullable().optional().describe('Co-registration: parent list whose members are synced into this list (another list of the account)'),
  sync_settings: z.object({
    sync_on_subscribe: z.boolean().optional().describe('Sync when a contact subscribes to the parent list'),
    sync_on_unsubscribe: z.boolean().optional().describe('Sync unsubscribes from the parent list'),
  }).optional().describe('Co-registration sync options (merged into the stored ones)'),
  settings: listSettingsSchema.optional(),
};

export function registerListTools(server: McpServer, api: NetSendoApiClient) {

  // List Contact Lists
  server.tool(
    'list_contact_lists',
    `Get all contact lists with subscriber counts and default mailbox info.

Each list may have a default_mailbox configured. When creating campaigns for a list:
- If list has default_mailbox: use that mailbox_id
- If no list default: use global default from list_mailboxes (is_default: true)`,
    {
      page: z.number().optional().describe('Page number (default: 1)'),
      per_page: z.number().min(1).max(100).optional().describe('Results per page (1-100, default: 50)'),
    },
    async ({ page, per_page }) => {
      try {
        const result = await api.listContactLists({
          page: page ?? 1,
          per_page: per_page ?? 50,
        });

        const lists = result.data.map(l => ({
          id: l.id,
          name: l.name,
          description: l.description,
          subscribers_count: l.subscribers_count,
          default_mailbox: l.default_mailbox ? {
            id: l.default_mailbox.id,
            name: l.default_mailbox.name,
            from_email: l.default_mailbox.from_email,
            from_name: l.default_mailbox.from_name,
          } : null,
          created_at: l.created_at,
        }));

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              lists,
              pagination: {
                page: result.meta.current_page,
                total_pages: result.meta.last_page,
                total: result.meta.total,
              },
            }, null, 2),
          }],
        };
      } catch (error) {
        return {
          content: [{ type: 'text' as const, text: `Error: ${(error as Error).message}` }],
          isError: true,
        };
      }
    }
  );

  // Get Contact List Details
  server.tool(
    'get_contact_list',
    `Get the full configuration of one contact list: name, type, member count, default mailbox / SMS provider, tags, the settings document (subscription / double opt-in, sending identity and headers, redirect pages, advanced bounce and queue options), co-registration (parent_list_id, sync_settings), limits (max_subscribers, signups_blocked), resubscription behaviour and webhook.

Read this before update_contact_list so you change only what is needed. The sending schedule is separate: get_list_cron_settings. Values a list does not set fall back to the account defaults (get_list_defaults).`,
    {
      id: z.number().describe('Contact list ID'),
    },
    async ({ id }) => {
      try {
        const result = await api.request<DataEnvelope>('get', `/lists/${id}`);
        return ok(result.data);
      } catch (error) {
        return fail(error);
      }
    }
  );

  // Get List Subscribers
  server.tool(
    'get_list_subscribers',
    'Get subscribers belonging to a specific contact list.',
    {
      list_id: z.number().describe('Contact list ID'),
      page: z.number().optional().describe('Page number (default: 1)'),
      per_page: z.number().min(1).max(100).optional().describe('Results per page (1-100, default: 20)'),
    },
    async ({ list_id, page, per_page }) => {
      try {
        const result = await api.getListSubscribers(list_id, {
          page: page ?? 1,
          per_page: per_page ?? 20,
        });

        const subscribers = result.data.map(s => ({
          id: s.id,
          email: s.email,
          name: [s.first_name, s.last_name].filter(Boolean).join(' ') || null,
          status: s.status,
          created_at: s.created_at,
        }));

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              list_id,
              subscribers,
              pagination: {
                page: result.meta.current_page,
                total_pages: result.meta.last_page,
                total: result.meta.total,
              },
            }, null, 2),
          }],
        };
      } catch (error) {
        return {
          content: [{ type: 'text' as const, text: `Error: ${(error as Error).message}` }],
          isError: true,
        };
      }
    }
  );

  // List Tags
  server.tool(
    'list_tags',
    'Get the account\'s tags (id, name, color, description). Tags label subscribers (sync_subscriber_tags, tag_list_members) and lists (update_contact_list tags).',
    {},
    async () => {
      try {
        const tags = await api.request<DataEnvelope<ApiTag[]> & { meta?: { total?: number } }>('get', '/tags', { params: { per_page: 100 } });

        return ok({
          tags: tags.data.map(t => ({
            id: t.id,
            name: t.name,
            color: t.color,
            description: t.description ?? null,
          })),
          total: tags.meta?.total ?? tags.data.length,
        });
      } catch (error) {
        return fail(error);
      }
    }
  );

  // Get List Stats
  server.tool(
    'get_list_stats',
    `Operational snapshot of a list: members broken down by status, engagement share (how many have ever opened or clicked), growth over the last 30 days, and the configuration that affects sending — double opt-in, resubscription behaviour, signup limits, default mailbox and webhook.

Use this to answer "how is this list doing?" in one call. For deeper analysis: get_list_engagement for performance over time, analyze_list_health for data-quality problems.`,
    {
      list_id: z.number().describe('Contact list ID'),
    },
    async ({ list_id }) => {
      try {
        return ok(await api.getListStats(list_id));
      } catch (error) {
        return fail(error);
      }
    }
  );

  // Create Contact List
  server.tool(
    'create_contact_list',
    `Create a contact list with any of the settings the list editor in the browser offers.

Choose the channel with "type": "email" (default) or "sms" — it determines which field is required for members and cannot be changed later by these tools. Contacts can only be moved or copied between lists of the same channel.

Set a default mailbox so campaigns for this list pick the right sender automatically (see list_mailboxes). Anything you do not set falls back to the account defaults (get_list_defaults). The sending schedule is set with update_list_cron_settings after creating the list.

Example: {"name":"Webinar leads","default_mailbox_id":3,"settings":{"subscription":{"double_optin":true,"notification_email":"sales@example.com"},"pages":{"confirmation":{"type":"custom","url":"https://example.com/check-inbox"}}}}`,
    {
      name: z.string().max(255).describe('List name'),
      type: z.enum(['email', 'sms']).optional().describe('Channel (default: email)'),
      ...listConfigShape,
    },
    async (input) => {
      try {
        const result = await api.request<DataEnvelope>('post', '/lists', { data: compact(input) });
        return ok(result.data);
      } catch (error) {
        return fail(error);
      }
    }
  );

  // Update Contact List
  server.tool(
    'update_contact_list',
    `Update a contact list's configuration. Only the fields you pass are changed; "settings" and "sync_settings" are deep-merged into what is stored, so {"settings":{"sending":{"reply_to":"x@example.com"}}} changes only Reply-To. Arrays are replaced whole; pass null to clear a value.

Call get_contact_list first to see the current values. Changing resubscription_behavior or double_opt_in affects future signups only, never existing members. Sending windows and per-minute limits: update_list_cron_settings.`,
    {
      list_id: z.number().describe('Contact list ID'),
      name: z.string().max(255).optional().describe('New name'),
      ...listConfigShape,
    },
    async ({ list_id, ...input }) => {
      try {
        const result = await api.request<DataEnvelope>('put', `/lists/${list_id}`, { data: compact(input) });
        return ok(result.data);
      } catch (error) {
        return fail(error);
      }
    }
  );

  // Get List CRON Settings
  server.tool(
    'get_list_cron_settings',
    `Get the sending schedule of a list: whether it follows the instance-wide CRON settings (use_defaults) or has its own messages-per-minute limit and weekly windows, plus the effective values, whether sending is allowed right now, and the global settings for comparison.

Times are minutes from midnight in the server's time zone (0-1440; 480 = 08:00).`,
    {
      list_id: z.number().describe('Contact list ID'),
    },
    async ({ list_id }) => {
      try {
        const result = await api.request<DataEnvelope>('get', `/lists/${list_id}/cron-settings`);
        return ok(result.data);
      } catch (error) {
        return fail(error);
      }
    }
  );

  // Update List CRON Settings
  server.tool(
    'update_list_cron_settings',
    `Set when queued messages of a list may be sent and how fast. Partial: days you send are merged into the current schedule (or into the global one when the list followed the defaults). Sending volume_per_minute or schedule without use_defaults switches the list to its own settings; use_defaults=true returns it to the global CRON settings.

Example — weekdays 08:00-18:00, no weekends, 50/min: {"list_id":1,"volume_per_minute":50,"schedule":{"monday":{"start":480,"end":1080},"tuesday":{"start":480,"end":1080},"wednesday":{"start":480,"end":1080},"thursday":{"start":480,"end":1080},"friday":{"start":480,"end":1080},"saturday":{"enabled":false},"sunday":{"enabled":false}}}`,
    {
      list_id: z.number().describe('Contact list ID'),
      use_defaults: z.boolean().optional().describe('true = follow the instance-wide CRON settings; false = use this list\'s own'),
      volume_per_minute: z.number().int().min(1).max(10000).nullable().optional().describe('Max messages per minute for this list (null = global limit)'),
      schedule: scheduleSchema.optional(),
    },
    async ({ list_id, ...input }) => {
      try {
        const result = await api.request<DataEnvelope>('put', `/lists/${list_id}/cron-settings`, { data: compact(input) });
        return ok(result.data);
      } catch (error) {
        return fail(error);
      }
    }
  );

  // Get List Defaults
  server.tool(
    'get_list_defaults',
    `Get the account-level list defaults (Settings → Defaults): the subscription, sending, pages and advanced values every list falls back to when it has no value of its own, plus the instance-wide CRON settings (volume per minute, daily maintenance hour, weekly schedule) and whether this key may change them (can_edit_cron).`,
    {},
    async () => {
      try {
        const result = await api.request<DataEnvelope>('get', '/settings/list-defaults');
        return ok(result.data);
      } catch (error) {
        return fail(error);
      }
    }
  );

  // Update List Defaults
  server.tool(
    'update_list_defaults',
    `Change the account-level list defaults. Deep-merged: only the keys you send change. The keys are the same as a list's settings (subscription, sending, pages, advanced — without the SMS and security options).

"cron" changes the instance-wide CRON settings shared by every list without its own schedule (and by every account on this NetSendo instance) — only for the account owner, and only when the user explicitly asked for it.`,
    {
      settings: defaultsSettingsSchema.optional(),
      cron: z.object({
        volume_per_minute: z.number().int().min(1).max(10000).optional().describe('Global max messages per minute'),
        daily_maintenance_hour: z.number().int().min(0).max(23).optional().describe('Hour of the daily maintenance run'),
        schedule: scheduleSchema.optional(),
      }).optional().describe('Instance-wide CRON settings (account owner only)'),
    },
    async ({ settings, cron }) => {
      try {
        const payload: Record<string, unknown> = { ...(settings ?? {}) };
        if (cron) {
          payload.cron = cron;
        }
        const result = await api.request<DataEnvelope>('put', '/settings/list-defaults', { data: { settings: payload } });
        return ok(result.data);
      } catch (error) {
        return fail(error);
      }
    }
  );

  // Delete Contact List
  server.tool(
    'delete_contact_list',
    `Delete a contact list. The contacts themselves are NOT deleted — they keep their other memberships and stay in the account.

SAFETY: deleting a list that still has members is refused unless confirm=true. Show the user the member count and get explicit approval before setting it.`,
    {
      list_id: z.number().describe('Contact list ID'),
      confirm: z.boolean().optional().describe('Required when the list still has members. Only set after the user approved.'),
    },
    async ({ list_id, confirm }) => {
      try {
        return ok(await api.deleteContactList(list_id, confirm ?? false));
      } catch (error) {
        return fail(error);
      }
    }
  );

  // List Custom Fields
  server.tool(
    'list_custom_fields',
    `Get the account's custom fields (extra subscriber data). Use "name" as the placeholder ([[name]]) and as the key when setting values on a subscriber; "label" is what forms show. scope "global" applies to all lists, "list" only to contact_list_id. In subscription forms a custom field is added as {"id":"custom_<id>"}.`,
    {},
    async () => {
      try {
        const fields = await api.request<DataEnvelope<ApiCustomField[]>>('get', '/custom-fields');

        return ok({
          custom_fields: fields.data.map(f => ({
            id: f.id,
            name: f.name,
            label: f.label,
            type: f.type,
            placeholder: f.placeholder,
            options: f.options,
            default_value: f.default_value,
            is_required: f.is_required,
            is_public: f.is_public,
            is_static: f.is_static,
            scope: f.scope,
            contact_list_id: f.contact_list_id,
            sort_order: f.sort_order,
          })),
          total: fields.data.length,
        });
      } catch (error) {
        return fail(error);
      }
    }
  );
}

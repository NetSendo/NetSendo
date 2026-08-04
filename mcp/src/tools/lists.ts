/**
 * NetSendo MCP Server - List & Tag Tools
 * 
 * Tools for managing contact lists and tags
 */

import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
import type { ContactListCreateInput, ContactListUpdateInput } from '../types.js';
import { ok, fail, compact } from './helpers.js';

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
          double_opt_in: l.double_opt_in,
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
    `Get detailed information about a specific contact list, including its default mailbox.

Use default_mailbox info when creating campaigns to automatically select the right sender.`,
    {
      id: z.number().describe('Contact list ID'),
    },
    async ({ id }) => {
      try {
        const list = await api.getContactList(id);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              id: list.id,
              name: list.name,
              description: list.description,
              subscribers_count: list.subscribers_count,
              double_opt_in: list.double_opt_in,
              default_mailbox: list.default_mailbox ? {
                id: list.default_mailbox.id,
                name: list.default_mailbox.name,
                from_email: list.default_mailbox.from_email,
                from_name: list.default_mailbox.from_name,
              } : null,
              created_at: list.created_at,
              updated_at: list.updated_at,
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
    'Get all available tags. Use this to see what tags can be assigned to subscribers.',
    {},
    async () => {
      try {
        const tags = await api.listTags();

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              tags: tags.map(t => ({
                id: t.id,
                name: t.name,
                color: t.color,
                subscribers_count: t.subscribers_count ?? null,
              })),
              total: tags.length,
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
    `Create a contact list.

Choose the channel with "type": "email" (default) or "sms" — it determines which field is required for members and cannot be changed later by these tools. Contacts can only be moved or copied between lists of the same channel.

Set a default mailbox so campaigns for this list pick the right sender automatically (see list_mailboxes).`,
    {
      name: z.string().max(255).describe('List name'),
      type: z.enum(['email', 'sms']).optional().describe('Channel (default: email)'),
      description: z.string().max(1000).optional().describe('What this audience is'),
      contact_list_group_id: z.number().optional().describe('Group to file the list under'),
      default_mailbox_id: z.number().optional().describe('Default sender mailbox for email campaigns (see list_mailboxes)'),
      default_sms_provider_id: z.number().optional().describe('Default SMS provider (see list_sms_providers)'),
      double_opt_in: z.boolean().optional().describe('Require confirmation of the subscription (default: false)'),
      resubscription_behavior: z.enum(['reset_date', 'keep_original_date']).optional().describe('Whether a returning contact gets a fresh signup date, which also restarts date-based autoresponders (default: reset_date)'),
      max_subscribers: z.number().min(0).optional().describe('Cap on members; 0 means unlimited'),
      is_public: z.boolean().optional().describe('Whether the list is publicly selectable in preference centres'),
      timezone: z.string().optional().describe('List timezone, e.g. "Europe/Warsaw"'),
    },
    async (input) => {
      try {
        return ok(await api.createContactList(compact(input) as ContactListCreateInput));
      } catch (error) {
        return fail(error);
      }
    }
  );

  // Update Contact List
  server.tool(
    'update_contact_list',
    `Update a contact list's settings. Only the fields you pass are changed; the rest of the list configuration is preserved.

Note: changing resubscription_behavior or double_opt_in affects future signups only, never existing members.`,
    {
      list_id: z.number().describe('Contact list ID'),
      name: z.string().max(255).optional().describe('New name'),
      description: z.string().max(1000).optional().describe('New description'),
      contact_list_group_id: z.number().optional().describe('Move the list to another group'),
      default_mailbox_id: z.number().optional().describe('Default sender mailbox'),
      default_sms_provider_id: z.number().optional().describe('Default SMS provider'),
      double_opt_in: z.boolean().optional().describe('Require confirmation of the subscription'),
      resubscription_behavior: z.enum(['reset_date', 'keep_original_date']).optional().describe('Signup-date handling for returning contacts'),
      max_subscribers: z.number().min(0).optional().describe('Cap on members; 0 means unlimited'),
      signups_blocked: z.boolean().optional().describe('Stop accepting new signups'),
      is_public: z.boolean().optional().describe('Publicly selectable in preference centres'),
      timezone: z.string().optional().describe('List timezone'),
      webhook_url: z.string().url().optional().describe('URL notified about this list\'s subscriber events'),
      webhook_events: z.array(z.string()).optional().describe('Events to deliver, e.g. ["subscriber.subscribed","subscriber.unsubscribed","subscriber.bounced"]'),
    },
    async ({ list_id, ...input }) => {
      try {
        return ok(await api.updateContactList(list_id, compact(input) as ContactListUpdateInput));
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
    'Get all available custom fields. Custom fields allow storing additional subscriber information.',
    {},
    async () => {
      try {
        const fields = await api.listCustomFields();

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              custom_fields: fields.map(f => ({
                id: f.id,
                name: f.name,
                slug: f.slug,
                type: f.type,
                required: f.required,
                options: f.options,
              })),
              total: fields.length,
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
}

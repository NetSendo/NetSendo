/**
 * NetSendo MCP Server - Campaign Tools
 * 
 * Tools for managing email/SMS campaigns (Messages)
 */

import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';

export function registerCampaignTools(server: McpServer, api: NetSendoApiClient) {

  // List Campaigns
  server.tool(
    'list_campaigns',
    `List all campaigns (email/SMS messages) with optional filtering.

Returns campaign details including:
- id, subject, channel (email/sms)
- type (broadcast/autoresponder)
- status (draft/scheduled/sending/sent/active)
- sent_count, planned_recipients
- scheduled_at, created_at`,
    {
      channel: z.enum(['email', 'sms']).optional().describe('Filter by channel type'),
      type: z.enum(['broadcast', 'autoresponder']).optional().describe('Filter by campaign type'),
      status: z.string().optional().describe('Filter by status (draft, scheduled, sending, sent, active)'),
      search: z.string().optional().describe('Search campaigns by subject'),
      page: z.number().optional().describe('Page number for pagination'),
      per_page: z.number().optional().describe('Items per page (max 100)'),
    },
    async ({ channel, type, status, search, page, per_page }) => {
      try {
        const campaigns = await api.listMessages({
          channel,
          type,
          status,
          search,
          page,
          per_page: Math.min(per_page ?? 25, 100),
        });

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              campaigns: campaigns.data.map(c => ({
                id: c.id,
                subject: c.subject,
                channel: c.channel,
                type: c.type,
                status: c.status,
                sent_count: c.sent_count,
                planned_recipients: c.planned_recipients_count,
                scheduled_at: c.scheduled_at,
                created_at: c.created_at,
              })),
              pagination: campaigns.meta,
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

  // Get Campaign Details
  server.tool(
    'get_campaign',
    'Get detailed information about a specific campaign including contact lists, exclusions, and configuration.',
    {
      campaign_id: z.number().describe('Campaign ID'),
    },
    async ({ campaign_id }) => {
      try {
        const campaign = await api.getMessage(campaign_id);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              id: campaign.id,
              subject: campaign.subject,
              preheader: campaign.preheader,
              channel: campaign.channel,
              type: campaign.type,
              status: campaign.status,
              mailbox: campaign.mailbox,
              content_preview: campaign.content?.substring(0, 500) + (campaign.content?.length > 500 ? '...' : ''),
              contact_lists: campaign.contact_lists?.map(l => ({ id: l.id, name: l.name, subscribers: l.subscribers_count })),
              excluded_lists: campaign.excluded_lists?.map(l => ({ id: l.id, name: l.name })),
              scheduled_at: campaign.scheduled_at,
              day: campaign.day,
              time_of_day: campaign.time_of_day,
              sent_count: campaign.sent_count,
              planned_recipients: campaign.planned_recipients_count,
              is_active: campaign.is_active,
              created_at: campaign.created_at,
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

  // Create Campaign
  server.tool(
    'create_campaign',
    `Create a new email or SMS campaign. The campaign is created as a DRAFT by default.

REQUIRED PARAMETERS:
- subject: Campaign subject line / title
- channel: Must be 'email' or 'sms' 
- type: 'broadcast' (one-time) or 'autoresponder' (subscription-triggered)

OPTIONAL PARAMETERS:
- content: HTML for email, plain text for SMS
- preheader: Email preview text (email only)
- mailbox_id: Sender mailbox ID (use list_mailboxes to get IDs)
- contact_list_ids: Array of recipient list IDs
- excluded_list_ids: Array of list IDs to exclude from sending
- scheduled_at: ISO datetime to schedule sending (creates as 'scheduled')
- day, time_of_day, timezone: For autoresponders only

WORKFLOW OPTIONS:
1. DRAFT: create_campaign → edit later in UI
2. SEND NOW: create_campaign → set_campaign_lists → send_campaign
3. SCHEDULE: create_campaign → set_campaign_lists → schedule_campaign
4. ONE-STEP SCHEDULE: create_campaign with scheduled_at + contact_list_ids

PERSONALIZATION (use list_placeholders for full list):
- [[first_name]], [[last_name]], [[email]], [[phone]]
- [[unsubscribe_link]] - REQUIRED for email compliance
- {{male|female}} - Gender-based text variation

EXAMPLE:
channel: "email"
type: "broadcast"
subject: "{{Drogi|Droga}} [[first_name]], sprawdź naszą ofertę!"
content: "<p>Cześć [[first_name]]!</p><a href='[[unsubscribe_link]]'>Wypisz się</a>"
mailbox_id: 1
contact_list_ids: [1, 2]`,
    {
      subject: z.string().min(1).describe('Campaign subject line / title'),
      channel: z.enum(['email', 'sms']).describe('REQUIRED: Channel type - must be "email" or "sms"'),
      type: z.enum(['broadcast', 'autoresponder']).describe('REQUIRED: Campaign type - "broadcast" (one-time) or "autoresponder" (triggered on subscription)'),
      content: z.string().optional().describe('Email/SMS content (HTML for email, plain text for SMS)'),
      preheader: z.string().optional().describe('Email preheader/preview text (shown in inbox preview)'),
      mailbox_id: z.number().optional().describe('Mailbox ID for sending (use list_mailboxes to get available IDs)'),
      contact_list_ids: z.array(z.number()).optional().describe('Array of contact list IDs to send to (use list_contact_lists to get IDs)'),
      excluded_list_ids: z.array(z.number()).optional().describe('Array of contact list IDs to EXCLUDE from sending'),
      scheduled_at: z.string().optional().describe('ISO 8601 datetime to schedule sending (e.g., 2024-12-25T10:00:00Z). If provided, campaign status will be "scheduled"'),
      day: z.number().optional().describe('For autoresponders only: day offset after subscription (0 = same day)'),
      time_of_day: z.string().optional().describe('For autoresponders only: time to send (format: HH:MM, e.g., "09:00")'),
      timezone: z.string().optional().describe('Timezone for scheduling (e.g., Europe/Warsaw, America/New_York)'),
    },
    async ({ subject, channel, type, content, preheader, mailbox_id, contact_list_ids, excluded_list_ids, scheduled_at, day, time_of_day, timezone }) => {
      try {
        const campaign = await api.createMessage({
          subject,
          channel,
          type,
          content,
          preheader,
          mailbox_id,
          contact_list_ids,
          excluded_list_ids,
          scheduled_at,
          day,
          time_of_day,
          timezone,
        });

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: 'Campaign created successfully',
              campaign: {
                id: campaign.id,
                subject: campaign.subject,
                channel: campaign.channel,
                type: campaign.type,
                status: campaign.status,
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

  // Update Campaign
  server.tool(
    'update_campaign',
    `Update an existing campaign (draft or scheduled only).

UPDATABLE FIELDS:
- subject, content, preheader, mailbox_id
- For autoresponders: day, time_of_day, is_active

NOTE: Sent campaigns cannot be modified.`,
    {
      campaign_id: z.number().describe('Campaign ID to update'),
      subject: z.string().optional().describe('New subject line'),
      content: z.string().optional().describe('New content'),
      preheader: z.string().optional().describe('New preheader'),
      mailbox_id: z.number().optional().describe('New mailbox ID'),
      day: z.number().optional().describe('New day offset (autoresponders)'),
      time_of_day: z.string().optional().describe('New time of day (autoresponders)'),
      is_active: z.boolean().optional().describe('Activate/deactivate autoresponder'),
    },
    async ({ campaign_id, subject, content, preheader, mailbox_id, day, time_of_day, is_active }) => {
      try {
        const campaign = await api.updateMessage(campaign_id, {
          subject,
          content,
          preheader,
          mailbox_id,
          day,
          time_of_day,
          is_active,
        });

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: 'Campaign updated successfully',
              campaign: {
                id: campaign.id,
                subject: campaign.subject,
                status: campaign.status,
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

  // Set Campaign Lists
  server.tool(
    'set_campaign_lists',
    'Set the recipient contact lists for a campaign. Replaces any existing lists.',
    {
      campaign_id: z.number().describe('Campaign ID'),
      contact_list_ids: z.array(z.number()).describe('Array of contact list IDs to send to'),
    },
    async ({ campaign_id, contact_list_ids }) => {
      try {
        const result = await api.setMessageLists(campaign_id, contact_list_ids);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: 'Recipient lists updated',
              planned_recipients: result.planned_recipients,
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

  // Set Campaign Exclusions
  server.tool(
    'set_campaign_exclusions',
    'Set exclusion lists for a campaign. Subscribers on these lists will NOT receive the campaign.',
    {
      campaign_id: z.number().describe('Campaign ID'),
      excluded_list_ids: z.array(z.number()).describe('Array of contact list IDs to exclude'),
    },
    async ({ campaign_id, excluded_list_ids }) => {
      try {
        const result = await api.setMessageExclusions(campaign_id, excluded_list_ids);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: 'Exclusion lists updated',
              planned_recipients: result.planned_recipients,
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

  // Schedule Campaign
  server.tool(
    'schedule_campaign',
    'Schedule a campaign for future sending.',
    {
      campaign_id: z.number().describe('Campaign ID'),
      scheduled_at: z.string().describe('ISO 8601 datetime for sending (e.g., 2024-12-25T10:00:00Z)'),
    },
    async ({ campaign_id, scheduled_at }) => {
      try {
        const campaign = await api.scheduleMessage(campaign_id, scheduled_at);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: 'Campaign scheduled',
              campaign_id: campaign.id,
              scheduled_at: campaign.scheduled_at,
              status: campaign.status,
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

  // Send Campaign
  server.tool(
    'send_campaign',
    `Send a campaign immediately or activate an autoresponder.

BEHAVIOR:
- Broadcast: Queues all recipients for immediate sending
- Autoresponder: Activates the trigger (sends on schedule)

PREREQUISITES:
- Campaign must have contact lists (use set_campaign_lists first)
- Must have content, subject, and mailbox configured`,
    {
      campaign_id: z.number().describe('Campaign ID'),
    },
    async ({ campaign_id }) => {
      try {
        const result = await api.sendMessage(campaign_id);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: result.message.type === 'autoresponder' ? 'Autoresponder activated' : 'Campaign queued for sending',
              campaign_id: result.message.id,
              status: result.message.status,
              recipients_added: result.recipients_added,
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

  // Get Campaign Stats
  server.tool(
    'get_campaign_stats',
    'Get sending statistics for a campaign including delivery status breakdown.',
    {
      campaign_id: z.number().describe('Campaign ID'),
    },
    async ({ campaign_id }) => {
      try {
        const stats = await api.getMessageStats(campaign_id);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify(stats, null, 2),
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

  // Delete Campaign
  server.tool(
    'delete_campaign',
    'Delete a campaign. Only draft or scheduled campaigns can be deleted.',
    {
      campaign_id: z.number().describe('Campaign ID to delete'),
    },
    async ({ campaign_id }) => {
      try {
        await api.deleteMessage(campaign_id);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: 'Campaign deleted successfully',
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

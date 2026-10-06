/**
 * NetSendo MCP Server - Campaign Tools
 *
 * Tools for managing email/SMS campaigns (Messages)
 */
import { z } from "zod";
import { campaignEditorShape, summarizeCampaign } from "./campaign-extras.js";
import { compact, fail } from "./helpers.js";
export function registerCampaignTools(server, api) {
    // List Campaigns
    server.tool("list_campaigns", `List all campaigns (email/SMS messages) with optional filtering.

Returns campaign details including:
- id, subject, channel (email/sms)
- type (broadcast/autoresponder)
- status (draft/scheduled/sending/sent/active)
- sent_count, planned_recipients
- scheduled_at, created_at`, {
        channel: z
            .enum(["email", "sms"])
            .optional()
            .describe("Filter by channel type"),
        type: z
            .enum(["broadcast", "autoresponder"])
            .optional()
            .describe("Filter by campaign type"),
        status: z
            .string()
            .optional()
            .describe("Filter by status (draft, scheduled, sending, sent, active)"),
        search: z.string().optional().describe("Search campaigns by subject"),
        page: z.number().optional().describe("Page number for pagination"),
        per_page: z.number().optional().describe("Items per page (max 100)"),
    }, async ({ channel, type, status, search, page, per_page }) => {
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
                content: [
                    {
                        type: "text",
                        text: JSON.stringify({
                            campaigns: campaigns.data.map((c) => ({
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
                    },
                ],
            };
        }
        catch (error) {
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: ${error.message}`,
                    },
                ],
                isError: true,
            };
        }
    });
    // Get Campaign Details
    server.tool("get_campaign", `Get everything needed to review or edit a campaign: subject, preheader, content (truncated unless content_chars=0), mailbox, template_id, lists/exclusions, CRM contacts, custom-field filters, day/time/timezone, status/is_active, trigger (trigger_type, trigger_config and the automation_rule it created), tag_ids, translations, tracked_links and ab_test_config — the same field names update_campaign accepts.`, {
        campaign_id: z.number().describe("Campaign ID"),
        content_chars: z
            .number()
            .optional()
            .describe("Return at most this many characters of content (default 2000, 0 = full content)"),
    }, async ({ campaign_id, content_chars }) => {
        try {
            const res = await api.request("get", `/messages/${campaign_id}`);
            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify(summarizeCampaign(res.data, content_chars ?? 2000), null, 2),
                    },
                ],
            };
        }
        catch (error) {
            return fail(error);
        }
    });
    // Create Campaign
    server.tool("create_campaign", `Create a new email or SMS campaign. The campaign is created as a DRAFT by default.

⚠️ CRITICAL: You MUST specify 'channel' parameter - choose 'email' OR 'sms'!

REQUIRED PARAMETERS (all three must be provided):
1. subject: Campaign subject line / title (string)
2. channel: MUST be 'email' or 'sms' - this determines the type of campaign
3. type: 'broadcast' (one-time mass send) or 'autoresponder' (triggered on subscription)

OPTIONAL PARAMETERS:
- content: HTML for email campaigns, plain text for SMS campaigns
- preheader: Email preview text (email only)
- mailbox_id: Sender mailbox ID - required for email campaigns (use list_mailboxes to get IDs)
- contact_list_ids: Array of recipient list IDs (use list_contact_lists to get IDs)
- excluded_list_ids: Array of list IDs to exclude from sending
- scheduled_at: ISO datetime to schedule sending (e.g., 2024-12-25T10:00:00Z) — needs contact_list_ids; the campaign is then scheduled right after creation
- day, time_of_day, timezone: For autoresponders only
- template_id: reference only — sending uses \`content\`; copy the template's html (get_template) into content
- trigger_type + trigger_config, tag_ids, translations, tracked_links, crm_contact_ids, field filters, ab_test_config, send_in_subscriber_timezone (see each field)

AUTORESPONDER (= queue message): type "autoresponder" + day (days after the subscriber joined the list, 0 = right away) + optional time_of_day/timezone. Every list member gets it once, counted from their signup. It is created as an inactive draft: activate it with set_campaign_active (or send_campaign). Build a sequence by creating one autoresponder per step with day 0, 2, 5, ...

🔑 MAILBOX SELECTION WORKFLOW (for email campaigns):
Before creating an email campaign, determine the correct mailbox_id:
1. Call list_contact_lists to check if contact lists have default_mailbox
2. If list has default_mailbox: use that mailbox_id
3. If no list default: call list_mailboxes and use default_mailbox_id from response
4. If no default: ask user which mailbox to use

AUTO-SELECTION (if mailbox_id not provided):
The system will try to auto-select in this order:
1. First attached list's default_mailbox
2. Global default mailbox (is_default: true)
3. First active verified mailbox

WORKFLOW OPTIONS:
1. DRAFT: create_campaign → edit later in UI
2. SEND NOW: create_campaign → preview_campaign / send_campaign_test → send_campaign
3. SCHEDULE: create_campaign → set_campaign_lists → schedule_campaign
4. ONE-STEP SCHEDULE: create_campaign with scheduled_at + contact_list_ids
5. AUTORESPONDER: create_campaign (type autoresponder, day N) → set_campaign_active

PERSONALIZATION (use list_placeholders for full list):
- [[first_name]], [[last_name]], [[email]], [[phone]]
- [[unsubscribe_link]] - REQUIRED for email compliance
- {{male|female}} - Gender-based text variation

EXAMPLE EMAIL CAMPAIGN:
{
  "channel": "email",
  "type": "broadcast",
  "subject": "Cześć [[first_name]], sprawdź naszą ofertę!",
  "content": "<p>Dziękujemy za kontakt.</p><a href='[[unsubscribe_link]]'>Wypisz się</a>",
  "mailbox_id": 1,
  "contact_list_ids": [1, 2]
}

EXAMPLE SMS CAMPAIGN:
{
  "channel": "sms",
  "type": "broadcast",
  "subject": "Promocja SMS",
  "content": "Cześć [[first_name]]! Twoja zniżka: PROMO20"
}

⚠️ Common mistakes:
- Forgetting to include 'channel' parameter → causes validation error
- Using 'send_email' instead of 'create_campaign' for bulk sends
- Not specifying mailbox_id for email campaigns`, {
        subject: z.string().min(1).describe("Campaign subject line / title"),
        channel: z
            .enum(["email", "sms"])
            .describe('⚠️ REQUIRED: Campaign channel - must be exactly "email" or "sms"'),
        type: z
            .enum(["broadcast", "autoresponder"])
            .describe('REQUIRED: Campaign type - "broadcast" (one-time) or "autoresponder" (triggered on subscription)'),
        content: z
            .string()
            .optional()
            .describe("Email/SMS content (HTML for email, plain text for SMS)"),
        preheader: z
            .string()
            .optional()
            .describe("Email preheader/preview text (shown in inbox preview)"),
        mailbox_id: z
            .number()
            .optional()
            .describe("Mailbox ID for sending (use list_mailboxes to get available IDs)"),
        contact_list_ids: z
            .array(z.number())
            .optional()
            .describe("Array of contact list IDs to send to (use list_contact_lists to get IDs)"),
        excluded_list_ids: z
            .array(z.number())
            .optional()
            .describe("Array of contact list IDs to EXCLUDE from sending"),
        scheduled_at: z
            .string()
            .optional()
            .describe('ISO 8601 datetime to schedule sending (e.g., 2024-12-25T10:00:00Z). If provided, campaign status will be "scheduled"'),
        day: z
            .number()
            .optional()
            .describe("For autoresponders only: day offset after subscription (0 = same day)"),
        time_of_day: z
            .string()
            .optional()
            .describe('For autoresponders only: time to send (format: HH:MM, e.g., "09:00")'),
        timezone: z
            .string()
            .optional()
            .describe("Timezone for scheduling (e.g., Europe/Warsaw, America/New_York)"),
        ...campaignEditorShape,
    }, async ({ subject, channel, type, content, preheader, mailbox_id, contact_list_ids, excluded_list_ids, scheduled_at, day, time_of_day, timezone, ...editorFields }) => {
        try {
            // Additional validation with clear error messages
            if (!channel) {
                return {
                    content: [
                        {
                            type: "text",
                            text: 'Error: The "channel" parameter is REQUIRED. Please specify channel: "email" or "sms". This determines whether you\'re creating an email campaign or SMS campaign.',
                        },
                    ],
                    isError: true,
                };
            }
            if (!["email", "sms"].includes(channel)) {
                return {
                    content: [
                        {
                            type: "text",
                            text: `Error: Invalid channel "${channel}". The channel parameter must be exactly "email" or "sms".`,
                        },
                    ],
                    isError: true,
                };
            }
            if (channel === "email" && !mailbox_id) {
                console.error("[NetSendo MCP] Warning: Creating email campaign without mailbox_id. Campaign will need mailbox configured before sending.");
            }
            const created = await api.request("post", "/messages", {
                data: compact({
                    subject,
                    channel,
                    type,
                    content,
                    preheader,
                    mailbox_id,
                    contact_list_ids,
                    excluded_list_ids,
                    day,
                    time_of_day,
                    timezone,
                    ...editorFields,
                }),
            });
            let campaign = created.data;
            // The create endpoint always makes a draft; scheduling is its own step
            let scheduleError;
            if (scheduled_at) {
                try {
                    const scheduled = await api.scheduleMessage(campaign.id, scheduled_at, timezone);
                    campaign = { ...campaign, status: scheduled.status, scheduled_at: scheduled.scheduled_at };
                }
                catch (error) {
                    scheduleError = `Created as a draft but scheduling failed: ${error.message}. Fix it, then call schedule_campaign.`;
                }
            }
            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify({
                            success: true,
                            message: "Campaign created successfully",
                            campaign: summarizeCampaign(campaign, 0),
                            warnings: [
                                ...(created.warnings ?? []),
                                ...(scheduleError ? [scheduleError] : []),
                            ],
                            next_steps: campaign.status === "draft"
                                ? type === "autoresponder"
                                    ? "Autoresponder is an inactive DRAFT. Check it with preview_campaign / send_campaign_test, then activate it with set_campaign_active."
                                    : "Campaign is in DRAFT status. Check it with preview_campaign / send_campaign_test, make sure it has lists (set_campaign_lists), then send_campaign or schedule_campaign."
                                : undefined,
                        }, null, 2),
                    },
                ],
            };
        }
        catch (error) {
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: ${error.message}`,
                    },
                ],
                isError: true,
            };
        }
    });
    // Update Campaign
    server.tool("update_campaign", `Update an existing campaign (draft or scheduled; sent/sending campaigns are locked). Only the fields you pass change; for array fields an empty array clears them.

UPDATABLE FIELDS:
- subject, content, preheader, mailbox_id, template_id (reference only — sending uses content)
- contact_list_ids / excluded_list_ids (replace the lists; planned recipient count is recalculated)
- day, time_of_day, timezone, send_in_subscriber_timezone
- is_active (autoresponders: true activates a draft, like set_campaign_active)
- trigger_type / trigger_config (synced to the trigger's automation rule; trigger_type null removes it)
- tag_ids, translations, tracked_links, crm_contact_ids, excluded_crm_contact_ids
- include/exclude_field_filters (+ _match), ab_test_config

Call get_campaign first to see the current values.`, {
        campaign_id: z.number().describe("Campaign ID to update"),
        subject: z.string().optional().describe("New subject line"),
        content: z.string().optional().describe("New content (full HTML for email)"),
        preheader: z.string().optional().describe("New preheader"),
        mailbox_id: z.number().optional().describe("New mailbox ID"),
        contact_list_ids: z
            .array(z.number())
            .optional()
            .describe("Replace the recipient lists"),
        excluded_list_ids: z
            .array(z.number())
            .optional()
            .describe("Replace the excluded lists"),
        day: z.number().optional().describe("New day offset (autoresponders)"),
        time_of_day: z
            .string()
            .optional()
            .describe('New time of day, HH:MM (autoresponders)'),
        timezone: z.string().optional().describe("Timezone, e.g. Europe/Warsaw"),
        is_active: z
            .boolean()
            .optional()
            .describe("Activate/deactivate autoresponder"),
        ...campaignEditorShape,
    }, async ({ campaign_id, ...fields }) => {
        try {
            const res = await api.request("put", `/messages/${campaign_id}`, { data: compact(fields) });
            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify({
                            success: true,
                            message: "Campaign updated successfully",
                            campaign: summarizeCampaign(res.data, 0),
                            warnings: res.warnings,
                        }, null, 2),
                    },
                ],
            };
        }
        catch (error) {
            return fail(error);
        }
    });
    // Set Campaign Lists
    server.tool("set_campaign_lists", "Set the recipient contact lists for a campaign. Replaces any existing lists.", {
        campaign_id: z.number().describe("Campaign ID"),
        contact_list_ids: z
            .array(z.number())
            .describe("Array of contact list IDs to send to"),
    }, async ({ campaign_id, contact_list_ids }) => {
        try {
            const result = await api.setMessageLists(campaign_id, contact_list_ids);
            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify({
                            success: true,
                            message: "Recipient lists updated",
                            planned_recipients: result.planned_recipients,
                        }, null, 2),
                    },
                ],
            };
        }
        catch (error) {
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: ${error.message}`,
                    },
                ],
                isError: true,
            };
        }
    });
    // Set Campaign Exclusions
    server.tool("set_campaign_exclusions", "Set exclusion lists for a campaign. Subscribers on these lists will NOT receive the campaign.", {
        campaign_id: z.number().describe("Campaign ID"),
        excluded_list_ids: z
            .array(z.number())
            .describe("Array of contact list IDs to exclude"),
    }, async ({ campaign_id, excluded_list_ids }) => {
        try {
            const result = await api.setMessageExclusions(campaign_id, excluded_list_ids);
            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify({
                            success: true,
                            message: "Exclusion lists updated",
                            planned_recipients: result.planned_recipients,
                        }, null, 2),
                    },
                ],
            };
        }
        catch (error) {
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: ${error.message}`,
                    },
                ],
                isError: true,
            };
        }
    });
    // Schedule Campaign
    server.tool("schedule_campaign", `Schedule a campaign for future sending.

TIMEZONE HANDLING:
By default, the scheduled_at time is interpreted in the user's profile timezone.
You can override this by specifying a timezone parameter.

Examples:
- scheduled_at: "2024-12-25T10:00:00" → Uses user's timezone from profile
- scheduled_at: "2024-12-25T10:00:00Z" → Explicit UTC (Z suffix)
- scheduled_at: "2024-12-25T10:00:00", timezone: "Europe/Warsaw" → Polish time

Common timezones:
- Europe/Warsaw (Poland)
- Europe/London (UK)
- America/New_York (US Eastern)
- America/Los_Angeles (US Pacific)
- UTC`, {
        campaign_id: z.number().describe("Campaign ID"),
        scheduled_at: z
            .string()
            .describe("ISO 8601 datetime for sending (e.g., 2024-12-25T10:00:00)"),
        timezone: z
            .string()
            .optional()
            .describe("Timezone for interpreting scheduled_at (e.g., Europe/Warsaw). If not provided, uses user profile timezone."),
    }, async ({ campaign_id, scheduled_at, timezone }) => {
        try {
            const campaign = await api.scheduleMessage(campaign_id, scheduled_at, timezone);
            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify({
                            success: true,
                            message: "Campaign scheduled",
                            campaign_id: campaign.id,
                            scheduled_at: campaign.scheduled_at,
                            status: campaign.status,
                        }, null, 2),
                    },
                ],
            };
        }
        catch (error) {
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: ${error.message}`,
                    },
                ],
                isError: true,
            };
        }
    });
    // Send Campaign
    server.tool("send_campaign", `Send a campaign immediately or activate an autoresponder.

BEHAVIOR:
- Broadcast: Queues all recipients for immediate sending
- Autoresponder: Activates the trigger (sends on schedule)

PREREQUISITES:
- Campaign must have contact lists (use set_campaign_lists first)
- Must have content, subject, and mailbox configured`, {
        campaign_id: z.number().describe("Campaign ID"),
    }, async ({ campaign_id }) => {
        try {
            const result = await api.sendMessage(campaign_id);
            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify({
                            success: true,
                            message: result.message.type === "autoresponder"
                                ? "Autoresponder activated"
                                : "Campaign queued for sending",
                            campaign_id: result.message.id,
                            status: result.message.status,
                            recipients_added: result.recipients_added,
                        }, null, 2),
                    },
                ],
            };
        }
        catch (error) {
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: ${error.message}`,
                    },
                ],
                isError: true,
            };
        }
    });
    // Get Campaign Stats
    server.tool("get_campaign_stats", "Get sending statistics for a campaign including delivery status breakdown.", {
        campaign_id: z.number().describe("Campaign ID"),
    }, async ({ campaign_id }) => {
        try {
            const stats = await api.getMessageStats(campaign_id);
            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify(stats, null, 2),
                    },
                ],
            };
        }
        catch (error) {
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: ${error.message}`,
                    },
                ],
                isError: true,
            };
        }
    });
    // Delete Campaign
    server.tool("delete_campaign", "Delete a campaign. Only draft or scheduled campaigns can be deleted.", {
        campaign_id: z.number().describe("Campaign ID to delete"),
    }, async ({ campaign_id }) => {
        try {
            await api.deleteMessage(campaign_id);
            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify({
                            success: true,
                            message: "Campaign deleted successfully",
                        }, null, 2),
                    },
                ],
            };
        }
        catch (error) {
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: ${error.message}`,
                    },
                ],
                isError: true,
            };
        }
    });
}
//# sourceMappingURL=campaigns.js.map
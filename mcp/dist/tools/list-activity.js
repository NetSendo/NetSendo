/**
 * NetSendo MCP Server - Activity & Engagement Tools
 *
 * "What is happening on this list?" — the raw event stream, the aggregate
 * performance picture, and one contact's full history.
 */
import { z } from 'zod';
import { ok, fail, compact } from './helpers.js';
const EVENT_TYPES = [
    'subscribed', 'resubscribed', 'confirmed', 'unsubscribed', 'bounced', 'sent', 'failed', 'opened', 'clicked',
];
export function registerListActivityTools(server, api) {
    server.tool('get_list_activity', `Chronological event feed for a list — who joined, confirmed, unsubscribed or bounced, and which messages were sent, opened, clicked or failed.

Use this to answer "what happened on this list recently?" or to investigate a sudden change: a spike in unsubscribed or bounced right after a send usually points at a content or deliverability problem.

Narrow with "types" when looking for something specific, e.g. types=["unsubscribed","bounced"].`, {
        list_id: z.number().describe('Contact list ID'),
        days: z.number().min(1).max(365).optional().describe('How far back to look (default: 30)'),
        limit: z.number().min(1).max(500).optional().describe('Maximum events returned (default: 50)'),
        types: z.array(z.enum(EVENT_TYPES)).optional().describe('Event types to include (default: all)'),
    }, async ({ list_id, ...params }) => {
        try {
            return ok(await api.getListActivity(list_id, compact(params)));
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('get_list_engagement', `Performance snapshot for a list: audience by status, growth (added / lost / net / churn with a daily series), delivery metrics (sent, unique openers and clickers, open rate, click rate, click-to-open rate), the best performing messages and links, the most engaged contacts, and how much of the audience has gone quiet.

Use this before planning a campaign, when reporting on list health, or to decide whether a re-engagement sequence is warranted.`, {
        list_id: z.number().describe('Contact list ID'),
        days: z.number().min(1).max(365).optional().describe('Reporting window in days (default: 30)'),
    }, async ({ list_id, days }) => {
        try {
            return ok(await api.getListEngagement(list_id, compact({ days })));
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('get_subscriber_activity', `Full history of one contact: profile and engagement counters, every list they belong to with status and dates, their tags, how many messages are still queued for them, and a timeline of signups, confirmations, unsubscribes, sends, opens and clicks.

Use this when investigating a single person — "did they get the message?", "why are they still receiving mail?", "when did they unsubscribe?".`, {
        subscriber_id: z.number().describe('Subscriber ID (find it with list_subscribers or get_subscriber)'),
        days: z.number().min(1).max(1095).optional().describe('How far back the timeline reaches (default: 365)'),
        limit: z.number().min(1).max(200).optional().describe('Maximum timeline entries (default: 50)'),
    }, async ({ subscriber_id, ...params }) => {
        try {
            return ok(await api.getSubscriberActivity(subscriber_id, compact(params)));
        }
        catch (error) {
            return fail(error);
        }
    });
}
//# sourceMappingURL=list-activity.js.map
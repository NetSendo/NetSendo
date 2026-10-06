/**
 * NetSendo MCP Server - Webhook Tools
 *
 * Outgoing webhooks: NetSendo POSTs a signed JSON payload to a URL whenever
 * one of the subscribed events happens (subscriber changes, tags, email/SMS,
 * Stripe purchases).
 */
import { z } from 'zod';
import { NetSendoApiError } from '../api-client.js';
import { compact, fail, ok } from './helpers.js';
const EVENTS_HELP = `Events: subscriber.created, subscriber.updated, subscriber.deleted, subscriber.subscribed, subscriber.resubscribed, subscriber.unsubscribed, subscriber.bounced, subscriber.tag_added, subscriber.tag_removed, email.queued, sms.queued, sms.sent, sms.failed, stripe.purchase_completed, stripe.payment_refunded (list_webhook_events returns the authoritative list for this installation).`;
const DELIVERY_HELP = `Delivery: POST with JSON body {"event", "timestamp", "data"} and headers X-NetSendo-Event and X-NetSendo-Signature = HMAC-SHA256 of the raw body keyed with the webhook secret. A 2xx answer counts as success; after 10 consecutive failures the webhook is deactivated automatically (re-enable with update_webhook is_active=true).`;
const eventList = z.array(z.string()).min(1);
export function registerWebhookTools(server, api) {
    server.tool('list_webhook_events', 'List the event names a webhook can subscribe to on this NetSendo installation. Call it before create_webhook / update_webhook when unsure of an event name.', {}, async () => {
        try {
            const res = await api.request('get', '/webhooks/events');
            return ok({ events: res.events, total: res.events.length });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('list_webhooks', 'List the account\'s outgoing webhooks with their URL, subscribed events, is_active, failure_count (consecutive failed deliveries) and last_triggered_at. Secrets are never listed.', {
        page: z.number().int().min(1).optional().describe('Page number (default 1)'),
        per_page: z.number().int().min(1).max(100).optional().describe('Items per page (default 25)'),
    }, async (params) => {
        try {
            const res = await api.request('get', '/webhooks', { params: compact(params) });
            return ok({ webhooks: res.data, pagination: res.meta ?? null });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('get_webhook', 'Get one webhook: name, url, events, is_active, failure_count and last_triggered_at. The secret is not returned (use regenerate_webhook_secret if it was lost).', {
        webhook_id: z.number().int().describe('Webhook ID'),
    }, async ({ webhook_id }) => {
        try {
            const res = await api.request('get', `/webhooks/${webhook_id}`);
            return ok(res.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('create_webhook', `Register an outgoing webhook. The response contains the signing secret ONCE — show it to the user so they can store it in the receiving system; it cannot be read again.

${EVENTS_HELP}

${DELIVERY_HELP}

Requires the webhooks:write permission.`, {
        name: z.string().min(1).max(255).describe('Name shown in the panel, e.g. "CRM sync"'),
        url: z.string().url().max(2048).describe('HTTPS endpoint that will receive the POST requests'),
        events: eventList.describe('Events to send, e.g. ["subscriber.created","subscriber.unsubscribed"]'),
        is_active: z.boolean().optional().describe('Start active (default true)'),
    }, async (input) => {
        try {
            const res = await api.request('post', '/webhooks', { data: compact(input) });
            return ok({ success: true, message: res.message, webhook: res.data });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('update_webhook', `Change a webhook's name, URL, events or active state. Only the fields you pass are changed; events REPLACES the whole list (read it with get_webhook first to add one). Set is_active=false to pause deliveries, true to resume a webhook that was auto-deactivated after failures. ${EVENTS_HELP} Requires webhooks:write.`, {
        webhook_id: z.number().int().describe('Webhook ID'),
        name: z.string().min(1).max(255).optional().describe('New name'),
        url: z.string().url().max(2048).optional().describe('New endpoint URL'),
        events: eventList.optional().describe('New full list of events'),
        is_active: z.boolean().optional().describe('Enable or pause the webhook'),
    }, async ({ webhook_id, ...changes }) => {
        try {
            const res = await api.request('put', `/webhooks/${webhook_id}`, { data: compact(changes) });
            return ok({ success: true, webhook: res.data });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('delete_webhook', 'Delete a webhook permanently; NetSendo stops sending events to its URL. Confirm with the user first if the webhook may feed another system. Requires webhooks:write.', {
        webhook_id: z.number().int().describe('Webhook ID'),
    }, async ({ webhook_id }) => {
        try {
            const res = await api.request('delete', `/webhooks/${webhook_id}`);
            return ok({ success: true, message: res.message, webhook_id });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('test_webhook', 'Send a test delivery (event "webhook.test", sample subscriber test@example.com) to the webhook URL right now and report whether the endpoint answered 2xx. A failed test counts towards failure_count. Requires webhooks:write.', {
        webhook_id: z.number().int().describe('Webhook ID'),
    }, async ({ webhook_id }) => {
        try {
            const res = await api.request('post', `/webhooks/${webhook_id}/test`);
            return ok(res);
        }
        catch (error) {
            // The API answers 422 when the endpoint did not accept the test
            if (error instanceof NetSendoApiError && error.statusCode === 422 && !error.errors) {
                return {
                    content: [{
                            type: 'text',
                            text: JSON.stringify({
                                success: false,
                                message: error.message,
                                hint: 'The endpoint did not answer with 2xx within 10 seconds. Check the URL and that the receiver accepts POST JSON.',
                            }, null, 2),
                        }],
                    isError: true,
                };
            }
            return fail(error);
        }
    });
    server.tool('regenerate_webhook_secret', 'Generate a new signing secret for a webhook. The old secret stops working immediately, so the receiving system must be updated with the new one — show the returned secret to the user (it is shown only once). Requires webhooks:write.', {
        webhook_id: z.number().int().describe('Webhook ID'),
    }, async ({ webhook_id }) => {
        try {
            const res = await api.request('post', `/webhooks/${webhook_id}/regenerate-secret`);
            return ok({ success: true, webhook_id, secret: res.secret, message: res.message });
        }
        catch (error) {
            return fail(error);
        }
    });
}
//# sourceMappingURL=webhooks.js.map
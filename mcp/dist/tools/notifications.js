/**
 * NetSendo MCP Server - Notification Tools
 *
 * How an automated process reports back to the human who owns the account.
 * These notifications land in NetSendo's notification centre — they are never
 * sent to subscribers or anyone outside the account.
 */
import { z } from 'zod';
import { ok, fail, compact } from './helpers.js';
export function registerNotificationTools(server, api) {
    server.tool('send_notification', `Post a notification to the NetSendo account owner's notification centre.

Use it to report the outcome of unattended work, or to flag something that needs a human decision — "import finished: 1240 added, 18 invalid", "this list has 12% hard bounces, clean-up recommended", "3200 contacts match the deletion criteria, awaiting approval".

This reaches only the account owner. It cannot message subscribers or third parties — use campaigns or send_email for that.`, {
        title: z.string().max(255).describe('Short headline'),
        message: z.string().max(2000).optional().describe('Details — include the numbers that matter'),
        type: z.enum(['info', 'success', 'warning', 'error']).optional().describe('Severity (default: info). Use "warning" for something needing attention, "error" for a failure.'),
        list_id: z.number().optional().describe('Related list — makes the notification link straight to it'),
        action_url: z.string().max(2048).optional().describe('Custom link target; overrides the list link'),
        data: z.record(z.any()).optional().describe('Structured payload kept with the notification, e.g. the import counters'),
    }, async (payload) => {
        try {
            const result = await api.createNotification(compact(payload));
            return ok({ ...result.data, message: result.message });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('list_notifications', 'Read the account owner\'s recent notifications, including how many are unread. Useful to check whether an earlier report was already delivered before sending another.', {
        unread_only: z.boolean().optional().describe('Only unread notifications (default: false)'),
        limit: z.number().min(1).max(100).optional().describe('How many to return (default: 25)'),
    }, async (params) => {
        try {
            return ok(await api.listNotifications(compact(params)));
        }
        catch (error) {
            return fail(error);
        }
    });
}
//# sourceMappingURL=notifications.js.map
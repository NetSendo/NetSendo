/**
 * NetSendo MCP Server - Tag Tools
 *
 * Create, rename/recolour and delete tags. Listing lives in lists.ts
 * (list_tags); assigning tags to people is sync_subscriber_tags /
 * tag_list_members.
 */
import { z } from 'zod';
import { compact, fail, ok } from './helpers.js';
const color = z
    .string()
    .regex(/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/)
    .describe('Hex colour shown in the panel, e.g. "#22c55e" (default "#3b82f6")');
export function registerTagTools(server, api) {
    server.tool('create_tag', `Create a tag in the account. Tag names are unique per account (a duplicate is rejected with 422) — call list_tags first to reuse an existing one.

Tags are referenced BY NAME elsewhere: sync_subscriber_tags / tag_list_members assign them, funnels can start on tag_added (trigger_tag) and check tag_exists, automations react to subscriber.tag_added. Requires the lists:write permission.`, {
        name: z.string().min(1).max(255).describe('Tag name, e.g. "vip" or "webinar-2026-10"'),
        color: color.optional(),
        description: z.string().max(1000).optional().describe('What the tag means (shown in the panel)'),
    }, async (input) => {
        try {
            const res = await api.request('post', '/tags', { data: compact(input) });
            return ok({ success: true, message: res.message, tag: res.data });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('update_tag', `Rename a tag or change its colour / description. Only the fields you pass are changed. Renaming keeps it on every subscriber, but funnels, automations and conditions that refer to the OLD name are not rewritten — check them after a rename. Requires lists:write.`, {
        tag_id: z.number().int().describe('Tag ID (from list_tags)'),
        name: z.string().min(1).max(255).optional().describe('New name (must stay unique in the account)'),
        color: color.optional(),
        description: z.string().max(1000).nullable().optional().describe('New description; null clears it'),
    }, async ({ tag_id, ...changes }) => {
        try {
            const res = await api.request('patch', `/tags/${tag_id}`, { data: compact(changes) });
            return ok({ success: true, message: res.message, tag: res.data });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('delete_tag', `Delete a tag. It is removed from every subscriber, contact list and message that carries it; the subscribers themselves stay. No webhooks or automations fire for this removal.

If the tag is still assigned to subscribers the API answers 409 with subscribers_count — tell the user how many people carry it and re-run with confirm=true only after they approve. Requires lists:write.`, {
        tag_id: z.number().int().describe('Tag ID (from list_tags)'),
        confirm: z.boolean().optional().describe('Set true to delete a tag that is still assigned to subscribers'),
    }, async ({ tag_id, confirm }) => {
        try {
            const res = await api.request('delete', `/tags/${tag_id}`, {
                params: confirm ? { confirm: 1 } : undefined,
            });
            return ok({ success: true, ...res });
        }
        catch (error) {
            return fail(error, 'The tag is still assigned to subscribers. Tell the user how many, and re-run with confirm=true once they approve.');
        }
    });
}
//# sourceMappingURL=tags.js.map
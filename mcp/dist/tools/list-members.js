/**
 * NetSendo MCP Server - List Membership Tools
 *
 * Bulk operations over a list's members. Every tool accepts the same selection
 * block: explicit IDs, explicit emails, or a filter over the list.
 */
import { z } from 'zod';
import { ok, fail, compact } from './helpers.js';
const SELECTION_GUIDE = `Selection (combine freely, at most 5000 contacts per call):
- subscriber_ids — explicit contact IDs
- emails         — explicit addresses
- filter         — a segment of the list: {"status":"active","never_opened":true,"tag_ids":[3],"subscribed_before":"2025-01-01","engaged":false,"limit":500}`;
const selection = {
    subscriber_ids: z.array(z.number()).optional().describe('Explicit contact IDs'),
    emails: z.array(z.string().email()).optional().describe('Explicit email addresses'),
    filter: z.object({
        status: z.enum(['active', 'inactive', 'unsubscribed', 'bounced', 'all']).optional().describe('Membership status (default: active)'),
        tag_ids: z.array(z.number()).optional().describe('Only contacts carrying at least one of these tags'),
        engaged: z.boolean().optional().describe('true = has opened or clicked; false = has done neither'),
        subscribed_before: z.string().optional().describe('ISO date — joined on/before'),
        subscribed_after: z.string().optional().describe('ISO date — joined on/after'),
        never_opened: z.boolean().optional().describe('Only contacts with zero opens'),
        limit: z.number().min(1).max(5000).optional().describe('Cap on matched contacts (default: 5000)'),
    }).optional().describe('Select a segment of the list instead of naming contacts'),
};
export function registerListMemberTools(server, api) {
    server.tool('add_list_members', `Attach existing contacts to a list. This does not create contacts — use import_subscribers for new people.

${SELECTION_GUIDE}
Using "filter" here also requires source_list_id: the list whose members you are selecting from.

By default the signup event fires, so the target list's welcome sequence and automations start. Pass trigger_automations=false to attach quietly.`, {
        list_id: z.number().describe('Target contact list ID'),
        source_list_id: z.number().optional().describe('List the filter selects from — required when using "filter"'),
        source: z.string().optional().describe('Source label recorded on the new membership (default: api)'),
        trigger_automations: z.boolean().optional().describe('Fire the signup event so sequences start (default: true)'),
        ...selection,
    }, async ({ list_id, ...payload }) => {
        try {
            const result = await api.addListMembers(list_id, compact(payload));
            return ok({ ...result.data, message: result.message });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('remove_list_members', `Detach contacts from a list. The contacts themselves and their other list memberships are kept; their queued messages for this list are cancelled.

Prefer set_member_status with status="unsubscribed" when the person opted out — that preserves the opt-out record. Removal is for cleaning up an audience, not for honouring an unsubscribe.

${SELECTION_GUIDE}
A filter-based removal requires confirm=true, since it can match a large segment.`, {
        list_id: z.number().describe('Contact list ID'),
        confirm: z.boolean().optional().describe('Required when selecting with "filter". Only set after the user approved the scope.'),
        ...selection,
    }, async ({ list_id, ...payload }) => {
        try {
            const result = await api.removeListMembers(list_id, compact(payload));
            return ok({ ...result.data, message: result.message });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('set_member_status', `Change the membership status of contacts on a list.

- "unsubscribed" — records the opt-out, cancels queued messages and fires the unsubscribe event (webhooks, automations)
- "active"       — resubscribes; by default this restarts the list's sequences, so pass trigger_automations=false when reactivating in bulk
- "bounced"      — marks the address as bouncing so it stops being targeted
- "inactive"     — parks the membership without recording an opt-out

${SELECTION_GUIDE}`, {
        list_id: z.number().describe('Contact list ID'),
        status: z.enum(['active', 'inactive', 'unsubscribed', 'bounced']).describe('Status to set'),
        reason: z.string().optional().describe('Reason recorded on the change, e.g. "user request"'),
        trigger_automations: z.boolean().optional().describe('Fire signup/unsubscribe events (default: true)'),
        ...selection,
    }, async ({ list_id, ...payload }) => {
        try {
            const result = await api.setListMemberStatus(list_id, compact(payload));
            return ok({ ...result.data, message: result.message });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('copy_list_members', `Copy contacts to another list, keeping them on the source list.

Both lists must be the same channel (email→email, sms→sms). By default the target list's automations start for each copied contact.

${SELECTION_GUIDE}`, {
        list_id: z.number().describe('Source contact list ID'),
        target_list_id: z.number().describe('Destination contact list ID'),
        source: z.string().optional().describe('Source label on the new membership (default: api_copy)'),
        trigger_automations: z.boolean().optional().describe('Start the target list\'s sequences (default: true)'),
        ...selection,
    }, async ({ list_id, ...payload }) => {
        try {
            const result = await api.transferListMembers(list_id, 'copy', compact(payload));
            return ok({ ...result.data, message: result.message });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('move_list_members', `Move contacts to another list — added to the target, then detached from the source.

Both lists must be the same channel. When migrating an audience that should not receive the target list's welcome sequence again, pass trigger_automations=false.

${SELECTION_GUIDE}`, {
        list_id: z.number().describe('Source contact list ID'),
        target_list_id: z.number().describe('Destination contact list ID'),
        source: z.string().optional().describe('Source label on the new membership (default: api_move)'),
        trigger_automations: z.boolean().optional().describe('Start the target list\'s sequences (default: true)'),
        ...selection,
    }, async ({ list_id, ...payload }) => {
        try {
            const result = await api.transferListMembers(list_id, 'move', compact(payload));
            return ok({ ...result.data, message: result.message });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('tag_list_members', `Add or remove tags across a segment of a list. Tags named in "add" are created if they do not exist.

This is the safe way to act on a problem segment: tag it, review it, build a campaign for it — instead of deleting contacts you may still be able to win back.

${SELECTION_GUIDE}`, {
        list_id: z.number().describe('Contact list ID'),
        add: z.array(z.string()).optional().describe('Tag names to add'),
        remove: z.array(z.string()).optional().describe('Tag names to remove'),
        ...selection,
    }, async ({ list_id, ...payload }) => {
        try {
            const result = await api.tagListMembers(list_id, compact(payload));
            return ok({ ...result.data, message: result.message });
        }
        catch (error) {
            return fail(error);
        }
    });
}
//# sourceMappingURL=list-members.js.map
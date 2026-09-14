/**
 * NetSendo MCP Server - Funnel Tools
 *
 * Tools for managing automation funnels (sequences)
 */
import { z } from 'zod';
import { compact, fail, ok } from './helpers.js';
/**
 * Settings shared by add_funnel_step and update_funnel_step. Every key is
 * optional; which ones matter depends on the step type.
 */
const stepSettings = {
    message_id: z.number().nullable().optional().describe('email: ID of the email message (campaign) to send'),
    sms_content: z.string().max(1600).nullable().optional().describe('sms: the text to send; placeholders like [[first_name]] are filled in. The subscriber needs a phone number'),
    delay_value: z.number().int().min(1).nullable().optional().describe('delay: how long to wait'),
    delay_unit: z.enum(['minutes', 'hours', 'days', 'weeks']).nullable().optional().describe('delay: unit of delay_value'),
    wait_until_type: z.enum(['specific_date', 'day_of_week', 'business_hours']).nullable().optional()
        .describe('wait_until: specific_date (wait_until_date + wait_until_time), day_of_week (wait_until_day + wait_until_time) or business_hours (Mon-Fri 9:00-17:00, no extra settings)'),
    wait_until_date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).nullable().optional().describe('wait_until specific_date: date as YYYY-MM-DD'),
    wait_until_time: z.string().regex(/^\d{2}:\d{2}$/).nullable().optional().describe('wait_until: time as HH:MM (default 00:00)'),
    wait_until_day: z.number().int().min(1).max(7).nullable().optional().describe('wait_until day_of_week: ISO weekday, 1 = Monday ... 7 = Sunday'),
    wait_until_timezone: z.string().nullable().optional().describe('wait_until: IANA time zone, e.g. Europe/Warsaw (default: the account owner\'s)'),
    condition_type: z.enum(['email_opened', 'email_clicked', 'link_clicked', 'tag_exists', 'field_value', 'task_completed']).nullable().optional()
        .describe('condition: what to check. Connect its yes/no paths with branch (add_funnel_step) or next_step_yes_id/next_step_no_id'),
    condition_config: z.record(z.unknown()).nullable().optional()
        .describe('condition settings by type: email_opened/email_clicked {message_id?} (default: the last email this funnel sent); link_clicked {url, message_id?}; tag_exists {tag} (tag name); field_value {field, operator: equals|not_equals|contains|not_empty|empty, value}; task_completed {task_id}'),
    wait_for_condition: z.boolean().optional().describe('condition: wait until the condition is met instead of taking the no path at once'),
    retry_enabled: z.boolean().optional().describe('condition with wait_for_condition: send reminders while waiting'),
    retry_max_attempts: z.number().int().min(1).max(10).optional().describe('reminders: how many to send before the exhausted action'),
    retry_interval_value: z.number().int().min(1).optional().describe('reminders: interval between reminders'),
    retry_interval_unit: z.enum(['hours', 'days']).optional().describe('reminders: unit of retry_interval_value'),
    retry_message_id: z.number().nullable().optional().describe('reminders: email to send (default: the email the condition is about)'),
    retry_exhausted_action: z.enum(['continue', 'exit', 'unsubscribe']).optional()
        .describe('after the last reminder and one more interval: continue (no path), exit the funnel, or unsubscribe from the trigger list and exit'),
    action_type: z.enum(['add_tag', 'remove_tag', 'move_to_list', 'copy_to_list', 'webhook', 'unsubscribe', 'notify']).nullable().optional()
        .describe('action: what to do'),
    action_config: z.record(z.unknown()).nullable().optional()
        .describe('action settings by type: add_tag/remove_tag {tag} (name; add_tag creates a missing tag); move_to_list {list_id, from_list_id?} (default source: the trigger list); copy_to_list {list_id}; webhook {url, method?, data?}; unsubscribe {list_id?} (default: the trigger list); notify {email?, subject?, message?} (default recipient: the account owner; {{subscriber_email}}, {{subscriber_name}}, {{funnel_name}} are filled in)'),
    goal_name: z.string().max(255).nullable().optional().describe('goal: name of the conversion'),
    goal_type: z.enum(['purchase', 'signup', 'page_visit', 'tag_added', 'custom', 'webhook']).nullable().optional().describe('goal: kind of conversion'),
    goal_value: z.number().min(0).nullable().optional().describe('goal: value recorded with the conversion'),
    goal_config: z.record(z.unknown()).nullable().optional().describe('goal: extra settings'),
};
/**
 * A step with the settings of its type and its connections, without the
 * columns that belong to other types.
 */
function describeStep(step) {
    const byType = {
        email: ['message_id'],
        sms: ['sms_content'],
        delay: ['delay_value', 'delay_unit'],
        wait_until: ['wait_until_type', 'wait_until_date', 'wait_until_time', 'wait_until_day', 'wait_until_timezone'],
        condition: [
            'condition_type', 'condition_config', 'wait_for_condition', 'retry_enabled', 'retry_max_attempts',
            'retry_interval_value', 'retry_interval_unit', 'retry_message_id', 'retry_exhausted_action',
        ],
        action: ['action_type', 'action_config'],
        goal: ['goal_name', 'goal_type', 'goal_value', 'goal_config'],
    };
    const settings = Object.fromEntries((byType[step.type] ?? []).map((key) => [key, step[key]]));
    return {
        id: step.id,
        type: step.type,
        name: step.name,
        order: step.order,
        ...settings,
        next_step_id: step.next_step_id,
        ...(step.type === 'condition' ? { next_step_yes_id: step.next_step_yes_id, next_step_no_id: step.next_step_no_id } : {}),
    };
}
export function registerFunnelTools(server, api) {
    // List Funnels
    server.tool('list_funnels', 'List all automation funnels with optional filtering.', {
        status: z.string().optional().describe('Filter by status (draft, active, paused)'),
        trigger_type: z.string().optional().describe('Filter by trigger type (list_signup, tag_added, form_submit, manual)'),
        search: z.string().optional().describe('Search funnels by name'),
        page: z.number().optional().describe('Page number'),
        per_page: z.number().optional().describe('Items per page'),
    }, async ({ status, trigger_type, search, page, per_page }) => {
        try {
            const funnels = await api.listFunnels({ status, trigger_type, search, page, per_page });
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            funnels: funnels.data.map(f => ({
                                id: f.id,
                                name: f.name,
                                status: f.status,
                                trigger_type: f.trigger_type,
                                trigger_list: f.trigger_list?.name ?? null,
                                subscribers_count: f.subscribers_count,
                                completed_count: f.completed_count,
                                steps_count: f.steps?.length ?? 0,
                            })),
                            pagination: funnels.meta,
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
    // Get Funnel Details
    server.tool('get_funnel', 'Get detailed information about a funnel including all steps and statistics.', {
        funnel_id: z.number().describe('Funnel ID'),
    }, async ({ funnel_id }) => {
        try {
            const funnel = await api.getFunnel(funnel_id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            id: funnel.id,
                            name: funnel.name,
                            slug: funnel.slug,
                            status: funnel.status,
                            trigger: {
                                type: funnel.trigger_type,
                                list: funnel.trigger_list?.name ?? null,
                                tag: funnel.trigger_tag,
                            },
                            stats: funnel.stats,
                            steps: funnel.steps?.map(describeStep),
                            created_at: funnel.created_at,
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
    // Create Funnel
    server.tool('create_funnel', 'Create a new automation funnel. Set a trigger and add steps after creation.', {
        name: z.string().describe('Funnel name'),
        trigger_type: z.enum(['list_signup', 'tag_added', 'form_submit', 'manual']).describe('What triggers the funnel'),
        trigger_list_id: z.number().optional().describe('For list_signup: the contact list ID'),
        trigger_tag: z.string().optional().describe('For tag_added: the tag name'),
        trigger_form_id: z.number().optional().describe('For form_submit: the form ID'),
    }, async ({ name, trigger_type, trigger_list_id, trigger_tag, trigger_form_id }) => {
        try {
            const funnel = await api.createFunnel({
                name,
                trigger_type,
                trigger_list_id,
                trigger_tag,
                trigger_form_id,
            });
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: 'Funnel created. Add steps using add_funnel_step.',
                            funnel: {
                                id: funnel.id,
                                name: funnel.name,
                                slug: funnel.slug,
                                status: funnel.status,
                            },
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
    // Update Funnel
    server.tool('update_funnel', 'Rename a funnel or change its trigger. A list_signup funnel starts when someone signs up to trigger_list_id, tag_added when trigger_tag is added, form_submit on trigger_form_id; manual only through enroll_subscriber_in_funnel.', {
        funnel_id: z.number().describe('Funnel ID'),
        name: z.string().max(255).optional().describe('New name'),
        trigger_type: z.enum(['list_signup', 'tag_added', 'form_submit', 'manual']).optional().describe('What starts the funnel'),
        trigger_list_id: z.number().nullable().optional().describe('For list_signup: the contact list ID'),
        trigger_tag: z.string().nullable().optional().describe('For tag_added: the tag name'),
        trigger_form_id: z.number().nullable().optional().describe('For form_submit: the form ID'),
    }, async ({ funnel_id, ...changes }) => {
        try {
            const funnel = await api.updateFunnel(funnel_id, compact(changes));
            return ok({
                success: true,
                message: 'Funnel updated',
                funnel: {
                    id: funnel.id,
                    name: funnel.name,
                    status: funnel.status,
                    trigger_type: funnel.trigger_type,
                    trigger_list_id: funnel.trigger_list_id,
                    trigger_tag: funnel.trigger_tag,
                    trigger_form_id: funnel.trigger_form_id,
                },
            });
        }
        catch (error) {
            return fail(error);
        }
    });
    // Add Funnel Step
    server.tool('add_funnel_step', 'Add a step to a funnel and connect it: after after_step_id, or else after the last step, continuing where that step led. After a condition step the new step goes on its "yes" path unless branch is "no". Set the settings of the step type (see each parameter). Use get_funnel to see step IDs and connections.', {
        funnel_id: z.number().describe('Funnel ID'),
        type: z.enum(['email', 'sms', 'delay', 'wait_until', 'condition', 'action', 'goal', 'end']).describe('Step type'),
        name: z.string().max(255).describe('Step name for reference'),
        after_step_id: z.number().optional().describe('Connect the new step after this step ID (default: the last step)'),
        branch: z.enum(['yes', 'no']).optional().describe('When after_step_id (or the last step) is a condition: its path to continue on (default: yes)'),
        ...stepSettings,
    }, async ({ funnel_id, ...input }) => {
        try {
            const step = await api.addFunnelStep(funnel_id, compact(input));
            return ok({
                success: true,
                message: 'Step added to funnel',
                step: describeStep(step),
            });
        }
        catch (error) {
            return fail(error);
        }
    });
    // Update Funnel Step
    server.tool('update_funnel_step', 'Change a funnel step: any of its settings, and its connections. next_step_id is where the step leads; a condition uses next_step_yes_id and next_step_no_id. Pass null to disconnect. Connected steps must belong to the same funnel.', {
        funnel_id: z.number().describe('Funnel ID'),
        step_id: z.number().describe('Step ID'),
        type: z.enum(['start', 'email', 'sms', 'delay', 'wait_until', 'condition', 'action', 'goal', 'end']).optional().describe('New step type (the start step keeps its type)'),
        name: z.string().max(255).optional().describe('New step name'),
        next_step_id: z.number().nullable().optional().describe('Step this one leads to'),
        next_step_yes_id: z.number().nullable().optional().describe('condition: step for "yes"'),
        next_step_no_id: z.number().nullable().optional().describe('condition: step for "no"'),
        ...stepSettings,
    }, async ({ funnel_id, step_id, ...changes }) => {
        try {
            const step = await api.updateFunnelStep(funnel_id, step_id, compact(changes));
            return ok({
                success: true,
                message: 'Step updated',
                step: describeStep(step),
            });
        }
        catch (error) {
            return fail(error);
        }
    });
    // Delete Funnel Step
    server.tool('delete_funnel_step', 'Delete a funnel step. Steps that led to it lead to its next step instead, and subscribers on it move on to that step. The start step cannot be deleted.', {
        funnel_id: z.number().describe('Funnel ID'),
        step_id: z.number().describe('Step ID to delete'),
    }, async ({ funnel_id, step_id }) => {
        try {
            await api.deleteFunnelStep(funnel_id, step_id);
            return ok({ success: true, message: 'Step deleted' });
        }
        catch (error) {
            return fail(error);
        }
    });
    // Enroll Subscriber
    server.tool('enroll_subscriber_in_funnel', 'Put a subscriber into an active funnel now (the way to start a funnel with the manual trigger). The funnel runs its steps up to the first wait immediately. A subscriber goes through a funnel once.', {
        funnel_id: z.number().describe('Funnel ID'),
        subscriber_id: z.number().optional().describe('Subscriber ID'),
        email: z.string().email().optional().describe('Subscriber email (instead of subscriber_id)'),
    }, async ({ funnel_id, subscriber_id, email }) => {
        if (subscriber_id === undefined && email === undefined) {
            return fail(new Error('Pass subscriber_id or email'));
        }
        try {
            const enrollment = await api.enrollInFunnel(funnel_id, compact({ subscriber_id, email }));
            return ok({
                success: true,
                message: 'Subscriber enrolled',
                enrollment,
            });
        }
        catch (error) {
            return fail(error, 'The funnel is not active (activate_funnel first) or the subscriber is already enrolled in it.');
        }
    });
    // Activate Funnel
    server.tool('activate_funnel', 'Activate a funnel to start processing new subscribers.', {
        funnel_id: z.number().describe('Funnel ID'),
    }, async ({ funnel_id }) => {
        try {
            const funnel = await api.activateFunnel(funnel_id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: 'Funnel activated',
                            funnel_id: funnel.id,
                            status: funnel.status,
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
    // Pause Funnel
    server.tool('pause_funnel', 'Pause a funnel to stop processing new subscribers.', {
        funnel_id: z.number().describe('Funnel ID'),
    }, async ({ funnel_id }) => {
        try {
            const funnel = await api.pauseFunnel(funnel_id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: 'Funnel paused',
                            funnel_id: funnel.id,
                            status: funnel.status,
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
    // Get Funnel Stats
    server.tool('get_funnel_stats', 'Get statistics for a funnel including subscriber counts and completion rates.', {
        funnel_id: z.number().describe('Funnel ID'),
    }, async ({ funnel_id }) => {
        try {
            const stats = await api.getFunnelStats(funnel_id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify(stats, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
    // Delete Funnel
    server.tool('delete_funnel', 'Delete a funnel. Cannot delete active funnels - pause first.', {
        funnel_id: z.number().describe('Funnel ID to delete'),
    }, async ({ funnel_id }) => {
        try {
            await api.deleteFunnel(funnel_id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: 'Funnel deleted',
                        }, null, 2),
                    }],
            };
        }
        catch (error) {
            return {
                content: [{ type: 'text', text: `Error: ${error.message}` }],
                isError: true,
            };
        }
    });
}
//# sourceMappingURL=funnels.js.map
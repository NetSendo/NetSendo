/**
 * NetSendo MCP Server - Automation Rule Tools
 *
 * Automation rules: "when <trigger event> happens, if <conditions> pass, run
 * <actions>". They complement funnels (multi-step sequences): a rule reacts
 * to a single event, e.g. tag everyone who joins a list or email the owner
 * when a deal is won.
 */
import { z } from 'zod';
import { compact, fail, ok } from './helpers.js';
const EXAMPLE = `{
  "name": "Welcome new newsletter readers",
  "trigger_event": "subscriber_signup",
  "trigger_config": { "list_id": 12 },
  "conditions": [{ "type": "field_contains", "field": "email", "value": "@acme.com" }],
  "condition_logic": "all",
  "actions": [
    { "type": "add_tag", "config": { "tag_name": "acme-lead" } },
    { "type": "send_email", "config": { "message_id": 345 } }
  ],
  "is_active": true
}`;
const condition = z.object({
    type: z.string().describe('Condition type key from get_automation_options (conditions[].key), e.g. tag_exists, field_equals, list_is'),
    field: z.string().optional().describe('field_* conditions only: a standard field (email, first_name, ...) or a custom field name'),
    value: z.union([z.string(), z.number()]).optional()
        .describe('What to compare with: a tag/list/message id for tag_*, list_*, email_*_message; text for field_*; days for subscribed_days_ago. Omit for field_is_empty / field_is_not_empty'),
});
const action = z.object({
    type: z.string().describe('Action type key from get_automation_options (actions[].key), e.g. add_tag, send_email, move_to_list, start_funnel, call_webhook, notify_admin'),
    config: z.record(z.unknown()).optional()
        .describe('Settings of the action type (actions[].config in get_automation_options), e.g. add_tag {tag_id} or {tag_name}; send_email {message_id}; copy_to_list {list_id}; start_funnel {funnel_id}; call_webhook {url, method?, headers?}'),
});
const ruleFields = {
    name: z.string().max(255).describe('Rule name'),
    description: z.string().max(1000).nullable().optional().describe('Optional description'),
    trigger_event: z.string()
        .describe('Trigger event key from get_automation_options (triggers[].key with available: true), e.g. subscriber_signup, tag_added, email_clicked, form_submitted, purchase, crm_deal_won'),
    trigger_config: z.record(z.unknown()).optional()
        .describe('Filters of the trigger (triggers[].config), e.g. subscriber_signup {list_id, form_id}; tag_added {tag_id}; email_opened {message_id, list_id}; date_reached {date: "YYYY-MM-DD"} (required). {} = every event of that type in the account. Keys the trigger does not use are rejected'),
    conditions: z.array(condition).optional().describe('Extra checks on the subscriber/event; [] or omitted for none'),
    condition_logic: z.enum(['all', 'any']).optional().describe('all = every condition must pass (default), any = at least one'),
    actions: z.array(action).min(1).describe('Actions run in order (at least one)'),
    is_active: z.boolean().optional().describe('Whether the rule runs (default true)'),
    limit_per_subscriber: z.boolean().optional().describe('Limit how often the rule runs for one subscriber'),
    limit_count: z.number().int().min(1).nullable().optional().describe('With limit_per_subscriber: successful runs allowed per subscriber in limit_period'),
    limit_period: z.enum(['hour', 'day', 'week', 'month', 'ever']).nullable().optional().describe('With limit_per_subscriber: the period limit_count applies to'),
};
const READ_ONLY_HINT = 'This rule is read-only here. Rules with read_only: true are kept in sync with a message (autoresponder) trigger and are overwritten whenever that message is saved: change the message instead, or use duplicate_automation for an editable copy.';
function summarize(rule) {
    return {
        id: rule.id,
        name: rule.name,
        trigger_event: rule.trigger_event,
        trigger_config: rule.trigger_config,
        conditions: rule.conditions.length,
        actions: rule.actions.map((a) => a.type),
        is_active: rule.is_active,
        execution_count: rule.execution_count,
        last_executed_at: rule.last_executed_at,
        is_system: rule.is_system,
        read_only: rule.read_only,
    };
}
export function registerAutomationTools(server, api) {
    server.tool('get_automation_options', 'Get the catalogue for building automation rules: every trigger event (key, label, available, description, and the trigger_config keys it accepts), every condition type (value_spec / field_spec) and every action type (config keys, one_of_required). Each field spec gives type (integer|number|string|boolean|date|enum|url|email|object), required, description, example, enum values, and ref — the kind of id it takes (list, tag, message, funnel, form, crm_pipeline, crm_stage, crm_company, user, field), which must belong to this account. Also returns an example rule. Call this before create_automation or update_automation. Triggers and conditions with available: false are rejected because they would never run.', {}, async () => {
        try {
            const result = await api.request('get', '/automations/options');
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('list_automations', 'List the account\'s automation rules (newest first) with trigger, action types, active state and run counts. read_only: true marks rules synced from a message trigger; is_system marks NetSendo\'s default automations.', {
        trigger_event: z.string().optional().describe('Only rules with this trigger event'),
        is_active: z.boolean().optional().describe('Only active (true) or inactive (false) rules'),
        search: z.string().optional().describe('Search in rule names'),
        page: z.number().int().min(1).optional().describe('Page (default 1)'),
        per_page: z.number().int().min(1).max(100).optional().describe('Rules per page (default 25)'),
    }, async ({ trigger_event, is_active, search, page, per_page }) => {
        try {
            const result = await api.request('get', '/automations', {
                params: compact({ trigger_event, is_active: is_active === undefined ? undefined : (is_active ? 1 : 0), search, page, per_page }),
            });
            return ok({ automations: result.data.map(summarize), meta: result.meta });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('get_automation', 'Get one automation rule in full: trigger_event, trigger_config, conditions, actions with their config, limits, run counts, 7-day stats, and managed_by/read_only (rules synced from a message trigger cannot be changed here).', {
        automation_id: z.number().describe('Automation rule ID'),
    }, async ({ automation_id }) => {
        try {
            const result = await api.request('get', `/automations/${automation_id}`);
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('create_automation', `Create an automation rule: when trigger_event happens (optionally filtered by trigger_config), and the conditions pass, run the actions in order. Call get_automation_options first to pick valid trigger, condition and action keys and their config fields. Ids (list, tag, message, funnel, form, CRM pipeline/stage, user) must belong to this account — look them up with list_contact_lists, list_tags, list_campaigns, list_funnels, list_custom_fields. send_email needs a message that is not a draft; add_tag can create a tag by tag_name. The rule is active unless is_active: false. Example payload:\n${EXAMPLE}`, ruleFields, async (input) => {
        try {
            const result = await api.request('post', '/automations', { data: compact(input) });
            return ok({ message: `Automation "${result.data.name}" created (id ${result.data.id})`, automation: result.data });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('update_automation', `Change an automation rule. Only the fields you send change; the rest keep their values. conditions and actions are replaced as a whole when sent (get_automation shows the current ones). Changing trigger_event re-checks the stored trigger_config against the new trigger, so send a matching trigger_config with it. Call get_automation_options first for valid keys. Rules with read_only: true (synced from a message trigger) cannot be changed (409). Example: {"automation_id": 7, "trigger_config": {"list_id": 12}, "actions": [{"type": "add_tag", "config": {"tag_id": 5}}]}`, {
        automation_id: z.number().describe('Automation rule ID'),
        ...ruleFields,
        name: ruleFields.name.optional(),
        trigger_event: ruleFields.trigger_event.optional(),
        actions: ruleFields.actions.optional(),
    }, async ({ automation_id, ...fields }) => {
        try {
            const result = await api.request('put', `/automations/${automation_id}`, { data: compact(fields) });
            return ok({ message: `Automation ${automation_id} updated`, automation: result.data });
        }
        catch (error) {
            return fail(error, READ_ONLY_HINT);
        }
    });
    server.tool('set_automation_active', 'Activate or deactivate an automation rule (is_active), or flip it when is_active is omitted. Deactivating is the safe way to stop a rule without losing it. Not possible for read_only rules (synced from a message trigger).', {
        automation_id: z.number().describe('Automation rule ID'),
        is_active: z.boolean().optional().describe('true = run the rule, false = pause it; omit to flip the current state'),
    }, async ({ automation_id, is_active }) => {
        try {
            const result = await api.request('post', `/automations/${automation_id}/toggle`, {
                data: compact({ is_active }),
            });
            return ok({
                message: `Automation ${automation_id} is now ${result.data.is_active ? 'active' : 'inactive'}`,
                automation: summarize(result.data),
            });
        }
        catch (error) {
            return fail(error, READ_ONLY_HINT);
        }
    });
    server.tool('duplicate_automation', 'Copy an automation rule. The copy is named "[KOPIA] <name>", starts inactive, and is an ordinary editable rule (also when the original is a default or message-synced rule). Activate it with set_automation_active after reviewing it.', {
        automation_id: z.number().describe('Automation rule ID to copy'),
    }, async ({ automation_id }) => {
        try {
            const result = await api.request('post', `/automations/${automation_id}/duplicate`);
            return ok({ message: `Automation copied as id ${result.data.id} (inactive)`, automation: result.data });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('delete_automation', 'Delete an automation rule permanently, with its execution log. Consider set_automation_active(false) instead. NetSendo default automations (is_system) need confirm=true once the user has approved; read_only rules (synced from a message trigger) cannot be deleted here.', {
        automation_id: z.number().describe('Automation rule ID'),
        confirm: z.boolean().optional().describe('Required (true) to delete a default automation (is_system: true)'),
    }, async ({ automation_id, confirm }) => {
        try {
            await api.request('delete', `/automations/${automation_id}`, { params: compact({ confirm: confirm ? 1 : undefined }) });
            return ok({ message: `Automation ${automation_id} deleted` });
        }
        catch (error) {
            return fail(error, 'This is a NetSendo default automation (or a message-synced rule). For a default automation, re-run with confirm=true once the user has approved — or deactivate it instead. A message-synced rule cannot be deleted here: change the message\'s trigger.');
        }
    });
    server.tool('get_automation_logs', 'Get the execution log of an automation rule, newest first: status (success | partial | failed | skipped — skipped means the per-subscriber limit was reached), subscriber, each action\'s result or error, the event data, and totals per status. Use it to check that a rule fires and to debug failing actions.', {
        automation_id: z.number().describe('Automation rule ID'),
        status: z.enum(['success', 'partial', 'failed', 'skipped']).optional().describe('Only runs with this status'),
        page: z.number().int().min(1).optional().describe('Page (default 1)'),
        per_page: z.number().int().min(1).max(100).optional().describe('Runs per page (default 20)'),
    }, async ({ automation_id, status, page, per_page }) => {
        try {
            const result = await api.request('get', `/automations/${automation_id}/logs`, {
                params: compact({ status, page, per_page }),
            });
            return ok({ logs: result.data, meta: result.meta });
        }
        catch (error) {
            return fail(error);
        }
    });
}
//# sourceMappingURL=automations.js.map
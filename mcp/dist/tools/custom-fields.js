/**
 * NetSendo MCP Server - Custom Field Tools
 *
 * Define the extra subscriber fields that personalise messages ([[name]])
 * and appear in forms. Listing is list_custom_fields (lists.ts) and
 * list_placeholders (placeholders.ts); values are set per subscriber through
 * create_subscriber / update_subscriber (custom_fields).
 */
import { z } from 'zod';
import { compact, fail, ok } from './helpers.js';
const FIELD_TYPES = ['text', 'number', 'date', 'select', 'checkbox', 'radio'];
const NAME_RULES = `Technical name: letters, digits and "_", starting with a letter (e.g. "city", "company_size"); it becomes the placeholder [[name]]. Reserved (rejected): email, first_name, last_name, phone, device, ip_address, user_agent, subscribed_at, confirmed_at, last_opened_at, last_clicked_at, opens_count, clicks_count, source, tags, status, id, created_at, updated_at, unsubscribe_link, unsubscribe_url, activate, unsubscribe. Must be unique in the account within the same scope (one global "city" plus one "city" per list is allowed).`;
const fieldSettings = {
    label: z.string().min(1).max(255).describe('Label shown to subscribers in forms and in the panel, e.g. "City"'),
    description: z.string().nullable().optional().describe('Internal note for admins'),
    type: z.enum(FIELD_TYPES).describe('text | number | date | select (dropdown) | radio | checkbox. select and radio need options'),
    options: z.array(z.string().max(255)).nullable().optional()
        .describe('Choices for select / radio / checkbox, e.g. ["S","M","L"]. Ignored (cleared) for text, number and date; empty strings are dropped'),
    default_value: z.string().max(255).nullable().optional().describe('Value used when the subscriber has none'),
    is_public: z.boolean().optional().describe('Visible in public signup forms (default true)'),
    is_required: z.boolean().optional().describe('Required in forms (default false)'),
    is_static: z.boolean().optional().describe('Only editable by the admin, never by the subscriber (default false)'),
};
export function registerCustomFieldTools(server, api) {
    server.tool('get_custom_field', 'Get one custom field with all its settings: name, placeholder ([[name]]), label, type, options, default_value, is_public / is_required / is_static, scope (global or list) and contact_list_id. Use list_custom_fields or list_placeholders to find IDs.', {
        field_id: z.number().int().describe('Custom field ID'),
    }, async ({ field_id }) => {
        try {
            const res = await api.request('get', `/custom-fields/${field_id}`);
            return ok(res.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('create_custom_field', `Create a custom subscriber field. Without contact_list_id the field is GLOBAL (available to every list); with contact_list_id it belongs to that list only (the scope cannot be changed later).

${NAME_RULES}

After creating it, use [[name]] in email/SMS content and set values with create_subscriber / update_subscriber (custom_fields: {"name": "value"}). Check list_custom_fields first to avoid duplicates. Requires the lists:write permission.`, {
        name: z.string().min(1).max(100).regex(/^[a-zA-Z][a-zA-Z0-9_]*$/).describe('Technical name (see rules above)'),
        ...fieldSettings,
        contact_list_id: z.number().int().optional().describe('Make the field specific to this contact list (omit for a global field)'),
    }, async (input) => {
        try {
            const res = await api.request('post', '/custom-fields', { data: compact(input) });
            return ok({ success: true, message: res.message, custom_field: res.data });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('update_custom_field', `Change a custom field. Only the fields you pass are changed; scope / contact_list_id cannot be changed (delete and re-create instead).

Careful: renaming changes the placeholder — messages that still use the old [[name]] will render it empty. Changing type to text/number/date clears options; changing to select/radio requires options. Stored subscriber values are kept as they are. Requires lists:write.`, {
        field_id: z.number().int().describe('Custom field ID'),
        name: z.string().min(1).max(100).regex(/^[a-zA-Z][a-zA-Z0-9_]*$/).optional().describe('New technical name (same rules as create_custom_field)'),
        label: fieldSettings.label.optional(),
        description: fieldSettings.description,
        type: fieldSettings.type.optional(),
        options: fieldSettings.options,
        default_value: fieldSettings.default_value,
        is_public: fieldSettings.is_public,
        is_required: fieldSettings.is_required,
        is_static: fieldSettings.is_static,
    }, async ({ field_id, ...changes }) => {
        try {
            const res = await api.request('patch', `/custom-fields/${field_id}`, {
                data: compact(changes),
            });
            return ok({ success: true, message: res.message, custom_field: res.data });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('delete_custom_field', `Delete a custom field AND every value subscribers have stored in it (cannot be undone). Messages still using its [[name]] placeholder will render it empty.

If any subscriber has a value, the API answers 409 with values_count — tell the user how many values will be lost and re-run with confirm=true only after they approve. Requires lists:write.`, {
        field_id: z.number().int().describe('Custom field ID'),
        confirm: z.boolean().optional().describe('Set true to delete a field that still holds subscriber values'),
    }, async ({ field_id, confirm }) => {
        try {
            const res = await api.request('delete', `/custom-fields/${field_id}`, {
                params: confirm ? { confirm: 1 } : undefined,
            });
            return ok({ success: true, ...res });
        }
        catch (error) {
            return fail(error, 'The field still holds subscriber values. Tell the user how many will be lost, and re-run with confirm=true once they approve.');
        }
    });
}
//# sourceMappingURL=custom-fields.js.map
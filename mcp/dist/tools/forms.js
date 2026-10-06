/**
 * NetSendo MCP Server - Subscription Form Tools
 *
 * Signup forms of a contact list: create, configure, style, duplicate and get
 * the embed code (HTML / JavaScript / iframe) for a website.
 */
import { z } from 'zod';
import { compact, fail, ok } from './helpers.js';
const fieldSchema = z.object({
    id: z.string().regex(/^(email|fname|lname|phone|custom_[0-9]+)$/)
        .describe('email (mandatory, always required), fname, lname, phone, or custom_<custom field id> (see list_custom_fields)'),
    label: z.string().max(255).optional().describe('Label (default: the field\'s own label)'),
    placeholder: z.string().max(255).optional().describe('Placeholder text'),
    required: z.boolean().optional().describe('Must be filled in (default: false; email is always required)'),
    type: z.string().max(50).optional().describe('Input type (default from the field: email, text, phone, number, date...)'),
    order: z.number().int().optional().describe('Position (default: order in the array)'),
}).passthrough();
const stylesSchema = z.object({
    bgcolor: z.string().optional().describe('Form background colour (#RRGGBB)'),
    bgcolor_opacity: z.number().min(0).max(100).optional(),
    text_color: z.string().optional(),
    border_color: z.string().optional(),
    border_width: z.number().optional(),
    border_radius: z.number().optional(),
    padding: z.number().optional(),
    font_family: z.string().optional(),
    field_bgcolor: z.string().optional(),
    field_text: z.string().optional(),
    field_border_color: z.string().optional(),
    field_border_color_active: z.string().optional(),
    field_border_radius: z.number().optional(),
    field_height: z.number().optional(),
    label_font_size: z.number().optional(),
    label_font_weight: z.number().optional(),
    placeholder_color: z.string().optional(),
    submit_text: z.string().optional().describe('Button text (default: "Zapisz się")'),
    submit_color: z.string().optional().describe('Button colour'),
    submit_text_color: z.string().optional(),
    submit_hover_color: z.string().optional(),
    submit_border_radius: z.number().optional(),
    submit_font_size: z.number().optional(),
    submit_align: z.enum(['left', 'center', 'right']).optional(),
    submit_full_width: z.boolean().optional(),
    shadow_enabled: z.boolean().optional(),
    gradient_enabled: z.boolean().optional(),
    gradient_from: z.string().optional(),
    gradient_to: z.string().optional(),
    animation_enabled: z.boolean().optional(),
    animation_type: z.enum(['fadeIn', 'slideUp', 'pulse', 'bounce']).optional(),
}).passthrough().describe('Style keys (colours as #RRGGBB, sizes in px). Merged key by key into the stored styles; unset keys use the defaults. Returned in full by get_form.');
const formConfigShape = {
    status: z.enum(['active', 'draft', 'disabled']).optional().describe('Only active forms accept signups (new forms are draft)'),
    type: z.enum(['inline', 'popup', 'embedded']).optional().describe('Display type (default: inline)'),
    fields: z.array(fieldSchema).min(1).optional().describe('Fields in display order; replaces the whole list. Must contain email. Default: email only'),
    design_preset: z.enum(['default', 'modern_dark', 'glassmorphism', 'minimal_light', 'gradient_style']).optional()
        .describe('Restyle from a built-in design (applied over the defaults, then "styles" on top)'),
    styles: stylesSchema.optional(),
    layout: z.enum(['vertical', 'horizontal', 'grid']).optional(),
    label_position: z.enum(['above', 'left', 'hidden']).optional(),
    show_placeholders: z.boolean().optional(),
    double_optin: z.boolean().nullable().optional().describe('Override the list\'s double opt-in (null = inherit from the list)'),
    require_policy: z.boolean().optional().describe('Show a privacy-policy checkbox that must be ticked'),
    policy_url: z.string().url().max(500).nullable().optional(),
    use_list_redirect: z.boolean().optional().describe('Use the list\'s pages (update_contact_list settings.pages) after signup instead of redirect_url'),
    redirect_url: z.string().url().max(500).nullable().optional().describe('Where to send the contact after signing up'),
    success_title: z.string().max(255).nullable().optional(),
    success_message: z.string().nullable().optional().describe('Message shown after a signup when there is no redirect'),
    error_message: z.string().nullable().optional(),
    coregister_lists: z.array(z.number()).optional().describe('Other email lists of the account the contact is also signed up to'),
    coregister_optional: z.boolean().optional().describe('Let the contact choose the co-registration lists'),
    captcha_enabled: z.boolean().optional(),
    captcha_provider: z.enum(['recaptcha_v2', 'recaptcha_v3', 'hcaptcha', 'turnstile']).optional(),
    captcha_site_key: z.string().nullable().optional(),
    captcha_secret_key: z.string().optional().describe('Write-only; stored encrypted. Omit to keep the stored secret'),
    honeypot_enabled: z.boolean().optional().describe('Hidden anti-bot field (default: true)'),
};
export function registerFormTools(server, api) {
    server.tool('list_forms', 'List the account\'s subscription (signup) forms with status, list, submission count and hosted URL. Filter by list_id or status. Use get_form for the full configuration and embed code.', {
        list_id: z.number().optional().describe('Only forms that sign up to this list'),
        status: z.enum(['active', 'draft', 'disabled']).optional(),
        search: z.string().optional().describe('Match on the form name'),
        page: z.number().optional(),
        per_page: z.number().min(1).max(100).optional().describe('Default 25'),
    }, async (input) => {
        try {
            const result = await api.request('get', '/forms', { params: compact(input) });
            return ok({ forms: result.data, pagination: result.meta });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('get_form', `Get a subscription form: fields, full styles, behaviour (double opt-in override, redirects, policy, co-registration, captcha, honeypot), integrations, and how to publish it:
- urls.hosted: a ready page with the form, to link to directly
- embed.html: self-contained HTML + CSS to paste into a website
- embed.js: a container + script tag that renders the form
- embed.iframe: an iframe of the hosted page
The form only accepts signups while status is "active".`, {
        form_id: z.number().describe('Form ID'),
    }, async ({ form_id }) => {
        try {
            const result = await api.request('get', `/forms/${form_id}`);
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('create_form', `Create a subscription form for an email list. It starts as a draft unless status "active" is given; the response includes the embed codes (see get_form).

Fields can be given by id only — label, type and placeholder come from the field definition. Minimal example: {"name":"Footer signup","contact_list_id":1}. Fuller: {"name":"Ebook","contact_list_id":1,"status":"active","fields":[{"id":"fname","required":true},{"id":"email"}],"design_preset":"modern_dark","styles":{"submit_text":"Send me the ebook"},"redirect_url":"https://example.com/thanks"}`, {
        name: z.string().max(255).describe('Internal form name'),
        contact_list_id: z.number().describe('Email list the form signs contacts up to'),
        ...formConfigShape,
    }, async (input) => {
        try {
            const result = await api.request('post', '/forms', { data: compact(input) });
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('update_form', 'Change a subscription form. Only the keys you send change; "styles" are merged key by key, "fields" replaces the field list. Set status "active" to publish it or "disabled" to stop signups. The embed code stays valid (it is tied to the form\'s slug).', {
        form_id: z.number().describe('Form ID'),
        name: z.string().max(255).optional(),
        contact_list_id: z.number().optional().describe('Move the form to another email list'),
        ...formConfigShape,
    }, async ({ form_id, ...input }) => {
        try {
            const result = await api.request('patch', `/forms/${form_id}`, { data: compact(input) });
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('delete_form', `Delete a subscription form. Every page embedding it stops working; subscribers are kept.

SAFETY: a form that has collected submissions is only deleted with confirm=true (its submission history is deleted too). Tell the user and get explicit approval first. To stop signups without deleting, use update_form status "disabled".`, {
        form_id: z.number().describe('Form ID'),
        confirm: z.boolean().optional().describe('Required when the form has submissions. Only set after the user approved.'),
    }, async ({ form_id, confirm }) => {
        try {
            return ok(await api.request('delete', `/forms/${form_id}`, { params: { confirm: confirm ? 1 : 0 } }));
        }
        catch (error) {
            return fail(error, 'The form has submissions. Ask the user to confirm, then re-run with confirm=true.');
        }
    });
    server.tool('duplicate_form', 'Copy a subscription form (fields, styles, behaviour) as a new draft named "[KOPIA] <name>", with a new slug and therefore new embed code. Integrations and submissions are not copied.', {
        form_id: z.number().describe('Form ID to copy'),
    }, async ({ form_id }) => {
        try {
            const result = await api.request('post', `/forms/${form_id}/duplicate`);
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
}
//# sourceMappingURL=forms.js.map
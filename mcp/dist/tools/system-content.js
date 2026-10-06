/**
 * NetSendo MCP Server - System Emails & System Pages Tools
 *
 * The automatic emails (double opt-in, welcome, unsubscribe confirmations,
 * owner notification) and the pages shown at the end of signup / activation /
 * unsubscribe / preference flows. Each slug has one instance-wide GLOBAL
 * default and optional per-list OVERRIDES (copy-on-write).
 */
import { z } from 'zod';
import { ok, fail, compact } from './helpers.js';
const COPY_ON_WRITE = `How overrides work (copy-on-write):
- Without list_id you work on the GLOBAL default (used by every list without its own version). Editing globals requires the account admin's API key (team members get 403).
- With list_id you work on that list: reads return the list's override if it exists (source="list", is_custom=true), otherwise the global (source="global"). The first update for a list creates the override (fields you omit are copied from the global); later updates edit it. reset_* removes the override so the list falls back to the global.
- Records are addressed by slug + optional list_id; responses also carry the row id and global_id.
- Content is HTML and is stored as sent (no sanitising), exactly like the panel editor. Permissions: lists:read to read, lists:write to change.`;
const EMAIL_SLUGS = `System email slugs:
- signup_confirmation — double opt-in email sent right after signup; MUST contain [[activation-link]]
- activation_confirmation — sent after the subscriber clicked the activation link
- subscription_welcome — welcome email after a signup without confirmation (single opt-in)
- already_active_resubscribe — an active subscriber signed up again
- inactive_resubscribe — an inactive/unsubscribed contact signed up again
- preference_confirm — confirm a preference change; MUST contain [[confirm-link]] (if switched off a built-in text is sent instead)
- data_edit_access — link to edit subscription data; uses [[edit-link]]
- unsubscribe_request — confirm an unsubscribe request; MUST contain [[unsubscribe-link]] (if switched off a built-in text is sent instead)
- unsubscribed_confirmation — sent after unsubscribing; may use [[resubscribe-link]]
- new_subscriber_notification — sent to the LIST OWNER (not the subscriber) on each signup
- activation_email, welcome_email — legacy, not sent by current flows
Placeholders in every system email (subject and body): [[list-name]], [[email]], [[first-name]]/[[fname]], [[!fname]] (Polish vocative), [[last-name]]/[[lname]], [[phone]], [[date]], plus custom fields and {{male|female}} forms (see get_system_email → placeholders.available).`;
const PAGE_SLUGS = `System page slugs:
- signup_success — after a successful form signup
- signup_exists / signup_exists_active / signup_exists_inactive — address already on the list (generic / active / still waiting for activation)
- signup_error — form signup failed
- activation_success / activation_error — activation link valid / invalid or expired
- unsubscribe_confirm — confirmation step before unsubscribing; uses [[unsubscribe-link]]
- unsubscribe_confirm_sent — after the unsubscribe confirmation email was sent
- unsubscribe_success / unsubscribe_error
- preference_confirm_sent / preference_update_success — preference change submitted / confirmed
Pages may use [[list-name]] and the subscriber placeholders ([[email]], [[fname]], custom fields...).`;
const listIdField = z.number().int().positive().optional()
    .describe('Contact list ID. Omit to work on the global default.');
const slugField = (kind) => z.string().regex(/^[A-Za-z0-9_-]+$/)
    .describe(`${kind} slug, e.g. from list_${kind.replace(' ', '_')}s`);
async function call(promise) {
    try {
        return ok(await promise);
    }
    catch (error) {
        return fail(error);
    }
}
export function registerSystemContentTools(server, api) {
    // ---------------------------------------------------------------- emails
    server.tool('list_system_emails', `List the automatic system emails (double opt-in, welcome, unsubscribe confirmation, owner notification...) as seen by one list, or the global defaults.

Each item: id, slug, name, description (what triggers it), subject, is_active, source ("list" = this list's own override, "global" = inherited default), is_custom, contact_list_id, global_id. Body content is omitted unless include_content=true — call get_system_email for one email with its body and placeholders.

${EMAIL_SLUGS}

${COPY_ON_WRITE}`, {
        list_id: listIdField,
        include_content: z.boolean().optional().describe('Include the HTML body of every email (default: false)'),
    }, async ({ list_id, include_content }) => call(api.request('get', '/system-emails', {
        params: compact({ list_id, include_content: include_content ? 1 : undefined }),
    })));
    server.tool('get_system_email', `Get one system email (subject, HTML content, is_active) as resolved for a list — its override if it has one, else the global default — plus placeholders: for_this_slug (links only this email receives, e.g. [[activation-link]]), built_in (always available) and available (all standard, system and custom-field placeholders of the account). Call this before update_system_email so you edit the current text.

${EMAIL_SLUGS}`, {
        slug: slugField('system email'),
        list_id: listIdField,
    }, async ({ slug, list_id }) => call(api.request('get', `/system-emails/${encodeURIComponent(slug)}`, {
        params: compact({ list_id }),
    })));
    server.tool('update_system_email', `Change a system email's subject, HTML content and/or is_active. Send only the fields you want to change.

With list_id: the first call creates that list's override (status 201, meta.created_override=true; omitted fields are copied from the global), later calls edit it. Without list_id: edits the GLOBAL default for all lists without an override — account admin only, and globals cannot be switched off (is_active=false without list_id is rejected).

Keep the required link placeholder of the slug (signup_confirmation → [[activation-link]], unsubscribe_request → [[unsubscribe-link]], preference_confirm → [[confirm-link]], data_edit_access → [[edit-link]]), otherwise subscribers cannot complete the flow.

${COPY_ON_WRITE}`, {
        slug: slugField('system email'),
        list_id: listIdField,
        subject: z.string().max(255).optional().describe('Email subject; placeholders allowed'),
        content: z.string().optional().describe('Full HTML body; stored as sent'),
        is_active: z.boolean().optional().describe('Only with list_id: false stops sending this email for that list'),
    }, async ({ slug, ...body }) => call(api.request('put', `/system-emails/${encodeURIComponent(slug)}`, {
        data: compact(body),
    })));
    server.tool('set_system_email_active', `Switch one system email on or off for ONE list (e.g. stop sending unsubscribed_confirmation or new_subscriber_notification for a list). Creates the list override from the global default if the list has none. Global defaults always stay active, so list_id is required. To bring back the global text and state entirely, use reset_system_email instead.

Note: unsubscribe_request and preference_confirm are confirmations the flow needs — switching them off makes NetSendo send a built-in fallback text, not nothing.`, {
        slug: slugField('system email'),
        list_id: z.number().int().positive().describe('Contact list ID (required)'),
        is_active: z.boolean().describe('true = send this email for the list, false = do not send it'),
    }, async ({ slug, list_id, is_active }) => call(api.request('put', `/system-emails/${encodeURIComponent(slug)}/active`, {
        data: { list_id, is_active },
    })));
    server.tool('reset_system_email', `Delete a list's override of a system email so the list uses the global default again (subject, content and active state). list_id is required — global defaults cannot be deleted. Returns the global the list now uses; meta.deleted=false means the list had no override.`, {
        slug: slugField('system email'),
        list_id: z.number().int().positive().describe('Contact list ID (required)'),
    }, async ({ slug, list_id }) => call(api.request('delete', `/system-emails/${encodeURIComponent(slug)}`, {
        params: { list_id },
    })));
    // ----------------------------------------------------------------- pages
    server.tool('list_system_pages', `List the system pages — the HTML pages NetSendo shows subscribers at the end of signup, activation, unsubscribe and preference flows — as seen by one list, or the global defaults.

Each item: id, slug, name, description (when it is shown), title, access (public/private), url, source ("list" | "global"), is_custom, contact_list_id, global_id. Content is omitted unless include_content=true.

${PAGE_SLUGS}

${COPY_ON_WRITE}`, {
        list_id: listIdField,
        include_content: z.boolean().optional().describe('Include the HTML body of every page (default: false)'),
    }, async ({ list_id, include_content }) => call(api.request('get', '/system-pages', {
        params: compact({ list_id, include_content: include_content ? 1 : undefined }),
    })));
    server.tool('get_system_page', `Get one system page (title, HTML content, access, url) as resolved for a list — its override if present, else the global — plus placeholders (for_this_slug, built_in, available). Call this before update_system_page.

NetSendo displays these pages automatically at the end of its flows; "url" is the address the panel shows for the page.

${PAGE_SLUGS}`, {
        slug: slugField('system page'),
        list_id: listIdField,
    }, async ({ slug, list_id }) => call(api.request('get', `/system-pages/${encodeURIComponent(slug)}`, {
        params: compact({ list_id }),
    })));
    server.tool('update_system_page', `Change a system page's title, HTML content, access and (list overrides only) slug. Send only the fields you want to change.

With list_id: the first call creates the list's override (201, meta.created_override=true; omitted fields copied from the global), later calls edit it. Without list_id: edits the GLOBAL default — account admin only; a global's slug can never change.

slug: avoid changing it. The signup/activation/unsubscribe flows look pages up by their standard slug, so a renamed override is no longer shown — the list then gets the global default (meta.warnings says so). After a rename, address the page by its new slug.

${COPY_ON_WRITE}`, {
        slug: slugField('system page'),
        list_id: listIdField,
        title: z.string().max(255).optional().describe('Page title (browser tab / heading); placeholders allowed'),
        content: z.string().optional().describe('Full HTML body; stored as sent'),
        access: z.enum(['public', 'private']).optional().describe('Visibility flag kept with the page (default: copied from the global, usually public)'),
        new_slug: z.string().regex(/^[A-Za-z0-9_-]+$/).max(100).optional()
            .describe('Only with list_id: rename the list override (letters, digits, - and _). Usually leave unset.'),
    }, async ({ slug, new_slug, ...body }) => call(api.request('put', `/system-pages/${encodeURIComponent(slug)}`, {
        data: compact({ ...body, slug: new_slug }),
    })));
    server.tool('reset_system_page', `Delete a list's override of a system page so the list uses the global default again. list_id is required — global defaults cannot be deleted. Returns the global the list now uses; meta.deleted=false means the list had no override.`, {
        slug: slugField('system page'),
        list_id: z.number().int().positive().describe('Contact list ID (required)'),
    }, async ({ slug, list_id }) => call(api.request('delete', `/system-pages/${encodeURIComponent(slug)}`, {
        params: { list_id },
    })));
}
//# sourceMappingURL=system-content.js.map
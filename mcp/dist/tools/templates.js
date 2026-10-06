/**
 * NetSendo MCP Server - Email Template Tools
 *
 * Tools for listing, reading, creating, editing, duplicating and previewing
 * email templates, their categories, and the visual builder's saved blocks.
 */
import { z } from 'zod';
import { ok, fail, compact } from './helpers.js';
const BLOCK_TYPES = [
    'header',
    'text',
    'image',
    'button',
    'divider',
    'spacer',
    'columns',
    'product',
    'product_grid',
    'social',
    'footer',
];
const TEMPLATE_TYPES = ['email', 'insert', 'signature'];
const blockSchema = z
    .object({
    id: z.string().optional().describe('Block id; generated when omitted'),
    type: z.enum(BLOCK_TYPES).describe('Block type (see list_template_block_types)'),
    content: z.record(z.unknown()).optional().describe('Block content, e.g. text: {html}, button: {text, href, backgroundColor}'),
    settings: z.record(z.unknown()).optional().describe('Block style settings (backgroundColor, padding, margin, borderRadius)'),
})
    .passthrough();
const jsonStructureSchema = z
    .object({
    blocks: z.array(blockSchema).describe('Ordered builder blocks, top to bottom'),
})
    .passthrough();
const templateFields = {
    description: z.string().max(255).nullable().optional().describe('Internal description'),
    preheader: z.string().max(500).nullable().optional().describe('Inbox preview text; placeholders allowed'),
    type: z
        .enum(TEMPLATE_TYPES)
        .optional()
        .describe("'email' (default) = full email template; 'insert' / 'signature' = reusable HTML snippets shown in the editor's Inserts menu"),
    content: z
        .string()
        .nullable()
        .optional()
        .describe('Full email HTML (inline CSS, table layout recommended). This is what the message editor loads when the template is chosen. Pass null to clear it so the builder blocks are used instead'),
    content_plain: z.string().nullable().optional().describe('Optional plain-text version'),
    json_structure: jsonStructureSchema
        .nullable()
        .optional()
        .describe('Visual-builder structure {blocks: [...]}. MJML is regenerated from it on save. Only needed if the template should be editable block-by-block in the drag & drop builder'),
    settings: z
        .record(z.unknown())
        .nullable()
        .optional()
        .describe('Builder global settings: width (600), background_color, content_background, font_family, primary_color, secondary_color, text_color, link_color'),
    category_id: z.number().nullable().optional().describe('Category ID from list_template_categories'),
    category: z.string().max(50).nullable().optional().describe('Category slug (filled automatically from category_id)'),
};
const USAGE_NOTE = `HOW A TEMPLATE IS USED IN A CAMPAIGN:
A campaign sends its own 'content', not the template. To build a campaign from a template, call get_template and pass its 'html' as 'content' (and 'preheader') to create_campaign / update_campaign. Builder-only templates (editor='builder', html=null) only have MJML, which the panel compiles in the browser — for API use prefer HTML templates (set 'content').

PLACEHOLDERS (call list_placeholders for the account's custom fields):
[[first_name]]/[[fname]], [[last_name]], [[email]], [[phone]], [[!fname]] (Polish vocative), [[unsubscribe_link]] (always include), [[unsubscribe_global]], [[manage]], custom fields as [[field_name]], gender forms {{male|female}}.`;
export function registerTemplateTools(server, api) {
    // ---------------------------------------------------------------------
    // Templates
    // ---------------------------------------------------------------------
    server.tool('list_templates', `List email templates: the account's own templates plus the read-only system starter templates.

Each item: id, name, description, preheader, type, category, category_id, is_system, editable, editor ('html' = has HTML content, 'builder' = drag & drop blocks only, 'empty'), has_html, has_blocks, updated_at. Use get_template for the full HTML / builder structure.

Filters: search (name/description), category (slug, e.g. newsletter, promotional, transactional, ecommerce, welcome, notification), category_id, source ('own' | 'system' | 'all', default all), type ('email' default | 'insert' | 'signature' | 'all').`, {
        search: z.string().optional().describe('Search in name and description'),
        category: z.string().optional().describe('Category slug'),
        category_id: z.number().optional().describe('Category ID (list_template_categories)'),
        source: z.enum(['own', 'system', 'all']).optional().describe("'own', 'system' (starter templates) or 'all' (default)"),
        type: z.enum([...TEMPLATE_TYPES, 'all']).optional().describe("Template type (default 'email')"),
        page: z.number().min(1).optional().describe('Page number (default 1)'),
        per_page: z.number().min(1).max(100).optional().describe('Results per page (1-100, default 25)'),
    }, async (params) => {
        try {
            const result = await api.request('get', '/templates', {
                params: compact(params),
            });
            return ok({
                templates: result.data.map((t) => ({
                    id: t.id,
                    name: t.name,
                    description: t.description,
                    type: t.type,
                    category: t.category,
                    category_id: t.category_id,
                    is_system: t.is_system,
                    editable: t.editable,
                    editor: t.editor,
                    updated_at: t.updated_at,
                })),
                pagination: {
                    page: result.meta.current_page,
                    total_pages: result.meta.last_page,
                    total: result.meta.total,
                },
            });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('get_template', `Get one template with its full body: 'html' (the HTML content, or null for builder-only templates), 'mjml' (generated from the builder blocks), 'json_structure' (builder blocks), 'settings' (with defaults), preheader, category, is_system/editable and used_by_messages (own campaigns that reference it).

${USAGE_NOTE}`, {
        template_id: z.number().describe('Template ID'),
    }, async ({ template_id }) => {
        try {
            const result = await api.request('get', `/templates/${template_id}`);
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('create_template', `Create an email template in the account.

Usually send 'content' = complete email HTML (inline CSS, 600px table layout). Such a template is used as-is by the campaign editor; note the drag & drop builder only edits block templates, so an HTML template opened in the builder shows an empty canvas — keep editing HTML templates with update_template.
Alternatively send 'json_structure' = {blocks: [{type, content, settings}]} to create a builder template (see list_template_block_types for block types and their default content); MJML is generated automatically. If both are sent, 'content' takes precedence in the campaign editor.

${USAGE_NOTE}`, {
        name: z.string().max(255).describe('Template name'),
        ...templateFields,
    }, async (params) => {
        try {
            const result = await api.request('post', '/templates', { data: compact(params) });
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('update_template', `Update an own template (partial update — only the fields you pass change). System starter templates are read-only: call duplicate_template first and edit the copy.

Sending json_structure or settings regenerates the MJML. Sending content: null removes the HTML so the builder blocks are used again. Campaigns that were already created from this template keep their own copy of the content and are not changed.`, {
        template_id: z.number().describe('Template ID'),
        name: z.string().max(255).optional().describe('New name'),
        ...templateFields,
    }, async ({ template_id, ...fields }) => {
        try {
            const result = await api.request('put', `/templates/${template_id}`, {
                data: compact(fields),
            });
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('delete_template', `Delete an own template (soft delete, same as the panel). Campaigns created from it keep their content; the response reports used_by_messages. System templates cannot be deleted.`, {
        template_id: z.number().describe('Template ID'),
    }, async ({ template_id }) => {
        try {
            const result = await api.request('delete', `/templates/${template_id}`);
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('duplicate_template', `Copy an own or system starter template into the account and return the editable copy (name defaults to "<name> (kopia)"). This is the way to customise a system template.`, {
        template_id: z.number().describe('Template ID to copy'),
        name: z.string().max(255).optional().describe('Name for the copy'),
    }, async ({ template_id, name }) => {
        try {
            const result = await api.request('post', `/templates/${template_id}/duplicate`, {
                data: compact({ name }),
            });
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('preview_template', `Render a template's placeholders for one of the account's subscribers (subscriber_id) or for sample data (default: Jan Kowalski, jan.kowalski@example.com; override with 'sample'). Unsubscribe/manage links are real signed links for a subscriber and '#preview-…' for sample data; pass list_id to get the list-specific unsubscribe link.

Returns html (HTML templates) or mjml (builder-only templates), the rendered preheader, placeholders_used, and unknown_placeholders (placeholders that no field provides — fix these before sending). Nothing is sent or saved.`, {
        template_id: z.number().describe('Template ID'),
        subscriber_id: z.number().optional().describe('Render for this subscriber'),
        list_id: z.number().optional().describe('List context for the unsubscribe link and custom fields'),
        sample: z
            .object({
            email: z.string().optional(),
            first_name: z.string().optional(),
            last_name: z.string().optional(),
            phone: z.string().optional(),
            gender: z.enum(['male', 'female']).optional(),
        })
            .optional()
            .describe('Sample subscriber values when no subscriber_id is given'),
    }, async ({ template_id, ...data }) => {
        try {
            const result = await api.request('post', `/templates/${template_id}/preview`, {
                data: compact(data),
            });
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('list_template_categories', 'List template categories (system categories such as newsletter, promotional, ecommerce, welcome, transactional, notification, plus the account\'s own). Use the id as category_id in create_template / update_template, or the slug as the list_templates category filter.', {}, async () => {
        try {
            const result = await api.request('get', '/template-categories');
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    // ---------------------------------------------------------------------
    // Builder blocks
    // ---------------------------------------------------------------------
    server.tool('list_template_block_types', `List the drag & drop builder block types (${BLOCK_TYPES.join(', ')}) with their default content and settings. Use this as the schema reference when writing a template's json_structure: each block is {type, content, settings}; 'columns' blocks hold nested blocks in content.columnBlocks (an array per column).`, {}, async () => {
        try {
            const result = await api.request('get', '/template-blocks/types');
            return ok(result);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('list_template_blocks', "List saved blocks (the builder sidebar's \"Saved blocks\"): the account's own blocks plus global blocks (read-only). Each block can be inserted into a template's json_structure.blocks as {type, content, settings}.", {
        type: z.enum(BLOCK_TYPES).optional().describe('Filter by block type'),
        search: z.string().optional().describe('Search by name'),
        page: z.number().min(1).optional().describe('Page number (default 1)'),
        per_page: z.number().min(1).max(100).optional().describe('Results per page (1-100, default 25)'),
    }, async (params) => {
        try {
            const result = await api.request('get', '/template-blocks', { params: compact(params) });
            return ok({
                blocks: result.data,
                pagination: {
                    page: result.meta.current_page,
                    total_pages: result.meta.last_page,
                    total: result.meta.total,
                },
            });
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('create_template_block', 'Save a reusable builder block (e.g. a branded header, footer or CTA button) so it appears under "Saved blocks" in the template builder. content follows the block type\'s shape from list_template_block_types.', {
        name: z.string().max(255).describe('Block name'),
        type: z.enum(BLOCK_TYPES).describe('Block type'),
        content: z.record(z.unknown()).describe('Block content'),
        settings: z.record(z.unknown()).nullable().optional().describe('Block style settings (defaults applied when omitted)'),
    }, async (params) => {
        try {
            const result = await api.request('post', '/template-blocks', { data: compact(params) });
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('update_template_block', 'Update an own saved block (name, content and/or settings). Global blocks are read-only. Templates that already contain the block are not changed.', {
        block_id: z.number().describe('Saved block ID'),
        name: z.string().max(255).optional().describe('New name'),
        content: z.record(z.unknown()).optional().describe('New content (replaces the whole content object)'),
        settings: z.record(z.unknown()).nullable().optional().describe('New settings'),
    }, async ({ block_id, ...fields }) => {
        try {
            const result = await api.request('put', `/template-blocks/${block_id}`, {
                data: compact(fields),
            });
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
    server.tool('delete_template_block', 'Delete an own saved block. Templates that already contain it are not changed.', {
        block_id: z.number().describe('Saved block ID'),
    }, async ({ block_id }) => {
        try {
            const result = await api.request('delete', `/template-blocks/${block_id}`);
            return ok(result.data);
        }
        catch (error) {
            return fail(error);
        }
    });
}
//# sourceMappingURL=templates.js.map
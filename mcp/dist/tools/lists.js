/**
 * NetSendo MCP Server - List & Tag Tools
 *
 * Tools for managing contact lists and tags
 */
import { z } from 'zod';
export function registerListTools(server, api) {
    // List Contact Lists
    server.tool('list_contact_lists', `Get all contact lists with subscriber counts and default mailbox info.

Each list may have a default_mailbox configured. When creating campaigns for a list:
- If list has default_mailbox: use that mailbox_id
- If no list default: use global default from list_mailboxes (is_default: true)`, {
        page: z.number().optional().describe('Page number (default: 1)'),
        per_page: z.number().min(1).max(100).optional().describe('Results per page (1-100, default: 50)'),
    }, async ({ page, per_page }) => {
        try {
            const result = await api.listContactLists({
                page: page ?? 1,
                per_page: per_page ?? 50,
            });
            const lists = result.data.map(l => ({
                id: l.id,
                name: l.name,
                description: l.description,
                subscribers_count: l.subscribers_count,
                double_opt_in: l.double_opt_in,
                default_mailbox: l.default_mailbox ? {
                    id: l.default_mailbox.id,
                    name: l.default_mailbox.name,
                    from_email: l.default_mailbox.from_email,
                    from_name: l.default_mailbox.from_name,
                } : null,
                created_at: l.created_at,
            }));
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            lists,
                            pagination: {
                                page: result.meta.current_page,
                                total_pages: result.meta.last_page,
                                total: result.meta.total,
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
    // Get Contact List Details
    server.tool('get_contact_list', `Get detailed information about a specific contact list, including its default mailbox.

Use default_mailbox info when creating campaigns to automatically select the right sender.`, {
        id: z.number().describe('Contact list ID'),
    }, async ({ id }) => {
        try {
            const list = await api.getContactList(id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            id: list.id,
                            name: list.name,
                            description: list.description,
                            subscribers_count: list.subscribers_count,
                            double_opt_in: list.double_opt_in,
                            default_mailbox: list.default_mailbox ? {
                                id: list.default_mailbox.id,
                                name: list.default_mailbox.name,
                                from_email: list.default_mailbox.from_email,
                                from_name: list.default_mailbox.from_name,
                            } : null,
                            created_at: list.created_at,
                            updated_at: list.updated_at,
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
    // Get List Subscribers
    server.tool('get_list_subscribers', 'Get subscribers belonging to a specific contact list.', {
        list_id: z.number().describe('Contact list ID'),
        page: z.number().optional().describe('Page number (default: 1)'),
        per_page: z.number().min(1).max(100).optional().describe('Results per page (1-100, default: 20)'),
    }, async ({ list_id, page, per_page }) => {
        try {
            const result = await api.getListSubscribers(list_id, {
                page: page ?? 1,
                per_page: per_page ?? 20,
            });
            const subscribers = result.data.map(s => ({
                id: s.id,
                email: s.email,
                name: [s.first_name, s.last_name].filter(Boolean).join(' ') || null,
                status: s.status,
                created_at: s.created_at,
            }));
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            list_id,
                            subscribers,
                            pagination: {
                                page: result.meta.current_page,
                                total_pages: result.meta.last_page,
                                total: result.meta.total,
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
    // List Tags
    server.tool('list_tags', 'Get all available tags. Use this to see what tags can be assigned to subscribers.', {}, async () => {
        try {
            const tags = await api.listTags();
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            tags: tags.map(t => ({
                                id: t.id,
                                name: t.name,
                                color: t.color,
                                subscribers_count: t.subscribers_count ?? null,
                            })),
                            total: tags.length,
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
    // List Custom Fields
    server.tool('list_custom_fields', 'Get all available custom fields. Custom fields allow storing additional subscriber information.', {}, async () => {
        try {
            const fields = await api.listCustomFields();
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            custom_fields: fields.map(f => ({
                                id: f.id,
                                name: f.name,
                                slug: f.slug,
                                type: f.type,
                                required: f.required,
                                options: f.options,
                            })),
                            total: fields.length,
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
//# sourceMappingURL=lists.js.map
/**
 * NetSendo MCP Server - Subscriber Tools
 *
 * Tools for managing subscribers
 */
import { z } from 'zod';
export function registerSubscriberTools(server, api) {
    // List Subscribers
    server.tool('list_subscribers', 'List subscribers with optional filtering. Returns paginated results with subscriber details including email, name, status, and tags.', {
        page: z.number().optional().describe('Page number (default: 1)'),
        per_page: z.number().min(1).max(100).optional().describe('Results per page (1-100, default: 20)'),
        search: z.string().optional().describe('Search by email or name'),
        list_id: z.number().optional().describe('Filter by contact list ID'),
        status: z.enum(['subscribed', 'unsubscribed', 'bounced', 'complained']).optional().describe('Filter by status'),
    }, async ({ page, per_page, search, list_id, status }) => {
        try {
            const result = await api.listSubscribers({
                page: page ?? 1,
                per_page: per_page ?? 20,
                search,
                list_id,
                status,
            });
            const subscriberList = result.data.map(s => ({
                id: s.id,
                email: s.email,
                name: [s.first_name, s.last_name].filter(Boolean).join(' ') || null,
                status: s.status,
                lists: s.lists?.map(l => l.name) ?? [],
                tags: s.tags?.map(t => t.name) ?? [],
                created_at: s.created_at,
            }));
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            subscribers: subscriberList,
                            pagination: {
                                page: result.meta.current_page,
                                total_pages: result.meta.last_page,
                                total: result.meta.total,
                                per_page: result.meta.per_page,
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
    // Get Subscriber by ID
    server.tool('get_subscriber', 'Get detailed information about a specific subscriber by ID or email address.', {
        id: z.number().optional().describe('Subscriber ID'),
        email: z.string().email().optional().describe('Subscriber email address'),
    }, async ({ id, email }) => {
        try {
            if (!id && !email) {
                return {
                    content: [{ type: 'text', text: 'Error: Either id or email is required' }],
                    isError: true,
                };
            }
            const subscriber = id
                ? await api.getSubscriber(id)
                : await api.getSubscriberByEmail(email);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            id: subscriber.id,
                            email: subscriber.email,
                            first_name: subscriber.first_name,
                            last_name: subscriber.last_name,
                            phone: subscriber.phone,
                            status: subscriber.status,
                            source: subscriber.source,
                            lists: subscriber.lists?.map(l => ({ id: l.id, name: l.name })) ?? [],
                            tags: subscriber.tags?.map(t => ({ id: t.id, name: t.name })) ?? [],
                            custom_fields: subscriber.custom_fields ?? {},
                            created_at: subscriber.created_at,
                            updated_at: subscriber.updated_at,
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
    // Create Subscriber
    server.tool('create_subscriber', 'Create a new subscriber with email, name, and optional list/tag assignments.', {
        email: z.string().email().describe('Email address (required)'),
        first_name: z.string().optional().describe('First name'),
        last_name: z.string().optional().describe('Last name'),
        phone: z.string().optional().describe('Phone number'),
        lists: z.array(z.number()).optional().describe('Array of contact list IDs to subscribe to'),
        tags: z.array(z.union([z.number(), z.string()])).optional().describe('Array of tag IDs or names'),
        source: z.string().optional().describe('Subscription source (e.g., "mcp", "api")'),
        custom_fields: z.record(z.union([z.string(), z.number(), z.boolean()])).optional().describe('Custom field values'),
    }, async (params) => {
        try {
            const subscriber = await api.createSubscriber({
                email: params.email,
                first_name: params.first_name,
                last_name: params.last_name,
                phone: params.phone,
                lists: params.lists,
                tags: params.tags,
                source: params.source ?? 'mcp',
                custom_fields: params.custom_fields,
            });
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: `Subscriber created successfully`,
                            subscriber: {
                                id: subscriber.id,
                                email: subscriber.email,
                                status: subscriber.status,
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
    // Update Subscriber
    server.tool('update_subscriber', 'Update an existing subscriber\'s information.', {
        id: z.number().describe('Subscriber ID'),
        email: z.string().email().optional().describe('New email address'),
        first_name: z.string().optional().describe('First name'),
        last_name: z.string().optional().describe('Last name'),
        phone: z.string().optional().describe('Phone number'),
        status: z.enum(['subscribed', 'unsubscribed']).optional().describe('Subscription status'),
        custom_fields: z.record(z.union([z.string(), z.number(), z.boolean()])).optional().describe('Custom field values'),
    }, async ({ id, ...updates }) => {
        try {
            const subscriber = await api.updateSubscriber(id, updates);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: `Subscriber ${id} updated successfully`,
                            subscriber: {
                                id: subscriber.id,
                                email: subscriber.email,
                                status: subscriber.status,
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
    // Delete Subscriber
    server.tool('delete_subscriber', 'Permanently delete a subscriber from the system.', {
        id: z.number().describe('Subscriber ID to delete'),
        confirm: z.boolean().describe('Must be true to confirm deletion'),
    }, async ({ id, confirm }) => {
        try {
            if (!confirm) {
                return {
                    content: [{ type: 'text', text: 'Error: Must set confirm=true to delete subscriber' }],
                    isError: true,
                };
            }
            await api.deleteSubscriber(id);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: `Subscriber ${id} deleted successfully`,
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
    // Sync Subscriber Tags
    server.tool('sync_subscriber_tags', 'Update the tags assigned to a subscriber. This replaces all existing tags.', {
        subscriber_id: z.number().describe('Subscriber ID'),
        tag_ids: z.array(z.number()).describe('Array of tag IDs to assign'),
    }, async ({ subscriber_id, tag_ids }) => {
        try {
            const subscriber = await api.syncSubscriberTags(subscriber_id, tag_ids);
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            success: true,
                            message: `Tags synced for subscriber ${subscriber_id}`,
                            tags: subscriber.tags?.map(t => ({ id: t.id, name: t.name })) ?? [],
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
//# sourceMappingURL=subscribers.js.map
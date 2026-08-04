/**
 * NetSendo MCP Server - Tools Registry
 *
 * Registers all available tools with the MCP server
 */
import { registerSubscriberTools } from './subscribers.js';
import { registerListTools } from './lists.js';
import { registerListImportTools } from './list-import.js';
import { registerListExportTools } from './list-export.js';
import { registerListHygieneTools } from './list-hygiene.js';
import { registerListMemberTools } from './list-members.js';
import { registerListActivityTools } from './list-activity.js';
import { registerSuppressionTools } from './suppressions.js';
import { registerNotificationTools } from './notifications.js';
import { registerMessagingTools } from './messaging.js';
import { registerCampaignTools } from './campaigns.js';
import { registerAbTestTools } from './ab-tests.js';
import { registerFunnelTools } from './funnels.js';
import { registerPlaceholderTools } from './placeholders.js';
/**
 * Register all tools with the MCP server
 */
export function registerAllTools(server, api) {
    // Subscriber management tools
    registerSubscriberTools(server, api);
    // Contact lists, tags and list CRUD
    registerListTools(server, api);
    // List import (CSV / TSV / JSON / plain addresses)
    registerListImportTools(server, api);
    // List export (inline data and queued CSV)
    registerListExportTools(server, api);
    // List hygiene: analyse, clean, deduplicate, verify deliverability
    registerListHygieneTools(server, api);
    // Bulk membership operations
    registerListMemberTools(server, api);
    // Activity feed and engagement analytics
    registerListActivityTools(server, api);
    // Account-wide suppression list
    registerSuppressionTools(server, api);
    // Notifications to the account owner
    registerNotificationTools(server, api);
    // Email and SMS messaging tools (single sends)
    registerMessagingTools(server, api);
    // Campaign management tools (bulk campaigns)
    registerCampaignTools(server, api);
    // A/B testing tools
    registerAbTestTools(server, api);
    // Funnel/automation tools
    registerFunnelTools(server, api);
    // Placeholder/custom fields tools
    registerPlaceholderTools(server, api);
    // Account / Connection test tool
    server.tool('test_connection', 'Test the connection to NetSendo API. Use this to verify credentials are working.', {}, async () => {
        const result = await api.testConnection();
        return {
            content: [{
                    type: 'text',
                    text: JSON.stringify({
                        connected: result.success,
                        message: result.message,
                        version: result.version ?? null,
                    }, null, 2),
                }],
            isError: !result.success,
        };
    });
    // Get Account Info
    server.tool('get_account_info', 'Get information about the connected NetSendo account.', {}, async () => {
        try {
            const info = await api.getAccountInfo();
            return {
                content: [{
                        type: 'text',
                        text: JSON.stringify({
                            name: info.name,
                            email: info.email,
                            version: info.version,
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
export { registerSubscriberTools } from './subscribers.js';
export { registerListTools } from './lists.js';
export { registerListImportTools } from './list-import.js';
export { registerListExportTools } from './list-export.js';
export { registerListHygieneTools } from './list-hygiene.js';
export { registerListMemberTools } from './list-members.js';
export { registerListActivityTools } from './list-activity.js';
export { registerSuppressionTools } from './suppressions.js';
export { registerNotificationTools } from './notifications.js';
export { registerMessagingTools } from './messaging.js';
export { registerCampaignTools } from './campaigns.js';
export { registerAbTestTools } from './ab-tests.js';
export { registerFunnelTools } from './funnels.js';
export { registerPlaceholderTools } from './placeholders.js';
//# sourceMappingURL=index.js.map
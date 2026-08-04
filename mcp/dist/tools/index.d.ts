/**
 * NetSendo MCP Server - Tools Registry
 *
 * Registers all available tools with the MCP server
 */
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
/**
 * Register all tools with the MCP server
 */
export declare function registerAllTools(server: McpServer, api: NetSendoApiClient): void;
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
//# sourceMappingURL=index.d.ts.map
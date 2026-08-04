/**
 * NetSendo MCP Server - Notification Tools
 *
 * How an automated process reports back to the human who owns the account.
 * These notifications land in NetSendo's notification centre — they are never
 * sent to subscribers or anyone outside the account.
 */
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
export declare function registerNotificationTools(server: McpServer, api: NetSendoApiClient): void;
//# sourceMappingURL=notifications.d.ts.map
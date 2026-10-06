/**
 * NetSendo MCP Server - System Emails & System Pages Tools
 *
 * The automatic emails (double opt-in, welcome, unsubscribe confirmations,
 * owner notification) and the pages shown at the end of signup / activation /
 * unsubscribe / preference flows. Each slug has one instance-wide GLOBAL
 * default and optional per-list OVERRIDES (copy-on-write).
 */
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
export declare function registerSystemContentTools(server: McpServer, api: NetSendoApiClient): void;
//# sourceMappingURL=system-content.d.ts.map
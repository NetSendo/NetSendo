/**
 * NetSendo MCP Server - Suppression Tools
 *
 * The account-wide do-not-mail list. Suppression outranks every list: a
 * suppressed address is skipped on import and cannot be mailed again until it
 * is explicitly lifted.
 */
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
export declare function registerSuppressionTools(server: McpServer, api: NetSendoApiClient): void;
//# sourceMappingURL=suppressions.d.ts.map
/**
 * NetSendo MCP Server - Custom Field Tools
 *
 * Define the extra subscriber fields that personalise messages ([[name]])
 * and appear in forms. Listing is list_custom_fields (lists.ts) and
 * list_placeholders (placeholders.ts); values are set per subscriber through
 * create_subscriber / update_subscriber (custom_fields).
 */
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
export declare function registerCustomFieldTools(server: McpServer, api: NetSendoApiClient): void;
//# sourceMappingURL=custom-fields.d.ts.map
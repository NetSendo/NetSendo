/**
 * NetSendo MCP Server - Tag Tools
 *
 * Create, rename/recolour and delete tags. Listing lives in lists.ts
 * (list_tags); assigning tags to people is sync_subscriber_tags /
 * tag_list_members.
 */
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
export declare function registerTagTools(server: McpServer, api: NetSendoApiClient): void;
//# sourceMappingURL=tags.d.ts.map
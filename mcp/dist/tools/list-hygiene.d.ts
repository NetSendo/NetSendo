/**
 * NetSendo MCP Server - List Hygiene Tools
 *
 * Diagnose what is hurting a list's deliverability, then fix it. Every
 * mutating tool here defaults to a dry run, and the irreversible actions
 * additionally require confirm=true.
 */
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
export declare function registerListHygieneTools(server: McpServer, api: NetSendoApiClient): void;
//# sourceMappingURL=list-hygiene.d.ts.map
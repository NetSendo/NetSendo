/**
 * NetSendo MCP Server - Automation Rule Tools
 *
 * Automation rules: "when <trigger event> happens, if <conditions> pass, run
 * <actions>". They complement funnels (multi-step sequences): a rule reacts
 * to a single event, e.g. tag everyone who joins a list or email the owner
 * when a deal is won.
 */
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
export declare function registerAutomationTools(server: McpServer, api: NetSendoApiClient): void;
//# sourceMappingURL=automations.d.ts.map
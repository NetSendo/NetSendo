/**
 * NetSendo MCP Server - Webhook Tools
 *
 * Outgoing webhooks: NetSendo POSTs a signed JSON payload to a URL whenever
 * one of the subscribed events happens (subscriber changes, tags, email/SMS,
 * Stripe purchases).
 */
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
export declare function registerWebhookTools(server: McpServer, api: NetSendoApiClient): void;
//# sourceMappingURL=webhooks.d.ts.map
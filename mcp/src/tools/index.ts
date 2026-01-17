/**
 * NetSendo MCP Server - Tools Registry
 * 
 * Registers all available tools with the MCP server
 */

import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';

import { registerSubscriberTools } from './subscribers.js';
import { registerListTools } from './lists.js';
import { registerMessagingTools } from './messaging.js';

/**
 * Register all tools with the MCP server
 */
export function registerAllTools(server: McpServer, api: NetSendoApiClient) {
  // Subscriber management tools
  registerSubscriberTools(server, api);
  
  // Contact lists and tags tools
  registerListTools(server, api);
  
  // Email and SMS messaging tools
  registerMessagingTools(server, api);

  // Account / Connection test tool
  server.tool(
    'test_connection',
    'Test the connection to NetSendo API. Use this to verify credentials are working.',
    {},
    async () => {
      const result = await api.testConnection();
      
      return {
        content: [{
          type: 'text' as const,
          text: JSON.stringify({
            connected: result.success,
            message: result.message,
            version: result.version ?? null,
          }, null, 2),
        }],
        isError: !result.success,
      };
    }
  );

  // Get Account Info
  server.tool(
    'get_account_info',
    'Get information about the connected NetSendo account.',
    {},
    async () => {
      try {
        const info = await api.getAccountInfo();
        
        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              name: info.name,
              email: info.email,
              version: info.version,
            }, null, 2),
          }],
        };
      } catch (error) {
        return {
          content: [{ type: 'text' as const, text: `Error: ${(error as Error).message}` }],
          isError: true,
        };
      }
    }
  );
}

export { registerSubscriberTools } from './subscribers.js';
export { registerListTools } from './lists.js';
export { registerMessagingTools } from './messaging.js';

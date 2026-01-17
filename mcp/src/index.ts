#!/usr/bin/env node
/**
 * NetSendo MCP Server
 * 
 * Model Context Protocol server for NetSendo email marketing platform.
 * Enables AI assistants (Claude, Cursor, VS Code) to interact with NetSendo.
 * 
 * Usage:
 *   # With environment variables (Docker/local):
 *   NETSENDO_API_URL=http://localhost:8080 NETSENDO_API_KEY=xxx node dist/index.js
 * 
 *   # With CLI arguments (remote):
 *   npx @netsendo/mcp-client --url https://app.example.com --api-key xxx
 * 
 * @author NetSendo Team
 * @license MIT
 */

import { program } from 'commander';
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';

import { loadConfig, validateConfig, CliArgs } from './config.js';
import { NetSendoApiClient } from './api-client.js';
import { registerAllTools } from './tools/index.js';

const SERVER_NAME = 'netsendo-mcp';
const SERVER_VERSION = '1.0.0';

// Parse CLI arguments
program
  .name('netsendo-mcp')
  .description('NetSendo MCP Server - Connect AI assistants to your NetSendo instance')
  .version(SERVER_VERSION)
  .option('--url <url>', 'NetSendo API URL (e.g., https://app.netsendo.com)')
  .option('--api-key <key>', 'NetSendo API key')
  .option('--debug', 'Enable debug logging')
  .parse(process.argv);

const cliOpts = program.opts();
const cliArgs: CliArgs = {
  url: cliOpts.url,
  apiKey: cliOpts.apiKey,
  debug: cliOpts.debug,
};

async function main() {
  // Validate configuration before starting
  const validation = validateConfig(cliArgs);
  if (!validation.valid) {
    console.error('Configuration Error:');
    validation.errors.forEach(err => console.error(`  - ${err}`));
    console.error('\nUsage:');
    console.error('  npx @netsendo/mcp-client --url <url> --api-key <key>');
    console.error('\nExamples:');
    console.error('  # Remote NetSendo instance:');
    console.error('  npx @netsendo/mcp-client --url https://app.netsendo.com --api-key ns_live_xxx');
    console.error('\n  # Local development (Docker):');
    console.error('  docker compose run --rm mcp');
    process.exit(1);
  }

  // Load configuration (CLI args take priority over env vars)
  const config = loadConfig(cliArgs);

  // Create API client
  const api = new NetSendoApiClient(config);

  // Create MCP server
  const server = new McpServer({
    name: SERVER_NAME,
    version: SERVER_VERSION,
  });

  // Register all tools
  registerAllTools(server, api);

  // Set up resources (informational data for AI context)
  server.resource(
    'netsendo://info',
    'NetSendo instance information',
    async () => {
      try {
        const info = await api.getAccountInfo();
        return {
          contents: [{
            uri: 'netsendo://info',
            mimeType: 'application/json',
            text: JSON.stringify({
              name: 'NetSendo Instance',
              description: 'Email marketing and automation platform',
              version: info.version,
              api_url: config.apiUrl,
              capabilities: [
                'Subscriber management',
                'Contact lists',
                'Tags',
                'Email campaigns',
                'SMS messaging',
                'CRM contacts',
                'Automations',
              ],
            }, null, 2),
          }],
        };
      } catch (error) {
        return {
          contents: [{
            uri: 'netsendo://info',
            mimeType: 'text/plain',
            text: `Error fetching NetSendo info: ${(error as Error).message}`,
          }],
        };
      }
    }
  );

  // Quick stats resource
  server.resource(
    'netsendo://stats',
    'Quick statistics overview',
    async () => {
      try {
        // Fetch basic stats from multiple endpoints
        const [lists, tags] = await Promise.all([
          api.listContactLists({ per_page: 100 }),
          api.listTags(),
        ]);

        const totalSubscribers = lists.data.reduce(
          (sum, list) => sum + list.subscribers_count, 
          0
        );

        return {
          contents: [{
            uri: 'netsendo://stats',
            mimeType: 'application/json',
            text: JSON.stringify({
              contact_lists: lists.meta.total,
              total_subscribers: totalSubscribers,
              tags: tags.length,
              top_lists: lists.data
                .sort((a, b) => b.subscribers_count - a.subscribers_count)
                .slice(0, 5)
                .map(l => ({ name: l.name, subscribers: l.subscribers_count })),
            }, null, 2),
          }],
        };
      } catch (error) {
        return {
          contents: [{
            uri: 'netsendo://stats',
            mimeType: 'text/plain',
            text: `Error fetching stats: ${(error as Error).message}`,
          }],
        };
      }
    }
  );

  // Add prompts for common tasks
  server.prompt(
    'analyze_subscribers',
    'Analyze subscriber list for quality and engagement patterns',
    {
      list_id: z.string().optional().describe('Optional: specific list ID to analyze'),
    },
    async ({ list_id }) => ({
      messages: [{
        role: 'user',
        content: {
          type: 'text',
          text: `Please analyze the subscriber ${list_id ? `list (ID: ${list_id})` : 'database'} in NetSendo.

Use the available tools to:
1. First, use list_contact_lists to get an overview of all lists
2. Use list_subscribers to sample subscriber data
3. Check for patterns in subscription sources, status distribution, and engagement

Provide insights on:
- Total subscriber count and status breakdown
- Quality indicators (bounced rate, unsubscribe rate)
- Recommendations for list hygiene
- Suggested segmentation strategies`,
        },
      }],
    })
  );

  server.prompt(
    'send_newsletter',
    'Help compose and send a newsletter to subscribers',
    {
      topic: z.string().describe('Newsletter topic or theme'),
      list_id: z.string().optional().describe('Target contact list ID'),
    },
    async ({ topic, list_id }) => ({
      messages: [{
        role: 'user',
        content: {
          type: 'text',
          text: `Help me create and send a newsletter about: ${topic}

${list_id ? `Target list ID: ${list_id}` : 'First, help me select the right contact list.'}

Steps:
1. Use list_mailboxes to see available sender addresses
2. Use list_contact_lists to confirm the target audience
3. Help me write compelling subject line and content
4. Use send_email (for individual) or prepare batch send instructions

Requirements:
- Engaging subject line
- Clear call-to-action
- Mobile-friendly HTML content
- Professional but friendly tone`,
        },
      }],
    })
  );

  server.prompt(
    'cleanup_list',
    'Identify and clean up inactive or problematic subscribers',
    {},
    async () => ({
      messages: [{
        role: 'user',
        content: {
          type: 'text',
          text: `Help me clean up my subscriber lists in NetSendo.

Use the available tools to:
1. List all contact lists and their subscriber counts
2. Identify subscribers with status 'bounced' or 'complained'
3. Find subscribers who haven't engaged (if engagement data available)

Then provide recommendations for:
- Subscribers to remove or unsubscribe
- Lists that might need attention
- Best practices for maintaining list hygiene

Important: Only suggest deletions, don't execute them without my confirmation.`,
        },
      }],
    })
  );

  // Connect with stdio transport
  const transport = new StdioServerTransport();
  await server.connect(transport);

  // Log startup (to stderr so it doesn't interfere with MCP protocol)
  console.error(`${SERVER_NAME} v${SERVER_VERSION} started`);
  console.error(`Connected to: ${config.apiUrl}`);
}

// Run the server
main().catch((error) => {
  console.error('Fatal error:', error);
  process.exit(1);
});

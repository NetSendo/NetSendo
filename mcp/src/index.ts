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
const SERVER_VERSION = '1.4.1';

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
                'Contact lists (create, update, delete, stats)',
                'List import (CSV, TSV, JSON, plain addresses) with dry-run preview',
                'List export (inline JSON/CSV/NDJSON, cursor-paged) and queued CSV',
                'List hygiene (health report, cleaning, deduplication, deliverability verification)',
                'Bulk membership operations (add, remove, status, move, copy, tag)',
                'Activity feed and engagement analytics',
                'Suppression list (account-wide do-not-mail)',
                'Notifications to the account owner',
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

  // Contact list directory — lets an assistant pick the right list without a
  // tool round-trip, and see at a glance which ones look neglected.
  server.resource(
    'netsendo://lists',
    'Contact lists with size, channel and growth indicators',
    async () => {
      try {
        const lists = await api.listContactLists({ per_page: 100 });

        return {
          contents: [{
            uri: 'netsendo://lists',
            mimeType: 'application/json',
            text: JSON.stringify({
              total: lists.meta.total,
              lists: lists.data.map(l => ({
                id: l.id,
                name: l.name,
                description: l.description,
                subscribers: l.subscribers_count,
                double_opt_in: l.double_opt_in,
                default_mailbox: l.default_mailbox?.from_email ?? null,
                created_at: l.created_at,
              })),
              hint: 'Use get_list_stats for a single list, analyze_list_health for data-quality problems, get_list_engagement for performance.',
            }, null, 2),
          }],
        };
      } catch (error) {
        return {
          contents: [{
            uri: 'netsendo://lists',
            mimeType: 'text/plain',
            text: `Error fetching contact lists: ${(error as Error).message}`,
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
    'Audit a list for deliverability problems and propose a safe clean-up plan',
    {
      list_id: z.string().optional().describe('Optional: specific list ID to clean up'),
    },
    async ({ list_id }) => ({
      messages: [{
        role: 'user',
        content: {
          type: 'text',
          text: `Audit and clean up ${list_id ? `contact list ID ${list_id}` : 'my contact lists'} in NetSendo.

Steps:
1. ${list_id ? `Run analyze_list_health on list ${list_id}` : 'Use list_contact_lists, then analyze_list_health on the largest lists'} to get the health score, per-category counts and recommendations.
2. Run verify_list_emails to see how much of the audience is actually deliverable.
3. Run dedupe_list with dry_run=true to see whether duplicates exist.
4. For every category worth acting on, run clean_list with dry_run=true so we can see exactly who would be affected.

Then present:
- The health score and the biggest problems, with numbers
- A concrete plan: which category, which action, how many contacts
- What I would lose by acting, and what it costs me to do nothing

Rules:
- Never execute a clean-up with dry_run=false until I approve the plan.
- Never use action "delete" or "suppress" unless I ask for it explicitly — prefer "unsubscribe" or "remove".
- For never_engaged and dormant contacts, propose tagging them for a win-back campaign before any removal.`,
        },
      }],
    })
  );

  server.prompt(
    'import_contacts',
    'Import contacts onto a list safely, with a preview before anything is written',
    {
      list_id: z.string().optional().describe('Target contact list ID'),
    },
    async ({ list_id }) => ({
      messages: [{
        role: 'user',
        content: {
          type: 'text',
          text: `Help me import contacts ${list_id ? `into contact list ID ${list_id}` : 'into one of my NetSendo lists'}.

Steps:
1. ${list_id ? '' : 'Use list_contact_lists to confirm the target list and its channel (email or sms).\n2. '}Take the data I provide in whatever shape it arrives — pasted CSV, a spreadsheet export, JSON records or a bare list of addresses.
${list_id ? '2' : '3'}. Run preview_list_import FIRST. Show me:
   - the detected column mapping (so I can catch a wrong column before it lands in the database)
   - how many contacts would be created, updated, reactivated, skipped and rejected
   - the problem rows: invalid syntax, typo domains with suggested corrections, disposable and role addresses, duplicates
${list_id ? '3' : '4'}. Recommend the import options based on what you found — for example fix_typos when there are misspelled provider domains, or skip_role when the file is full of shared inboxes.
${list_id ? '4' : '5'}. Only after I approve, run import_subscribers and report the final counts.

Also tell me whether the import should trigger the list's welcome sequence (trigger_automations). Default is yes — but for migrating an existing audience it usually should be no.`,
        },
      }],
    })
  );

  server.prompt(
    'list_report',
    'Report on how a list is performing and what to do next',
    {
      list_id: z.string().describe('Contact list ID to report on'),
      days: z.string().optional().describe('Reporting window in days (default: 30)'),
    },
    async ({ list_id, days }) => ({
      messages: [{
        role: 'user',
        content: {
          type: 'text',
          text: `Give me a performance report for NetSendo contact list ID ${list_id} over the last ${days ?? 30} days.

Gather:
1. get_list_stats — audience composition and configuration
2. get_list_engagement with days=${days ?? 30} — growth, churn, open/click rates, best messages and links, most engaged contacts
3. get_list_activity — anything unusual in the event stream (spikes in unsubscribes or bounces, failed sends)
4. analyze_list_health — data-quality problems dragging the numbers down

Then write a short report:
- How the list is doing, in plain language, with the numbers that matter
- What changed and what likely caused it
- The three most valuable things to do next, in priority order

Be direct about bad news: if the list is shrinking, the engagement is collapsing or the bounce rate threatens the sending reputation, say so plainly.`,
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

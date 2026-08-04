/**
 * NetSendo MCP Server - List Export Tools
 *
 * Pull list members out as data the assistant can work with — filtered,
 * field-selected and cursor-paged.
 */

import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
import type { ListExportOptions } from '../types.js';
import { ok, fail, compact } from './helpers.js';

export function registerListExportTools(server: McpServer, api: NetSendoApiClient) {

  server.tool(
    'export_list',
    `Export a list's members. Returns the rows inline, so the data can be analysed, transformed or handed to the user directly.

Filtering: status, tags, signup date range, and engagement (engaged=false isolates contacts who never opened or clicked).
Formats: "json" (records array — best for further processing), "csv" or "ndjson" (returned as a single string in "data").

Paging: at most 5000 rows per call. When has_more is true, call again with cursor=next_cursor to continue. Do not raise the limit to avoid paging — page instead.

Use get_export_fields first if unsure which columns, including custom fields, are available.`,
    {
      list_id: z.number().describe('Contact list ID'),
      format: z.enum(['json', 'csv', 'ndjson']).optional().describe('Output format (default: json)'),
      fields: z.array(z.string()).optional().describe('Columns to include, e.g. ["email","first_name","tags","custom_fields"] (default: id, email, phone, first_name, last_name, status, subscribed_at)'),
      status: z.enum(['active', 'inactive', 'unsubscribed', 'bounced', 'all']).optional().describe('Membership status filter (default: active)'),
      tag_ids: z.array(z.number()).optional().describe('Only contacts carrying at least one of these tag IDs'),
      subscribed_after: z.string().optional().describe('ISO date — only members who joined on/after this date'),
      subscribed_before: z.string().optional().describe('ISO date — only members who joined on/before this date'),
      engaged: z.boolean().optional().describe('true = only contacts with an open or click; false = only contacts with neither'),
      limit: z.number().min(1).max(5000).optional().describe('Rows per call (default: 500, max: 5000)'),
      cursor: z.number().optional().describe('Pass next_cursor from the previous call to fetch the next page'),
      delimiter: z.string().optional().describe('CSV delimiter (default: ",")'),
    },
    async ({ list_id, ...options }) => {
      try {
        return ok(await api.exportList(list_id, compact(options) as ListExportOptions));
      } catch (error) {
        return fail(error);
      }
    }
  );

  server.tool(
    'get_export_fields',
    'List the columns export_list can return for a list: standard subscriber fields plus the account\'s custom fields with their names and types.',
    {
      list_id: z.number().describe('Contact list ID'),
    },
    async ({ list_id }) => {
      try {
        return ok(await api.getExportFields(list_id));
      } catch (error) {
        return fail(error);
      }
    }
  );

  server.tool(
    'queue_list_export',
    `Queue the full CSV export of a list. The file is generated in the background and NetSendo sends the account owner a download link.

Use this for a complete archive the user wants as a file. For data the assistant needs to reason about, use export_list instead — it returns rows immediately.`,
    {
      list_id: z.number().describe('Contact list ID'),
    },
    async ({ list_id }) => {
      try {
        return ok(await api.queueListExport(list_id));
      } catch (error) {
        return fail(error);
      }
    }
  );
}

/**
 * NetSendo MCP Server - Suppression Tools
 *
 * The account-wide do-not-mail list. Suppression outranks every list: a
 * suppressed address is skipped on import and cannot be mailed again until it
 * is explicitly lifted.
 */

import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
import { ok, fail, compact } from './helpers.js';

export function registerSuppressionTools(server: McpServer, api: NetSendoApiClient) {

  server.tool(
    'list_suppressions',
    'Show the account-wide suppression list — addresses that must never be mailed again, with the reason and the date they were suppressed.',
    {
      search: z.string().optional().describe('Filter by a fragment of the address'),
      reason: z.string().optional().describe('Filter by reason, e.g. "gdpr_erasure"'),
      per_page: z.number().min(1).max(200).optional().describe('Results per page (default: 50)'),
    },
    async (params) => {
      try {
        return ok(await api.listSuppressions(compact(params)));
      } catch (error) {
        return fail(error);
      }
    }
  );

  server.tool(
    'suppress_emails',
    `Add addresses to the account-wide do-not-mail list and, by default, unsubscribe them from every list they are on.

Use this for people who asked to be forgotten, complained, or must not be contacted again for legal reasons. Suppression is the strongest guarantee in the system: these addresses are also skipped by future imports.

Only suppress addresses when the user has asked for it or the person clearly requested it — it is a deliberate, account-wide block, not a clean-up shortcut.`,
    {
      emails: z.array(z.string().email()).min(1).max(1000).describe('Addresses to suppress'),
      reason: z.string().optional().describe('Why, e.g. "gdpr_erasure", "complaint", "user_request" (default: api)'),
      unsubscribe_existing: z.boolean().optional().describe('Also unsubscribe them from every list (default: true)'),
    },
    async (payload) => {
      try {
        const result = await api.addSuppressions(payload);
        return ok({ ...result.data, message: result.message });
      } catch (error) {
        return fail(error);
      }
    }
  );

  server.tool(
    'unsuppress_emails',
    `Lift suppression for addresses. This does NOT resubscribe anyone — it only removes the block, so consent has to be collected again through a normal signup.

Only do this when the person has renewed their consent.`,
    {
      emails: z.array(z.string().email()).min(1).max(1000).describe('Addresses to remove from suppression'),
    },
    async ({ emails }) => {
      try {
        const result = await api.removeSuppressions(emails);
        return ok({ ...result.data, message: result.message });
      } catch (error) {
        return fail(error);
      }
    }
  );
}

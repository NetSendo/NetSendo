/**
 * NetSendo MCP Server - Funnel Tools
 * 
 * Tools for managing automation funnels (sequences)
 */

import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';

export function registerFunnelTools(server: McpServer, api: NetSendoApiClient) {

  // List Funnels
  server.tool(
    'list_funnels',
    'List all automation funnels with optional filtering.',
    {
      status: z.string().optional().describe('Filter by status (draft, active, paused)'),
      trigger_type: z.string().optional().describe('Filter by trigger type (list_signup, tag_added, form_submit, manual)'),
      search: z.string().optional().describe('Search funnels by name'),
      page: z.number().optional().describe('Page number'),
      per_page: z.number().optional().describe('Items per page'),
    },
    async ({ status, trigger_type, search, page, per_page }) => {
      try {
        const funnels = await api.listFunnels({ status, trigger_type, search, page, per_page });

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              funnels: funnels.data.map(f => ({
                id: f.id,
                name: f.name,
                status: f.status,
                trigger_type: f.trigger_type,
                trigger_list: f.trigger_list?.name ?? null,
                subscribers_count: f.subscribers_count,
                completed_count: f.completed_count,
                steps_count: f.steps?.length ?? 0,
              })),
              pagination: funnels.meta,
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

  // Get Funnel Details
  server.tool(
    'get_funnel',
    'Get detailed information about a funnel including all steps and statistics.',
    {
      funnel_id: z.number().describe('Funnel ID'),
    },
    async ({ funnel_id }) => {
      try {
        const funnel = await api.getFunnel(funnel_id);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              id: funnel.id,
              name: funnel.name,
              slug: funnel.slug,
              status: funnel.status,
              trigger: {
                type: funnel.trigger_type,
                list: funnel.trigger_list?.name ?? null,
                tag: funnel.trigger_tag,
              },
              stats: funnel.stats,
              steps: funnel.steps?.map(s => ({
                id: s.id,
                type: s.type,
                name: s.name,
                order: s.order,
                delay_value: s.delay_value,
                delay_unit: s.delay_unit,
              })),
              created_at: funnel.created_at,
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

  // Create Funnel
  server.tool(
    'create_funnel',
    'Create a new automation funnel. Set a trigger and add steps after creation.',
    {
      name: z.string().describe('Funnel name'),
      trigger_type: z.enum(['list_signup', 'tag_added', 'form_submit', 'manual']).describe('What triggers the funnel'),
      trigger_list_id: z.number().optional().describe('For list_signup: the contact list ID'),
      trigger_tag: z.string().optional().describe('For tag_added: the tag name'),
      trigger_form_id: z.number().optional().describe('For form_submit: the form ID'),
    },
    async ({ name, trigger_type, trigger_list_id, trigger_tag, trigger_form_id }) => {
      try {
        const funnel = await api.createFunnel({
          name,
          trigger_type,
          trigger_list_id,
          trigger_tag,
          trigger_form_id,
        });

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: 'Funnel created. Add steps using add_funnel_step.',
              funnel: {
                id: funnel.id,
                name: funnel.name,
                slug: funnel.slug,
                status: funnel.status,
              },
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

  // Add Funnel Step
  server.tool(
    'add_funnel_step',
    'Add a step to a funnel. Steps are executed in order.',
    {
      funnel_id: z.number().describe('Funnel ID'),
      type: z.enum(['email', 'sms', 'delay', 'condition', 'action', 'end']).describe('Step type'),
      name: z.string().describe('Step name for reference'),
      after_step_id: z.number().optional().describe('Insert after this step ID'),
      message_id: z.number().optional().describe('For email/sms: campaign ID to send'),
      delay_value: z.number().optional().describe('For delay: time value'),
      delay_unit: z.enum(['minutes', 'hours', 'days']).optional().describe('For delay: time unit'),
      condition_type: z.string().optional().describe('For condition: type of condition'),
    },
    async ({ funnel_id, type, name, after_step_id, message_id, delay_value, delay_unit, condition_type }) => {
      try {
        const step = await api.addFunnelStep(funnel_id, {
          type,
          name,
          after_step_id,
          message_id,
          delay_value,
          delay_unit,
          condition_type,
        });

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: 'Step added to funnel',
              step: {
                id: step.id,
                type: step.type,
                name: step.name,
                order: step.order,
              },
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

  // Activate Funnel
  server.tool(
    'activate_funnel',
    'Activate a funnel to start processing new subscribers.',
    {
      funnel_id: z.number().describe('Funnel ID'),
    },
    async ({ funnel_id }) => {
      try {
        const funnel = await api.activateFunnel(funnel_id);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: 'Funnel activated',
              funnel_id: funnel.id,
              status: funnel.status,
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

  // Pause Funnel
  server.tool(
    'pause_funnel',
    'Pause a funnel to stop processing new subscribers.',
    {
      funnel_id: z.number().describe('Funnel ID'),
    },
    async ({ funnel_id }) => {
      try {
        const funnel = await api.pauseFunnel(funnel_id);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: 'Funnel paused',
              funnel_id: funnel.id,
              status: funnel.status,
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

  // Get Funnel Stats
  server.tool(
    'get_funnel_stats',
    'Get statistics for a funnel including subscriber counts and completion rates.',
    {
      funnel_id: z.number().describe('Funnel ID'),
    },
    async ({ funnel_id }) => {
      try {
        const stats = await api.getFunnelStats(funnel_id);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify(stats, null, 2),
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

  // Delete Funnel
  server.tool(
    'delete_funnel',
    'Delete a funnel. Cannot delete active funnels - pause first.',
    {
      funnel_id: z.number().describe('Funnel ID to delete'),
    },
    async ({ funnel_id }) => {
      try {
        await api.deleteFunnel(funnel_id);

        return {
          content: [{
            type: 'text' as const,
            text: JSON.stringify({
              success: true,
              message: 'Funnel deleted',
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

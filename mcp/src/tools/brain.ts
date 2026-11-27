/**
 * NetSendo MCP Server - Brain conversation tools
 *
 * Let an agent hold conversations with NetSendo Brain (the account's AI
 * marketing assistant): list, read, start, rename and delete conversations,
 * send messages, and decide the plans Brain proposes in them.
 *
 * Reading needs the brain:read permission, everything else brain:write.
 */

import { z } from 'zod';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import type { NetSendoApiClient } from '../api-client.js';
import { compact, fail, ok } from './helpers.js';

interface ApiMessage {
  id: number;
  role: string;
  content: string;
  metadata?: Record<string, unknown> | null;
  plan?: unknown;
  created_at: string | null;
}

interface ApiConversation {
  id: number;
  title: string | null;
  channel: string;
  status: string;
  message_count: number;
  last_activity_at: string | null;
  created_at: string | null;
  messages?: ApiMessage[];
}

interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

/** A Brain reply waits for an AI model, sometimes with several agent steps. */
const CHAT_TIMEOUT_MS = 115_000;

const PREVIEW_LENGTH = 200;

function summary(conversation: ApiConversation) {
  const last = conversation.messages?.[0];

  return {
    id: conversation.id,
    title: conversation.title,
    channel: conversation.channel,
    status: conversation.status,
    message_count: conversation.message_count,
    last_activity_at: conversation.last_activity_at,
    created_at: conversation.created_at,
    last_message: last
      ? {
          role: last.role,
          preview: last.content.length > PREVIEW_LENGTH ? `${last.content.slice(0, PREVIEW_LENGTH)}…` : last.content,
        }
      : null,
  };
}

/** Send one message to Brain and shape its reply for the model. */
async function chat(
  api: NetSendoApiClient,
  data: { message: string; conversation_id?: number; force_new?: boolean },
) {
  const res = await api.request<Record<string, unknown>>('post', '/brain/chat', {
    data: compact(data),
    timeout: CHAT_TIMEOUT_MS,
  });
  const { message, response, conversation_id, title, type, ...rest } = res;

  return {
    conversation_id: conversation_id ?? null,
    title: title ?? null,
    type: type ?? 'message',
    reply: message ?? response ?? null,
    ...rest,
  };
}

const CHAT_NOTES = `Brain answers in the user's configured language and works in the account's Brain mode:
- type "approval_request": Brain proposes a plan (plan_id, plan.steps) and waits. Do NOT approve it yourself — show the plan to the user and call decide_brain_plan only with their explicit decision.
- type "info_request": Brain needs more details; answer in the same conversation.
- in autonomous mode Brain may carry out plans (create and send campaigns) right away.
A reply can take up to two minutes. If the call times out, Brain still finishes: read the reply later with get_brain_conversation.`;

export function registerBrainTools(server: McpServer, api: NetSendoApiClient) {
  server.tool(
    'list_brain_conversations',
    `List conversations with NetSendo Brain, the most recently active first (20 per page). Each item has the title, channel ("web" = panel, "api" = agents and MCP, "telegram"), message count and a preview of the last message. Conversations without any message are not listed. Requires brain:read.`,
    {
      page: z.number().int().min(1).optional().describe('Page number (default 1)'),
      channel: z.enum(['web', 'api', 'telegram']).optional().describe('Only conversations from this channel'),
      search: z.string().max(120).optional().describe('Only conversations whose title contains this text'),
    },
    async (input) => {
      try {
        const res = await api.request<Paginated<ApiConversation>>('get', '/brain/conversations', { params: compact(input) });
        return ok({
          conversations: res.data.map(summary),
          page: res.current_page,
          last_page: res.last_page,
          total: res.total,
        });
      } catch (error) {
        return fail(error);
      }
    }
  );

  server.tool(
    'get_brain_conversation',
    `Read one Brain conversation with its messages, oldest first. A message that carries a plan still waiting for a decision has a "plan" (id, title, steps) — decide it with decide_brain_plan once the user has. Requires brain:read.`,
    {
      conversation_id: z.number().int().describe('Conversation ID (from list_brain_conversations or send_brain_message)'),
      last: z.number().int().min(1).max(200).optional().describe('Return only the last N messages (default: all)'),
    },
    async ({ conversation_id, last }) => {
      try {
        const res = await api.request<ApiConversation>('get', `/brain/conversations/${conversation_id}`);
        const messages = (res.messages ?? []).map((message) => ({
          id: message.id,
          role: message.role,
          content: message.content,
          created_at: message.created_at,
          ...(message.plan ? { plan: message.plan } : {}),
        }));

        return ok({
          id: res.id,
          title: res.title,
          channel: res.channel,
          status: res.status,
          message_count: res.message_count,
          last_activity_at: res.last_activity_at,
          messages: last ? messages.slice(-last) : messages,
        });
      } catch (error) {
        return fail(error);
      }
    }
  );

  server.tool(
    'create_brain_conversation',
    `Start a new conversation with NetSendo Brain, optionally with a title and a first message. Without a message the conversation stays empty (and unlisted) until send_brain_message is called with its conversation_id. With a message, Brain's reply is returned too, and Brain may name the conversation itself when no title is given.

${CHAT_NOTES}

Requires brain:write.`,
    {
      title: z.string().min(1).max(120).optional().describe('Conversation title shown in the Brain panel'),
      message: z.string().min(1).max(5000).optional().describe('First message to Brain'),
    },
    async ({ title, message }) => {
      try {
        const conversation = await api.request<ApiConversation>('post', '/brain/conversations', { data: compact({ title }) });

        if (!message) {
          return ok({ success: true, conversation: summary(conversation) });
        }

        return ok({ success: true, ...(await chat(api, { message, conversation_id: conversation.id })) });
      } catch (error) {
        return fail(error);
      }
    }
  );

  server.tool(
    'send_brain_message',
    `Send a message to NetSendo Brain and get its reply. Pass conversation_id to continue a conversation (Brain sees its history); a deleted or unknown id answers 404. Without conversation_id the message continues the latest agent conversation if it was active in the last two hours, otherwise a new one starts; new_conversation=true always starts a new one. The reply carries the conversation_id to continue with.

${CHAT_NOTES}

Requires brain:write.`,
    {
      message: z.string().min(1).max(5000).describe('What to tell or ask Brain'),
      conversation_id: z.number().int().optional().describe('Conversation to continue'),
      new_conversation: z.boolean().optional().describe('Start a new conversation (ignored when conversation_id is given)'),
    },
    async ({ message, conversation_id, new_conversation }) => {
      try {
        return ok(await chat(api, { message, conversation_id, force_new: new_conversation }));
      } catch (error) {
        return fail(error);
      }
    }
  );

  server.tool(
    'update_brain_conversation',
    `Rename a Brain conversation. Requires brain:write.`,
    {
      conversation_id: z.number().int().describe('Conversation ID'),
      title: z.string().min(1).max(120).describe('New title'),
    },
    async ({ conversation_id, title }) => {
      try {
        const res = await api.request<ApiConversation>('put', `/brain/conversations/${conversation_id}`, { data: { title } });
        return ok({ success: true, conversation: summary(res) });
      } catch (error) {
        return fail(error);
      }
    }
  );

  server.tool(
    'delete_brain_conversation',
    `Delete a Brain conversation and all its messages. This cannot be undone — confirm with the user first. Plans and goals started in the conversation are kept (only their link to it is removed). Requires brain:write.`,
    {
      conversation_id: z.number().int().describe('Conversation ID'),
    },
    async ({ conversation_id }) => {
      try {
        const res = await api.request<Record<string, unknown>>('delete', `/brain/conversations/${conversation_id}`);
        return ok({ success: true, ...res });
      } catch (error) {
        return fail(error);
      }
    }
  );

  server.tool(
    'decide_brain_plan',
    `Approve or reject a plan Brain proposed (type "approval_request" from send_brain_message, or a "plan" on a message in get_brain_conversation). Approving lets Brain carry it out — that can create and SEND campaigns to real subscribers — so call this only with the user's explicit decision on that plan. An approved plan is queued; its result is posted into the conversation (read it with get_brain_conversation). A plan with no pending approval answers 404. Requires brain:write.`,
    {
      plan_id: z.number().int().describe('Plan ID (plan_id / plan.id)'),
      approved: z.boolean().describe('true = approve and run, false = reject'),
      reason: z.string().max(1000).optional().describe('Why it was rejected (kept with the decision)'),
    },
    async ({ plan_id, approved, reason }) => {
      try {
        const res = await api.request<Record<string, unknown>>('post', `/brain/plans/${plan_id}/approve`, {
          data: compact({ approved, reason }),
        });
        return ok({ success: true, plan_id, ...res });
      } catch (error) {
        return fail(error);
      }
    }
  );
}

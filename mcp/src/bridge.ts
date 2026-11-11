#!/usr/bin/env node
/**
 * NetSendo MCP - in-app tool bridge
 *
 * The NetSendo application serves a remote MCP endpoint (the "connector") and
 * runs the tools of this package through this file, so the tool list exists in
 * one place only. NetSendo starts `node bridge.js`, writes one command to stdin
 * and reads JSON lines from stdout:
 *
 *   <- {"op":"manifest"}                           list every tool
 *   <- {"op":"call","name":"…","arguments":{…}}    run one tool
 *
 *   -> {"type":"api","id":1,"method":"get","path":"/lists","params":{…},"data":{…}}
 *   <- {"type":"api_response","id":1,"status":200,"body":{…}}
 *   -> {"type":"result","result":{…}}              final answer, then exit
 *   -> {"type":"error","message":"…"}              the bridge itself failed
 *
 * API calls are not sent over the network: each one is handed back to the
 * application, which runs it as the signed-in connector user and enforces the
 * granted permissions. The bridge never holds a credential.
 */

import { createInterface } from 'node:readline';
import { AxiosError } from 'axios';
import type { AxiosAdapter, AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js';

import { NetSendoApiClient } from './api-client.js';
import { registerAllTools } from './tools/index.js';
import { toolAnnotations } from './tools/annotations.js';

type Command =
  | { op: 'manifest' }
  | { op: 'call'; name: string; arguments?: Record<string, unknown> };

type ApiResponseLine = { type: 'api_response'; id: number; status: number; body: unknown };

const CALL_TIMEOUT_MS = 120_000;

const pending = new Map<number, (line: ApiResponseLine) => void>();
let nextId = 1;
let commandReceived: (command: Command) => void;
const firstCommand = new Promise<Command>((resolve) => {
  commandReceived = resolve;
});
let gotCommand = false;

function write(line: unknown): void {
  process.stdout.write(JSON.stringify(line) + '\n');
}

const input = createInterface({ input: process.stdin, crlfDelay: Infinity });
input.on('line', (raw) => {
  if (raw.trim() === '') return;
  let line: unknown;
  try {
    line = JSON.parse(raw);
  } catch {
    return;
  }
  if (!gotCommand) {
    gotCommand = true;
    commandReceived(line as Command);
    return;
  }
  const reply = line as ApiResponseLine;
  if (reply?.type === 'api_response' && pending.has(reply.id)) {
    const resolve = pending.get(reply.id)!;
    pending.delete(reply.id);
    resolve(reply);
  }
});

/** Hand an API request to the application and wait for its answer. */
const hostAdapter: AxiosAdapter = (config: InternalAxiosRequestConfig) =>
  new Promise<AxiosResponse>((resolve, reject) => {
    const id = nextId++;
    let data: unknown = config.data;
    if (typeof data === 'string' && data !== '') {
      try {
        data = JSON.parse(data);
      } catch {
        // leave non-JSON bodies as they are
      }
    }

    pending.set(id, (reply) => {
      const response: AxiosResponse = {
        data: reply.body,
        status: reply.status,
        statusText: String(reply.status),
        headers: {},
        config,
        request: null,
      };
      const accept = config.validateStatus ?? ((status: number) => status >= 200 && status < 300);
      if (accept(reply.status)) {
        resolve(response);
      } else {
        reject(
          new AxiosError(
            `Request failed with status code ${reply.status}`,
            AxiosError.ERR_BAD_RESPONSE,
            config,
            null,
            response,
          ),
        );
      }
    });

    write({
      type: 'api',
      id,
      method: (config.method ?? 'get').toLowerCase(),
      path: config.url ?? '/',
      params: config.params ?? null,
      data: data ?? null,
    });
  });

async function main(): Promise<void> {
  const command = await firstCommand;

  const api = new NetSendoApiClient(
    { apiUrl: 'http://netsendo.invalid', apiKey: 'in-app', debug: false },
    { adapter: hostAdapter },
  );
  const server = new McpServer({ name: 'netsendo-mcp', version: 'bridge' });
  registerAllTools(server, api);

  const [clientSide, serverSide] = InMemoryTransport.createLinkedPair();
  const client = new Client({ name: 'netsendo-app', version: 'bridge' });
  await Promise.all([server.connect(serverSide), client.connect(clientSide)]);

  if (command.op === 'manifest') {
    const { tools } = await client.listTools();
    write({
      type: 'result',
      result: {
        tools: tools.map((tool) => ({
          name: tool.name,
          description: tool.description ?? '',
          inputSchema: tool.inputSchema,
          annotations: toolAnnotations(tool.name),
        })),
      },
    });
    return;
  }

  if (command.op === 'call') {
    try {
      const result = await client.callTool(
        { name: command.name, arguments: command.arguments ?? {} },
        undefined,
        { timeout: CALL_TIMEOUT_MS },
      );
      write({ type: 'result', result: { content: result.content, isError: result.isError === true } });
    } catch (error) {
      // Unknown tool or arguments that do not match the schema: report it as a
      // tool error the model can read and correct.
      write({
        type: 'result',
        result: { content: [{ type: 'text', text: `Error: ${(error as Error).message}` }], isError: true },
      });
    }
    return;
  }

  write({ type: 'error', message: 'Unknown command' });
}

main()
  .catch((error) => {
    write({ type: 'error', message: (error as Error).message });
    process.exitCode = 1;
  })
  .finally(() => {
    input.close();
    // Let stdout drain before leaving; the parent reads the last line.
    process.stdout.write('', () => process.exit());
  });

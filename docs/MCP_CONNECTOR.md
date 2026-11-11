# AI connector (remote MCP endpoint with sign-in)

Every NetSendo installation serves a remote [Model Context Protocol](https://modelcontextprotocol.io/) endpoint:

```
https://<your-netsendo-domain>/mcp
```

An AI app (Claude, Claude Code, Hermes Agent, ChatGPT, Cursor, VS Code, Gemini CLI, …) is given this address. It sends the user to NetSendo to sign in and choose permissions, and then works with the account through the same tools as the npm package `@netsendo/mcp-client`. No API key is created or pasted anywhere.

The panel page **Settings → AI connector** shows the address of the installation, the connected apps (with *Disconnect*) and step-by-step instructions for each app. This document is for the person who runs the server.

API keys, the REST API and the local MCP client (`npx @netsendo/mcp-client`, see [MCP_INTEGRATION.md](MCP_INTEGRATION.md)) work as before. The connector is an additional way in.

## What has to be reachable

Cloud apps (Claude on the web, desktop and phone, ChatGPT) connect **from their provider's servers**, not from the user's computer. For them the installation needs:

| Requirement | Why |
| --- | --- |
| A public host name with an IPv4 `A` record | Claude refuses addresses that resolve to private ranges |
| HTTPS with a valid certificate on port 443 | OAuth is not offered over plain http, except on `localhost` |
| The paths below answered by NetSendo, on the same host | The app discovers everything from the address |
| No redirect of `/mcp` to another host | A redirect drops the `Authorization` header |

Apps that run on the user's own machine (Claude Code, Hermes, Cursor, VS Code, Gemini CLI) connect from that machine: an internal address or `http://localhost:…` is enough for them.

### Paths

| Path | Method | Purpose |
| --- | --- | --- |
| `/mcp` | `POST` | The MCP endpoint (Streamable HTTP, JSON answers). `GET`/`DELETE` answer 405: there is no server-to-client stream and no session |
| `/.well-known/oauth-protected-resource/mcp` and `/.well-known/oauth-protected-resource` | `GET` | Which authorization server protects `/mcp` (RFC 9728) |
| `/.well-known/oauth-authorization-server` (also `…/mcp`, and `/.well-known/openid-configuration`) | `GET` | Where the OAuth endpoints are and what they support (RFC 8414) |
| `/oauth/register` | `POST` | Dynamic client registration (RFC 7591) |
| `/oauth/authorize` | `GET`, `POST` | The consent screen, for a signed-in panel user |
| `/oauth/token` | `POST` | Authorization code and refresh token exchange |
| `/oauth/revoke` | `POST` | Token revocation (RFC 7009) |

A quick check from outside:

```bash
curl -i -X POST https://<your-netsendo-domain>/mcp
```

The answer must be `401` with a header like

```
WWW-Authenticate: Bearer resource_metadata="https://<your-netsendo-domain>/.well-known/oauth-protected-resource/mcp"
```

and the address in that header must be the public one (`https://`, your domain). If it shows `http://` or an internal name, see *Reverse proxy* below.

## Reverse proxy

**The bundled nginx** (`docker/nginx/default.conf`) needs no change: every path that is not a file goes to the application, including `/.well-known/…`, and the `Authorization` header is passed on. An installation that still uses an older copy of that file does not need to update it for the connector.

**Your own proxy in front** (Caddy, Traefik, nginx, Cloudflare, a load balancer) must:

- pass `/mcp`, `/oauth/*` and `/.well-known/oauth-*` to NetSendo. Some setups answer `/.well-known/` themselves (ACME challenges, a static site): only `/.well-known/acme-challenge/` should be handled there;
- forward the `Authorization` request header and return the `WWW-Authenticate` response header unchanged;
- send `X-Forwarded-Proto` and `X-Forwarded-Host` (or `Host`) with the public values. NetSendo trusts these headers and builds the addresses in its discovery documents from them, so they have to describe what the outside world sees;
- not require its own login (basic auth, an access proxy, a VPN) on those paths: the AI app cannot pass it;
- not cache `POST /mcp` or the OAuth endpoints. The discovery documents may be cached for a few minutes.

Web application firewalls and bot protection sometimes challenge requests without a browser. Exempt the paths above; Anthropic publishes the address ranges Claude connects from in its [API documentation](https://platform.claude.com/docs/en/api/ip-addresses).

Tool calls are short requests (a tool may take up to 150 seconds in the worst case, usually well under one): the proxy's read timeout should not be lower than that of the panel.

## How it works

```
AI app ──POST /mcp (Bearer token)──► NetSendo (PHP)
                                       │  protocol: laravel/mcp
                                       │  starts one Node.js process per tool call
                                       ▼
                               mcp/dist/bridge.js   (tools of @netsendo/mcp-client)
                                       │  "I need GET /lists" (stdin/stdout, no network)
                                       ▼
                               NetSendo API v1, inside the same PHP process,
                               as the connected user with the granted permissions
```

- **One tool list.** The tools are those of the `mcp/` package. The application runs them through `mcp/dist/bridge.js`; it does not have its own copy. Adding a tool to the package adds it to the connector with the next image.
- **No credential leaves PHP.** The Node.js process gets no token, no API key and none of the application's environment. Each API request of a tool is handed back to the application and runs through the normal API v1 routes, with the same permission checks and the same API log as a request with an API key.
- **Node.js is part of the image** (`/opt/netsendo-mcp`). There is no extra container and nothing to change in `docker-compose.yml`. The `mcp` service in the compose file is the older stdio server and is not used by the connector.
- **Protocol versions.** The current MCP revision (2026-07-28, stateless) and the older handshake-based ones (2025-03-26 to 2025-11-25) are both served.

### Sign-in

The connector is its own OAuth 2.1 authorization server:

- authorization code with PKCE (`S256` is required); clients are public, there are no client secrets;
- an app identifies itself with a **Client ID Metadata Document** (an https address it publishes; this is Claude's "published identity") or registers itself with **dynamic client registration**. Metadata documents are downloaded by the server over https from public addresses only;
- redirect addresses must be https, or http on a loopback address (desktop and command-line apps), or one of the app URL schemes in `MCP_CONNECTOR_REDIRECT_SCHEMES`;
- access tokens live one hour; refresh tokens are single-use and replaced on every refresh. A refresh token that turns up again later ends the connection. An app that is not used for 90 days has to sign in again;
- only SHA-256 hashes of tokens are stored (tables `connector_clients`, `connector_connections`, `connector_tokens`, `connector_auth_codes`);
- `php artisan connector:prune` (scheduled daily) removes expired codes, tokens and connections. A self-registered client that was connected once is kept for 90 days after its last use, so that a host can reconnect with the `client_id` it remembers; one that never got as far as a connection is removed after a day.

### Limits on what needs no sign-in

Discovery, registration and the token endpoint are reachable by anyone, so they are kept cheap:

- the token endpoint never downloads anything: a client metadata document is fetched only when a signed-in user opens the consent screen, at most once a day per client, with a time limit on the DNS lookup and on the download, and a failed download is not repeated for five minutes;
- a registration stores at most 5 redirect addresses of up to 255 characters, and at most 500 self-registered clients may exist without a connected app. At that number the oldest unused ones are removed to make room; if all of them are younger than ten minutes, `/oauth/register` answers `503 temporarily_unavailable`;
- the discovery documents are sent with `Cache-Control: no-store`, because their addresses follow the host of the request;
- a broken authorization request of a self-registered client is shown as an error in NetSendo and not redirected to the client's address.

### Limits on a connected app

A tool call starts a Node.js process and holds a PHP worker until it ends. By default one app may send 120 requests a minute and run 2 tool calls at the same time, and all apps together 3. A call over the limit is not started: the tool answers "NetSendo is busy … call the tool again", which the assistant does by itself. The limits use cache locks and work with every cache store (file, database, Redis).

### Permissions

The permissions on the consent screen are the permission names of API keys (`lists:read`, `subscribers:write`, `messages:write`, …). A connected app acts as the user who approved it:

- it sees that user's data only; an app connected by a team member does not get the account owner's access;
- it can do only what was ticked; a tool that needs more answers with an error that names the missing permission;
- the user disconnects it in **Settings → AI connector**; the account owner also sees and can disconnect the apps of team members. Disconnecting takes effect on the next request.

A host that cannot sign in but can send a fixed header may call `/mcp` with a NetSendo API key instead: `Authorization: Bearer ns_live_…`. The key's own permissions apply.

## Settings

All settings are optional and go into `.env`. Defaults are in `src/config/connector.php`.

| Variable | Default | Meaning |
| --- | --- | --- |
| `MCP_CONNECTOR_ENABLED` | `true` | `false` turns the connector off |
| `MCP_ALLOWED_ORIGINS` | empty | Browser-based MCP apps on other sites that may call `/mcp` (comma-separated origins, or `*`). The installation's own address and `localhost` are always allowed; server-side apps such as Claude send no `Origin` and are not affected |
| `MCP_CONNECTOR_DYNAMIC_REGISTRATION` | `true` | `false` closes `/oauth/register`; only apps with a metadata document can connect |
| `MCP_CONNECTOR_METADATA_DOCUMENTS` | `true` | `false` stops the server from downloading client metadata documents; apps fall back to registration |
| `MCP_CONNECTOR_REDIRECT_SCHEMES` | `cursor,vscode,vscode-insiders,windsurf,zed,claude` | App URL schemes accepted as redirect targets |
| `MCP_CONNECTOR_ACCESS_TOKEN_TTL` | `3600` | Lifetime of an access token, seconds |
| `MCP_CONNECTOR_REFRESH_TOKEN_TTL` | `7776000` | How long an unused connection stays valid, seconds (90 days) |
| `MCP_CONNECTOR_RATE_LIMIT` | `120` | Requests per minute for one connected app |
| `MCP_CONNECTOR_CONCURRENT_CALLS_PER_APP` | `2` | Tool calls of one connected app that may run at the same time (`0` = no limit) |
| `MCP_CONNECTOR_CONCURRENT_CALLS` | `3` | Tool calls of all apps together that may run at the same time (`0` = no limit). Keep it below the number of PHP-FPM workers (5 in the stock image), so that the panel stays responsive |
| `MCP_CONNECTOR_MAX_UNCONNECTED_CLIENTS` | `500` | Self-registered clients that may exist without a connected app |
| `MCP_CONNECTOR_TOOL_TIMEOUT` | `150` | Seconds one tool call may take |
| `MCP_CONNECTOR_NODE` | `node` | The Node.js binary |
| `MCP_CONNECTOR_BRIDGE` | empty | Path of `bridge.js`, if it is not at `/opt/netsendo-mcp/dist/bridge.js` |

After changing `.env`, restart the `app` container (`docker compose up -d app`).

## Turning the connector off

```env
MCP_CONNECTOR_ENABLED=false
```

`/mcp`, `/oauth/*` and the discovery documents then answer 404, and the panel page says that the connector is turned off. Existing connections stop working; they are not deleted, so turning the connector on again restores them (until their tokens expire). API keys and the REST API are not affected.

To keep the connector but stop new apps from connecting, close the two registration paths instead (`MCP_CONNECTOR_DYNAMIC_REGISTRATION=false` and `MCP_CONNECTOR_METADATA_DOCUMENTS=false`).

To block it at the proxy as well, deny `/mcp`, `/oauth/` and `/.well-known/oauth-`.

## Troubleshooting

| What you see | Cause and fix |
| --- | --- |
| The app says it cannot reach the server, or "authorization failed" at once | The address is not public, not https, or resolves to a private IP. Run the `curl` check above from outside your network |
| The `WWW-Authenticate` header or the discovery documents show `http://` or an internal host | The proxy in front does not send `X-Forwarded-Proto` / the public `Host` |
| The app connects but shows no tools; the panel page says "The tools are not installed" | The image has no tool engine (a custom build without the `mcp/` step in `docker/php/Dockerfile`), or Node.js cannot be started. `docker compose exec app node /opt/netsendo-mcp/dist/bridge.js < /dev/null` must exit without an error; details are in `storage/logs/laravel.log` (lines starting with "Connector tool engine") |
| The consent screen says the request cannot be shown | The app sent a redirect address it did not register, or its registration was removed. Remove the connector in the app and add it again |
| A tool answers "Error 403 … permission" | The permission was not ticked on the consent screen. Disconnect the app and connect it again |
| `401` on every call after it worked | The app was disconnected in the panel, the connector was turned off, or the app was not used for 90 days. Sign in again from the app |
| `429` | More than `MCP_CONNECTOR_RATE_LIMIT` requests per minute from one app |
| Tools often answer "NetSendo is busy" | More tool calls at once than `MCP_CONNECTOR_CONCURRENT_CALLS_PER_APP` / `MCP_CONNECTOR_CONCURRENT_CALLS` allow. The assistant retries by itself; raise the limits only together with the number of PHP-FPM workers |
| `/oauth/register` answers `503 temporarily_unavailable` | A burst of registrations that never continued to a sign-in. It clears by itself within ten minutes; hosts with a published identity (Claude, ChatGPT, VS Code, Hermes) are not affected |
| The consent screen says "Check where it returns" | The app publishes its identity on one site and returns to another. Genuine hosts return to their own site or to the user's computer |
| A browser-based MCP tool gets `403 Forbidden: The request origin is not allowed` | Add its origin to `MCP_ALLOWED_ORIGINS` |

## For developers

- Routes: `src/routes/connector.php`. Protocol server: `src/app/Mcp/NetSendoServer.php`. OAuth: `src/app/Services/Connector/`, `src/app/Http/Controllers/Connector/`.
- Tool engine: `src/app/Services/Connector/NodeToolEngine.php` and `mcp/src/bridge.ts` (the protocol between them is described at the top of `bridge.ts`). In a repository checkout the engine is `mcp/dist/bridge.js`: run `npm ci && npm run build` in `mcp/` after changing a tool.
- Tool annotations (read-only, destructive) are derived from the tool name in `mcp/src/tools/annotations.ts`.
- Tests: `src/tests/Feature/Connector/`. `ConnectorNodeEngineTest` runs the real tools and is skipped when Node.js or the built package is missing.

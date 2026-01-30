# MCP Integration (Model Context Protocol)

This document describes the MCP integration in NetSendo, providing technical details for developers and AI agents building or maintaining this feature.

## Overview

The MCP (Model Context Protocol) integration enables AI assistants to interact with NetSendo:

- **Natural language control** of email marketing features
- **37+ tools** for subscribers, campaigns, A/B tests, funnels, messaging
- **Compatible with** Claude Desktop, Cursor IDE, VS Code
- **Two deployment modes**: Local (Docker) and Remote (npx)

> [Model Context Protocol](https://modelcontextprotocol.io/) is an open standard introduced by Anthropic that allows AI systems to integrate with external tools and data sources.

---

## Architecture

### Communication Flow

```
┌─────────────────┐     STDIO      ┌─────────────────┐     HTTP      ┌─────────────────┐
│  AI Assistant   │ ◄──────────►   │   MCP Server    │ ◄───────────► │    NetSendo     │
│ Claude/Cursor   │               │  TypeScript/Node │               │   REST API v1   │
└─────────────────┘               └─────────────────┘               └─────────────────┘
```

### Key Components

```
NetSendo/
├── mcp/                                    # MCP Server (TypeScript)
│   ├── src/
│   │   ├── index.ts                        # Server entry point
│   │   ├── api-client.ts                   # NetSendo API client
│   │   ├── config.ts                       # Configuration handling
│   │   ├── types.ts                        # TypeScript type definitions
│   │   └── tools/                          # Tool implementations
│   │       ├── index.ts                    # Tool registry
│   │       ├── subscribers.ts              # Subscriber management tools
│   │       ├── lists.ts                    # Contact lists & tags tools
│   │       ├── campaigns.ts                # Campaign management tools
│   │       ├── ab-tests.ts                 # A/B testing tools
│   │       ├── funnels.ts                  # Automation funnel tools
│   │       ├── messaging.ts                # Email & SMS tools
│   │       └── placeholders.ts             # Placeholder tools
│   ├── package.json                        # npm package definition
│   ├── Dockerfile                          # Docker image for local mode
│   └── README.md                           # MCP documentation
│
├── src/app/Console/Commands/
│   ├── GenerateMcpConfigCommand.php        # Config generator
│   └── TestMcpConnection.php               # Connection tester
│
├── src/app/Http/Controllers/
│   ├── McpStatusController.php             # Status API
│   └── Api/McpController.php               # Test endpoint
│
├── src/app/Models/
│   ├── McpStatus.php                       # Connection status model
│   └── ApiKey.php                          # API key with MCP flag
│
├── src/resources/js/
│   ├── Pages/Marketplace/MCP.vue           # MCP info/setup page
│   └── Components/McpStatusIndicator.vue   # Status widget
│
└── docs/mcp-server.md                      # Public documentation
```

---

## Deployment Modes

### Option A: Local Installation (Docker)

Best for self-hosted NetSendo running with Docker.

**Requirements:**

- NetSendo running locally with Docker Compose
- API key in `.env` file

**Setup:**

```bash
# 1. Add API key to .env
MCP_API_KEY=your-api-key-here

# 2. Build MCP container
docker compose build mcp

# 3. Get configuration
docker compose exec app php artisan mcp:config --type=local
```

**Configuration:**

```json
{
  "mcpServers": {
    "netsendo": {
      "command": "docker",
      "args": [
        "compose",
        "-f",
        "/path/to/NetSendo/docker-compose.yml",
        "run",
        "--rm",
        "-i",
        "mcp"
      ]
    }
  }
}
```

### Option B: Remote Installation (npx)

Best for hosted NetSendo (e.g., `https://app.mycompany.com`).

**Requirements:**

- Node.js 18+ installed locally
- NetSendo API key

**Configuration:**

```json
{
  "mcpServers": {
    "netsendo": {
      "command": "npx",
      "args": [
        "-y",
        "@netsendo/mcp-client",
        "--url",
        "https://your-netsendo-domain.com",
        "--api-key",
        "your-api-key-here"
      ]
    }
  }
}
```

---

## AI Tool Configuration Paths

| Tool                         | Configuration Location                                            |
| ---------------------------- | ----------------------------------------------------------------- |
| **Claude Desktop (macOS)**   | `~/Library/Application Support/Claude/claude_desktop_config.json` |
| **Claude Desktop (Windows)** | `%APPDATA%\Claude\claude_desktop_config.json`                     |
| **Cursor IDE**               | Settings → MCP → Add Server                                       |
| **VS Code**                  | `.vscode/mcp.json` in your project                                |

---

## Available Tools (37+)

### Subscriber Management

| Tool                   | Description                                    |
| ---------------------- | ---------------------------------------------- |
| `list_subscribers`     | List subscribers with filtering and pagination |
| `get_subscriber`       | Get subscriber by ID or email                  |
| `create_subscriber`    | Create a new subscriber                        |
| `update_subscriber`    | Update subscriber information                  |
| `delete_subscriber`    | Delete a subscriber                            |
| `sync_subscriber_tags` | Update subscriber tags                         |

### Contact Lists & Tags

| Tool                   | Description                  |
| ---------------------- | ---------------------------- |
| `list_contact_lists`   | Get all contact lists        |
| `get_contact_list`     | Get list details             |
| `get_list_subscribers` | Get subscribers in a list    |
| `list_tags`            | Get all available tags       |
| `list_custom_fields`   | Get custom field definitions |

### Campaigns

| Tool                      | Description                       |
| ------------------------- | --------------------------------- |
| `list_campaigns`          | List all campaigns with filtering |
| `get_campaign`            | Get campaign details              |
| `create_campaign`         | Create email/SMS campaign         |
| `update_campaign`         | Update campaign settings          |
| `set_campaign_lists`      | Set recipient lists               |
| `set_campaign_exclusions` | Set exclusion lists               |
| `schedule_campaign`       | Schedule for future sending       |
| `send_campaign`           | Send immediately                  |
| `get_campaign_stats`      | Get sending statistics            |
| `delete_campaign`         | Delete a campaign                 |

### A/B Testing

| Tool                  | Description                |
| --------------------- | -------------------------- |
| `list_ab_tests`       | List A/B tests             |
| `get_ab_test`         | Get test details           |
| `create_ab_test`      | Create new A/B test        |
| `add_ab_test_variant` | Add variant to test        |
| `start_ab_test`       | Start the test             |
| `end_ab_test`         | End test and select winner |
| `get_ab_test_results` | Get test results           |

### Funnels (Automation)

| Tool               | Description             |
| ------------------ | ----------------------- |
| `list_funnels`     | List automation funnels |
| `get_funnel`       | Get funnel details      |
| `create_funnel`    | Create new funnel       |
| `add_funnel_step`  | Add step to funnel      |
| `activate_funnel`  | Activate funnel         |
| `pause_funnel`     | Pause funnel            |
| `get_funnel_stats` | Get funnel statistics   |

### Messaging

| Tool                 | Description                   |
| -------------------- | ----------------------------- |
| `list_mailboxes`     | Get available email mailboxes |
| `send_email`         | Send an email to a subscriber |
| `get_email_status`   | Check email delivery status   |
| `list_sms_providers` | Get available SMS providers   |
| `send_sms`           | Send an SMS message           |
| `get_sms_status`     | Check SMS delivery status     |

### Account

| Tool                | Description                |
| ------------------- | -------------------------- |
| `test_connection`   | Test API connection        |
| `get_account_info`  | Get account information    |
| `list_placeholders` | Get available placeholders |

---

## Pre-built Prompts

MCP server includes pre-built prompts for common workflows:

| Prompt                | Description                        |
| --------------------- | ---------------------------------- |
| `analyze_subscribers` | Analyze subscriber list quality    |
| `send_newsletter`     | Help compose and send a newsletter |
| `cleanup_list`        | Identify problematic subscribers   |

---

## Resources

MCP provides read-only resources for AI context:

| Resource URI       | Description                                 |
| ------------------ | ------------------------------------------- |
| `netsendo://info`  | Instance information and capabilities       |
| `netsendo://stats` | Quick statistics (lists, subscribers, tags) |

---

## Artisan Commands

### `php artisan mcp:config`

Generates MCP configuration for AI tools.

```bash
# Auto-detect installation type
php artisan mcp:config

# Force local Docker mode
php artisan mcp:config --type=local

# Force remote mode
php artisan mcp:config --type=remote

# Custom server name
php artisan mcp:config --name=netsendo-prod
```

### `php artisan mcp:test`

Tests MCP connection to NetSendo API.

```bash
# Test connection
php artisan mcp:test

# Returns success/failure status with diagnostics
```

---

## API Endpoints

### Test Connection

```
GET /api/mcp/test
Authorization: Bearer ns_live_YOUR_API_KEY
```

**Success Response:**

```json
{
  "success": true,
  "message": "Connection successful",
  "data": {
    "account_name": "Your Name",
    "account_email": "your@email.com",
    "api_url": "https://your-domain.com",
    "version": "1.0.0",
    "mcp_enabled": true,
    "api_key_name": "MCP Key",
    "timestamp": "2026-01-17T12:00:00+00:00"
  }
}
```

### MCP Status

```
GET /mcp/status
```

Returns current MCP configuration status (configured/not configured).

---

## Placeholders

When creating email content via MCP, use these placeholders:

### Subscriber Data

- `[[first_name]]` - First name
- `[[last_name]]` - Last name
- `[[email]]` - Email address
- `[[phone]]` - Phone number
- `[[!fname]]` - First name in vocative case (Polish)

### System Links

- `[[unsubscribe_link]]` - **Required** in every email
- `[[manage]]` - Manage preferences link

### Gender Forms (Polish)

- `{{male|female}}` - Shows appropriate text based on gender
- Example: `{{Drogi|Droga}} [[first_name]]`

---

## Agent System Prompt

Provide this prompt to AI assistants for optimal NetSendo integration:

```
You are an AI assistant with access to NetSendo — an email/SMS marketing platform.

AVAILABLE TOOLS:
- list_subscribers, get_subscriber, create_subscriber, update_subscriber, delete_subscriber
- list_contact_lists, list_tags, sync_subscriber_tags
- list_campaigns, get_campaign, create_campaign, update_campaign, send_campaign
- set_campaign_lists, set_campaign_exclusions, schedule_campaign, get_campaign_stats
- list_ab_tests, create_ab_test, add_ab_variant, start_ab_test, end_ab_test
- list_funnels, get_funnel, create_funnel, add_funnel_step, activate_funnel
- send_email, send_sms (for single messages)
- list_placeholders, list_mailboxes, test_connection, get_account_info

⚠️ CRITICAL - create_campaign REQUIRED PARAMETERS:
1. channel: MUST be "email" OR "sms" (determines campaign type)
2. subject: Campaign title
3. type: "broadcast" (one-time) or "autoresponder" (automated)

EMAIL CAMPAIGN WORKFLOW:
1. create_campaign with channel:"email", type:"broadcast", subject:"..."
2. set_campaign_lists with contact_list_ids
3. send_campaign

RULES:
- Always use list_placeholders first to learn available placeholders
- Every email MUST contain [[unsubscribe_link]]
- Before sending a campaign you MUST assign lists (set_campaign_lists)
- For create_campaign ALWAYS provide channel: "email" or "sms"
```

---

## Security

- API keys are never logged or exposed
- All API calls respect NetSendo permissions
- Rate limiting: 60 requests/minute
- Sensitive data never returned
- MCP uses STDIO transport (no network exposure)

---

## Docker Configuration

The MCP server runs as a Docker service:

```yaml
# docker-compose.yml
mcp:
  build:
    context: ./mcp
    dockerfile: Dockerfile
  environment:
    - NETSENDO_API_URL=http://app:80
    - NETSENDO_API_KEY=${MCP_API_KEY:-}
  depends_on:
    - app
  # MCP uses stdio transport, not HTTP
  stdin_open: true
  tty: true
```

---

## Scheduled Tasks

```php
// Console Kernel
$schedule->command('mcp:test')->dailyAt('05:30');
```

Daily connection test at 5:30 AM to ensure MCP is operational.

---

## Troubleshooting

| Issue                         | Cause                   | Solution                                |
| ----------------------------- | ----------------------- | --------------------------------------- |
| "Connection failed"           | NetSendo not accessible | Check URL and network                   |
| "Invalid API key"             | Wrong or revoked key    | Generate new key in Settings → API Keys |
| "Tools not appearing"         | Config not loaded       | Restart AI tool after config change     |
| "npx command not found"       | Node.js not installed   | Install from nodejs.org                 |
| MCP status shows unconfigured | No MCP key in database  | Mark API key as MCP in settings         |

### Debug Mode

```bash
# Enable debug logging
npx @netsendo/mcp-client --url https://app.example.com --api-key xxx --debug
```

---

## Requirements

- Node.js 18+ (for remote mode)
- Docker (for local mode)
- Valid NetSendo API key with appropriate permissions

---

## Related Files

- [mcp/src/index.ts](file:///mcp/src/index.ts) - Server entry point
- [mcp/src/api-client.ts](file:///mcp/src/api-client.ts) - API client
- [mcp/src/tools/](file:///mcp/src/tools/) - Tool implementations
- [GenerateMcpConfigCommand.php](file:///src/app/Console/Commands/GenerateMcpConfigCommand.php)
- [TestMcpConnection.php](file:///src/app/Console/Commands/TestMcpConnection.php)
- [McpStatusController.php](file:///src/app/Http/Controllers/McpStatusController.php)
- [MCP.vue](file:///src/resources/js/Pages/Marketplace/MCP.vue) - Frontend page
- [mcp-server.md](file:///docs/mcp-server.md) - Public documentation

---

## See Also

- [Model Context Protocol Specification](https://modelcontextprotocol.io/)
- [mcp/README.md](../mcp/README.md) - Detailed MCP server documentation

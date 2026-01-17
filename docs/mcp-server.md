# NetSendo MCP Server

NetSendo includes a Model Context Protocol (MCP) server that enables AI assistants to interact with your email marketing platform.

## What is MCP?

[Model Context Protocol](https://modelcontextprotocol.io/) is an open standard introduced by Anthropic that allows AI systems (like Claude, Cursor, VS Code Copilot) to integrate with external tools and data sources.

With the NetSendo MCP server, you can:

- **Ask questions**: "How many subscribers do I have?" "Show me my contact lists"
- **Manage subscribers**: Create, update, delete, and search subscribers using natural language
- **Send messages**: "Send a welcome email to john@example.com"
- **Get insights**: "Analyze my subscriber list quality"

## Quick Setup

### Easy Configuration (Recommended)

Run this command to generate your MCP configuration:

```bash
# For local Docker installation:
docker compose exec app php artisan mcp:config --type=local

# For remote/hosted NetSendo:
docker compose exec app php artisan mcp:config --type=remote

# Auto-detect installation type:
docker compose exec app php artisan mcp:config
```

This will output ready-to-use configuration for Claude Desktop, Cursor, or VS Code.

---

## Setup Options

### Option A: Local Installation (Docker)

Best for self-hosted NetSendo running on your machine.

#### 1. Generate an API Key

Go to **Settings → API Keys** in NetSendo and create a new API key.

#### 2. Add API Key to Environment

Add to your `.env` file:

```bash
MCP_API_KEY=your-api-key-here
```

#### 3. Build MCP Container

```bash
docker compose build mcp
```

#### 4. Configure Your AI Tool

Run `php artisan mcp:config --type=local` to get the configuration, or use:

```json
{
  "mcpServers": {
    "netsendo": {
      "command": "docker",
      "args": [
        "compose",
        "-f",
        "/path/to/your/NetSendo/docker-compose.yml",
        "run",
        "--rm",
        "-i",
        "mcp"
      ]
    }
  }
}
```

Replace `/path/to/your/NetSendo` with your actual installation path.

---

### Option B: Remote Installation (Hosted)

Best for NetSendo hosted on a server or cloud (e.g., `https://app.mycompany.com`).

#### 1. Generate an API Key

Go to **Settings → API Keys** in your NetSendo instance.

#### 2. Configure Your AI Tool

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

Replace:

- `https://your-netsendo-domain.com` with your NetSendo URL
- `your-api-key-here` with your API key

> **Note**: Uses `npx` to automatically download and run the MCP client. No Docker required on your local machine.

---

## Configuration File Locations

| Tool                         | Location                                                          |
| ---------------------------- | ----------------------------------------------------------------- |
| **Claude Desktop (macOS)**   | `~/Library/Application Support/Claude/claude_desktop_config.json` |
| **Claude Desktop (Windows)** | `%APPDATA%\Claude\claude_desktop_config.json`                     |
| **Cursor IDE**               | Settings → MCP → Add Server                                       |
| **VS Code**                  | `.vscode/mcp.json` in your project                                |

---

## Multiple Installations

You can connect to multiple NetSendo instances by using different server names:

```json
{
  "mcpServers": {
    "netsendo-production": {
      "command": "npx",
      "args": [
        "-y",
        "@netsendo/mcp-client",
        "--url",
        "https://app.example.com",
        "--api-key",
        "prod-key"
      ]
    },
    "netsendo-staging": {
      "command": "npx",
      "args": [
        "-y",
        "@netsendo/mcp-client",
        "--url",
        "https://staging.example.com",
        "--api-key",
        "staging-key"
      ]
    },
    "netsendo-local": {
      "command": "docker",
      "args": [
        "compose",
        "-f",
        "/path/to/docker-compose.yml",
        "run",
        "--rm",
        "-i",
        "mcp"
      ]
    }
  }
}
```

---

## Available Tools

| Category         | Tools                                                                                  |
| ---------------- | -------------------------------------------------------------------------------------- |
| **Subscribers**  | List, get, create, update, delete, sync tags                                           |
| **Lists & Tags** | List contact lists, get list subscribers, list tags                                    |
| **Messaging**    | Send email, send SMS, check delivery status                                            |
| **Campaigns**    | List, create, update, delete campaigns; set lists/exclusions; schedule/send; get stats |
| **A/B Tests**    | Create tests, add variants, start/end tests, get results                               |
| **Funnels**      | Create automation funnels, add steps, activate/pause, get stats                        |
| **Account**      | Test connection, get account info                                                      |

### Campaign Tools (NEW)

| Tool                      | Description                       |
| ------------------------- | --------------------------------- |
| `list_campaigns`          | List all campaigns with filtering |
| `get_campaign`            | Get campaign details              |
| `create_campaign`         | Create new email/SMS campaign     |
| `update_campaign`         | Update campaign content/settings  |
| `set_campaign_lists`      | Set recipient lists               |
| `set_campaign_exclusions` | Set exclusion lists               |
| `schedule_campaign`       | Schedule for future sending       |
| `send_campaign`           | Send immediately or activate      |
| `get_campaign_stats`      | Get sending statistics            |
| `delete_campaign`         | Delete a campaign                 |

### A/B Test Tools (NEW)

| Tool                  | Description                |
| --------------------- | -------------------------- |
| `list_ab_tests`       | List all A/B tests         |
| `create_ab_test`      | Create test for a campaign |
| `add_ab_variant`      | Add test variant           |
| `start_ab_test`       | Start running test         |
| `end_ab_test`         | End test and pick winner   |
| `get_ab_test_results` | Get test results           |
| `delete_ab_test`      | Delete a test              |

### Funnel Tools (NEW)

| Tool               | Description                  |
| ------------------ | ---------------------------- |
| `list_funnels`     | List automation funnels      |
| `get_funnel`       | Get funnel details and steps |
| `create_funnel`    | Create new funnel            |
| `add_funnel_step`  | Add step to funnel           |
| `activate_funnel`  | Activate funnel              |
| `pause_funnel`     | Pause funnel                 |
| `get_funnel_stats` | Get funnel statistics        |
| `delete_funnel`    | Delete a funnel              |

## Security

- Uses your existing NetSendo API key with same permissions
- Rate limited (60 requests/minute)
- Sensitive data (passwords, payment info) never exposed
- All operations logged in NetSendo activity log

## Troubleshooting

### "Connection failed"

1. Ensure NetSendo is running and accessible
2. Verify your API key is valid
3. Check the URL is correct (include `https://`)

### "Tools not appearing"

Restart your AI tool after configuration changes.

### "npx command not found"

Install Node.js from [nodejs.org](https://nodejs.org/) (includes npm/npx).

---

For detailed documentation, see [mcp/README.md](../mcp/README.md).

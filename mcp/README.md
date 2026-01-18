# NetSendo MCP Server

Model Context Protocol (MCP) server for NetSendo email marketing platform. Enables AI assistants like Claude Desktop, Cursor, and VS Code to interact with your NetSendo installation.

## 🚀 Quick Start

### Generate Configuration Automatically

Run this command to get your MCP configuration:

```bash
# Auto-generate configuration
docker compose exec app php artisan mcp:config

# For remote/hosted installation
docker compose exec app php artisan mcp:config --type=remote
```

---

## 📡 Connection Options

### Option A: Local Docker Installation

Best for self-hosted NetSendo running with Docker.

```
┌─────────────────┐     STDIO      ┌─────────────────┐     HTTP      ┌─────────────────┐
│  Claude/Cursor  │ ◄──────────► │   MCP Server    │ ◄───────────► │    NetSendo     │
│   (AI Client)   │               │   (Docker)      │               │   (local)       │
└─────────────────┘               └─────────────────┘               └─────────────────┘
```

#### Setup Steps

1. **Generate API Key** in NetSendo: **Settings → API Keys**

2. **Add to .env:**

   ```bash
   MCP_API_KEY=your-api-key-here
   ```

3. **Build MCP container:**

   ```bash
   docker compose build mcp
   ```

4. **Configure your AI tool:**
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

---

### Option B: Remote/Hosted Installation

Best for connecting to NetSendo hosted on a server (e.g., `https://app.example.com`).

```
┌─────────────────┐     STDIO      ┌─────────────────┐     HTTPS     ┌─────────────────┐
│  Claude/Cursor  │ ◄──────────► │  MCP Client     │ ◄───────────► │    NetSendo     │
│   (AI Client)   │               │  (npx)          │               │   (remote)      │
└─────────────────┘               └─────────────────┘               └─────────────────┘
```

#### Setup Steps

1. **Generate API Key** in your NetSendo instance

2. **Configure your AI tool:**
   ```json
   {
     "mcpServers": {
       "netsendo": {
         "command": "npx",
         "args": [
           "-y",
           "@netsendo/mcp-client",
           "--url",
           "https://your-domain.com",
           "--api-key",
           "your-api-key"
         ]
       }
     }
   }
   ```

> **Note:** Requires Node.js 18+ installed on your machine.

---

## 📁 Configuration File Locations

| Tool                         | Location                                                          |
| ---------------------------- | ----------------------------------------------------------------- |
| **Claude Desktop (macOS)**   | `~/Library/Application Support/Claude/claude_desktop_config.json` |
| **Claude Desktop (Windows)** | `%APPDATA%\Claude\claude_desktop_config.json`                     |
| **Cursor IDE**               | Settings → MCP → Add Server                                       |
| **VS Code**                  | `.vscode/mcp.json` in your project                                |

---

## 🛠️ Available Tools

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

### Messaging

| Tool                 | Description                   |
| -------------------- | ----------------------------- |
| `list_mailboxes`     | Get available email mailboxes |
| `send_email`         | Send an email to a subscriber |
| `get_email_status`   | Check email delivery status   |
| `list_sms_providers` | Get available SMS providers   |
| `send_sms`           | Send an SMS message           |
| `get_sms_status`     | Check SMS delivery status     |

### Campaign Management

| Tool                      | Description                                                          |
| ------------------------- | -------------------------------------------------------------------- |
| `list_campaigns`          | List all campaigns with filtering                                    |
| `get_campaign`            | Get campaign details                                                 |
| `create_campaign`         | Create email/SMS campaign (**requires `channel`**: 'email' or 'sms') |
| `update_campaign`         | Update campaign settings                                             |
| `set_campaign_lists`      | Set recipient lists                                                  |
| `set_campaign_exclusions` | Set exclusion lists                                                  |
| `schedule_campaign`       | Schedule for future sending                                          |
| `send_campaign`           | Send immediately                                                     |
| `get_campaign_stats`      | Get sending statistics                                               |
| `delete_campaign`         | Delete a campaign                                                    |

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

### Account

| Tool               | Description             |
| ------------------ | ----------------------- |
| `test_connection`  | Test API connection     |
| `get_account_info` | Get account information |

---

## 💡 Pre-built Prompts

| Prompt                | Description                        |
| --------------------- | ---------------------------------- |
| `analyze_subscribers` | Analyze subscriber list quality    |
| `send_newsletter`     | Help compose and send a newsletter |
| `cleanup_list`        | Identify problematic subscribers   |

---

## 🧑‍💻 CLI Usage

The MCP client supports command-line arguments:

```bash
netsendo-mcp --url <url> --api-key <key> [--debug]

Options:
  --url <url>       NetSendo API URL (e.g., https://app.netsendo.com)
  --api-key <key>   NetSendo API key
  --debug           Enable debug logging
  -h, --help        Display help
```

Environment variables are also supported:

- `NETSENDO_API_URL` - API URL
- `NETSENDO_API_KEY` - API key

CLI arguments take priority over environment variables.

---

## 🔒 Security

- API keys are never logged or exposed
- All API calls respect NetSendo permissions
- Rate limiting: 60 requests/minute
- Sensitive data never returned

---

## 🐛 Troubleshooting

### "Connection failed"

1. Ensure NetSendo is running and accessible
2. Verify API key is valid
3. Check URL is correct (include `https://`)

### "Tools not appearing"

Restart your AI tool after configuration changes.

### "npx command not found"

Install Node.js from [nodejs.org](https://nodejs.org/).

---

Made with ❤️ by [NetSendo Team](https://netsendo.com)

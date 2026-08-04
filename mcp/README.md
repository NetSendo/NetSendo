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

| Tool                   | Description                                                    |
| ---------------------- | -------------------------------------------------------------- |
| `list_contact_lists`   | Get all contact lists                                          |
| `get_contact_list`     | Get list details                                               |
| `get_list_stats`       | Membership breakdown, engagement share, 30-day growth, config   |
| `create_contact_list`  | Create a list (`type`: email or sms)                           |
| `update_contact_list`  | Update list settings (partial — untouched settings preserved)   |
| `delete_contact_list`  | Delete a list (**`confirm: true` required if it has members**) |
| `get_list_subscribers` | Get subscribers in a list                                      |
| `list_tags`            | Get all available tags                                         |
| `list_custom_fields`   | Get custom field definitions                                   |

### List Import

| Tool                    | Description                                                       |
| ----------------------- | ----------------------------------------------------------------- |
| `preview_list_import`   | Dry-run: detected mapping, per-action counts, problem rows          |
| `import_subscribers`    | Import CSV / TSV / JSON records / plain address list (≤5000 rows)  |

Accepted shapes:

| `format`   | Where the data goes | Notes                                                                |
| ---------- | ------------------- | -------------------------------------------------------------------- |
| `csv`      | `data` (string)     | Delimiter and header row auto-detected (`,` `;` tab `|`)             |
| `tsv`      | `data` (string)     | Tab-separated                                                        |
| `json`     | `records` (array)   | Objects keyed by field name; supports `custom_fields` and `tags`     |
| `emails`   | `data` (string)     | One per line; accepts `Anna Kowalska <anna@example.com>`             |

Column mapping is automatic for common PL/EN/DE/ES headers (`email`, `e-mail`, `imię`,
`nazwisko`, `telefon`, `first_name`, …). Override it with
`column_mapping: {"0":"email","1":"first_name","3":"custom:city","2":"ignore"}`.

Import safeguards, all tunable per call: skip invalid syntax (default on), skip
disposable domains (on), skip suppressed addresses (on), skip role addresses (off),
auto-correct typo domains (off). Duplicates fold by real mailbox, so `JAN@x.pl`,
`jan@x.pl` and `j.an+news@gmail.com` collapse onto one contact.

### List Export

| Tool                | Description                                                            |
| ------------------- | ---------------------------------------------------------------------- |
| `export_list`       | Inline export, filtered and cursor-paged; `json`, `csv` or `ndjson`     |
| `get_export_fields` | Available columns, including the account's custom fields                |
| `queue_list_export` | Queue the classic full CSV export; owner receives a download link       |

Filters: status, tag IDs, signup date range, and `engaged` (`false` isolates contacts
who never opened or clicked). Up to 5000 rows per call — when `has_more` is true,
call again with `cursor: next_cursor`.

### List Hygiene

| Tool                  | Description                                                            |
| --------------------- | ---------------------------------------------------------------------- |
| `analyze_list_health` | Health score 0–100, per-category counts with samples, recommendations   |
| `clean_list`          | Act on matched categories (**dry run by default**)                      |
| `dedupe_list`         | Merge contacts sharing one real mailbox (**dry run by default**)        |
| `verify_list_emails`  | Syntax + MX/DNS deliverability check, per domain                        |
| `get_hygiene_options` | Exact category and action names accepted by `clean_list`                |

Categories: `invalid_syntax`, `typo_domain`, `disposable_domain`, `role_address`,
`missing_contact`, `duplicate`, `hard_bounced`, `soft_bounce_risk`, `suppressed`,
`unsubscribed`, `unconfirmed`, `globally_inactive`, `never_engaged`, `dormant`.

Actions: `unsubscribe`, `remove`, `tag`, and the irreversible `delete` / `suppress`.

> **Safety.** `clean_list` and `dedupe_list` default to `dry_run: true`. Writing needs
> `dry_run: false`, and `delete` / `suppress` additionally need `confirm: true`.
> Deleting a list with members needs `confirm: true`. A filter-based
> `remove_list_members` needs `confirm: true`.

### List Membership

| Tool                   | Description                                              |
| ---------------------- | -------------------------------------------------------- |
| `add_list_members`     | Attach existing contacts to a list                       |
| `remove_list_members`  | Detach from a list (contacts and other lists untouched)  |
| `set_member_status`    | Bulk status change: active / inactive / unsubscribed / bounced |
| `copy_list_members`    | Copy to another list of the same channel                 |
| `move_list_members`    | Move to another list of the same channel                 |
| `tag_list_members`     | Add or remove tags across a segment                      |

All six take the same selection block — `subscriber_ids`, `emails`, or a `filter`
such as `{"status":"active","never_opened":true,"subscribed_before":"2025-01-01","limit":500}` —
capped at 5000 contacts per call. Pass `trigger_automations: false` to migrate an
audience without restarting the target list's welcome sequence.

### Activity & Engagement

| Tool                      | Description                                                       |
| ------------------------- | ----------------------------------------------------------------- |
| `get_list_activity`       | Event feed: signups, confirmations, unsubscribes, bounces, sends, opens, clicks |
| `get_list_engagement`     | Growth, churn, open/click/CTOR, top messages and links, most engaged |
| `get_subscriber_activity` | One contact's full timeline, memberships, tags and queued messages |

### Suppression & Notifications

| Tool                 | Description                                                          |
| -------------------- | -------------------------------------------------------------------- |
| `list_suppressions`  | The account-wide do-not-mail list                                    |
| `suppress_emails`    | Suppress addresses and unsubscribe them everywhere                   |
| `unsuppress_emails`  | Lift suppression (does **not** resubscribe anyone)                   |
| `send_notification`  | Report back to the account owner's notification centre               |
| `list_notifications` | Read recent notifications and the unread count                       |

Suppression outranks every list: suppressed addresses are skipped by future imports.
`send_notification` reaches only the account owner — it cannot message subscribers.

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

| Prompt                | Description                                                     |
| --------------------- | --------------------------------------------------------------- |
| `analyze_subscribers` | Analyze subscriber list quality                                 |
| `send_newsletter`     | Help compose and send a newsletter                              |
| `import_contacts`     | Import contacts safely, with a preview before anything is written |
| `cleanup_list`        | Audit a list and propose an approved-only clean-up plan          |
| `list_report`         | Performance report with prioritised next steps                   |

## 📚 Resources

| Resource            | Description                                       |
| ------------------- | ------------------------------------------------- |
| `netsendo://info`   | Instance information and capabilities             |
| `netsendo://stats`  | Quick statistics overview                         |
| `netsendo://lists`  | Contact list directory with sizes and channels    |

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

### Required API key permissions

| Scope                 | Unlocks                                                            |
| --------------------- | ------------------------------------------------------------------ |
| `lists:read`          | List details, stats, import preview, health report, activity, engagement |
| `lists:write`         | Create/update/delete lists, import, clean, dedupe, membership changes |
| `subscribers:read`    | Inline export, subscriber timeline, suppression list                |
| `subscribers:write`   | Suppression changes, subscriber CRUD                                |
| `notifications:write` | `send_notification`                                                 |

`lists:write` and `notifications:write` were added in 1.4.0. Keys created before the
upgrade are migrated automatically **if** they already held `subscribers:write`;
deliberately narrow keys keep their original scope and must be updated by hand under
**NetSendo → API keys**. A missing scope returns a `403` naming the exact permission.

### Destructive operations

Tools that can lose data refuse to act until they are told to twice:

- `clean_list`, `dedupe_list` — run as a dry run unless `dry_run: false`
- `clean_list` with `action: delete` or `suppress` — additionally needs `confirm: true`
- `delete_contact_list` on a non-empty list — needs `confirm: true`
- `remove_list_members` selected by `filter` — needs `confirm: true`

The intent is that an assistant always shows the user the affected count from the dry
run before anything is written.

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

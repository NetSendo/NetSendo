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

List settings and sending schedule:

| Tool                        | Description                                                                                                 |
| --------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `get_list_cron_settings`    | A list's sending windows and per-minute limit, with the effective values and the global CRON settings       |
| `update_list_cron_settings` | Set a list's sending windows (minutes from midnight per day) and limit, or return it to the global defaults |
| `get_list_defaults`         | Account-level defaults for new lists and the instance-wide CRON settings                                    |
| `update_list_defaults`      | Change the list defaults (deep-merged); `cron` for the account owner only                                   |

`get_contact_list` returns the whole configuration (settings document, tags, co-registration, limits,
webhook); `create_contact_list` / `update_contact_list` accept every setting of the list editor and
deep-merge `settings` (subscription, sending, pages, advanced).

### Tags & Custom Fields

| Tool                  | Description                                                                                                       |
| --------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `create_tag`          | Create a tag (name unique per account, optional hex colour and description)                                       |
| `update_tag`          | Rename a tag or change its colour/description                                                                     |
| `delete_tag`          | Delete a tag and detach it from subscribers, lists and messages (**`confirm: true`** if subscribers carry it)     |
| `get_custom_field`    | Full settings of one custom field (placeholder, type, options, flags, scope)                                      |
| `create_custom_field` | Create a global or list-specific field (text, number, date, select, radio, checkbox); its name becomes `[[name]]` |
| `update_custom_field` | Change label, type, options, default value or form flags, or rename the field                                     |
| `delete_custom_field` | Delete a field and its stored values (**`confirm: true`** if any subscriber has a value)                          |

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

`create_campaign` / `update_campaign` also take `template_id` (a reference — copy the template's
`html` into `content`), autoresponder triggers (`trigger_type` + `trigger_config`, synced to an
automation rule like the editor does), `tag_ids`, `translations`, `tracked_links`, CRM contacts,
include/exclude custom-field filters, `ab_test_config` and `send_in_subscriber_timezone`.
`get_campaign` returns every editable field.

| Tool                           | Description                                                                                                 |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| `send_campaign_test`           | Send a `[TEST]` copy to up to 5 addresses, personalised with a subscriber or sample data; nothing is queued |
| `preview_campaign`             | Render subject + HTML as a recipient sees it; lists unresolved placeholders                                 |
| `duplicate_campaign`           | Copy a campaign as a new draft                                                                              |
| `set_campaign_active`          | Activate or pause an autoresponder (activation promotes a draft and enables its trigger rule)               |
| `get_campaign_recipient_count` | Who the campaign would reach now, after exclusions and filters                                              |
| `resend_campaign_failed`       | Re-queue recipients whose delivery failed (**`confirm: true`**)                                             |
| `send_campaign_to_missed`      | Send an active autoresponder now to subscribers who missed it (**`confirm: true`**)                         |

### Email Templates

| Tool                        | Description                                                                                       |
| --------------------------- | ------------------------------------------------------------------------------------------------- |
| `list_templates`            | Own and system starter templates (filters: search, category, source, type)                        |
| `get_template`              | Full template: `html`, `mjml`, builder `json_structure`, settings, editor mode, messages using it |
| `create_template`           | Create a template from HTML (`content`) or builder blocks (`json_structure`)                      |
| `update_template`           | Partially update an own template (system templates: duplicate first)                              |
| `delete_template`           | Soft-delete an own template; campaigns keep their content                                         |
| `duplicate_template`        | Copy an own or system template into the account                                                   |
| `preview_template`          | Render placeholders for a subscriber or sample data; reports unknown placeholders                 |
| `list_template_categories`  | System and own template categories                                                                |
| `list_template_block_types` | Builder block types with default content/settings (schema for `json_structure`)                   |
| `list_template_blocks`      | Saved builder blocks (own and global)                                                             |
| `create_template_block`     | Save a reusable builder block                                                                     |
| `update_template_block`     | Change an own saved block                                                                         |
| `delete_template_block`     | Delete an own saved block                                                                         |

### System Emails & Pages

Global defaults plus per-list overrides (copy-on-write), addressed by slug and an optional `list_id`.
Editing the global defaults needs the account owner's key (not a team member's).

| Tool                      | Description                                                                                                   |
| ------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `list_system_emails`      | Automatic emails (double opt-in, welcome, unsubscribe, owner notification) as resolved for a list or globally |
| `get_system_email`        | Subject, HTML, active flag and placeholders (incl. the slug's required link, e.g. `[[activation-link]]`)      |
| `update_system_email`     | Change subject/content/active; with `list_id` creates or edits the list override, without it edits the global |
| `set_system_email_active` | Switch one email on or off for one list                                                                       |
| `reset_system_email`      | Remove a list's override so it uses the global default again                                                  |
| `list_system_pages`       | Pages shown after signup, activation, unsubscribe and preference changes                                      |
| `get_system_page`         | Title, HTML, access and placeholders of one page                                                              |
| `update_system_page`      | Change title/content/access (list overrides can also be renamed via `new_slug`)                               |
| `reset_system_page`       | Remove a list's override of a page                                                                            |

### Subscription Forms

| Tool             | Description                                                                                      |
| ---------------- | ------------------------------------------------------------------------------------------------ |
| `list_forms`     | Subscription forms, filterable by list and status                                                |
| `get_form`       | A form's configuration plus hosted URL and HTML / JS / iframe embed codes                        |
| `create_form`    | Create a signup form for an email list (fields by id, design preset, styles, redirects, captcha) |
| `update_form`    | Change a form; styles merged, fields replaced; status `active` publishes it                      |
| `delete_form`    | Delete a form (**`confirm: true`** when it has submissions)                                      |
| `duplicate_form` | Copy a form as a new draft with new embed code                                                   |

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

| Tool                          | Description                                                             |
| ----------------------------- | ----------------------------------------------------------------------- |
| `list_funnels`                | List automation funnels                                                 |
| `get_funnel`                  | Get funnel details, steps with their settings and connections           |
| `create_funnel`               | Create new funnel                                                       |
| `update_funnel`               | Rename a funnel or change its trigger                                   |
| `add_funnel_step`             | Add any step after a step, a condition's path or an A/B variant's path  |
| `update_funnel_step`          | Change a step's settings and connections                                |
| `delete_funnel_step`          | Delete a step and reconnect the funnel around it                        |
| `enroll_subscriber_in_funnel` | Put a subscriber into an active funnel (starts `manual` funnels)        |
| `activate_funnel`             | Activate funnel                                                         |
| `pause_funnel`                | Pause funnel                                                            |
| `get_funnel_stats`            | Get funnel statistics                                                   |
| `delete_funnel`               | Delete a funnel                                                         |

### Automation Rules

"If this happens, check that, then do this" rules (`trigger_event` + `trigger_config`, optional
conditions, ordered actions). Call `get_automation_options` before building one.

| Tool                     | Description                                                                                                 |
| ------------------------ | ----------------------------------------------------------------------------------------------------------- |
| `get_automation_options` | Catalogue of trigger events (with their filters), condition types and action types with their config fields |
| `list_automations`       | Rules, filterable by trigger event, active state and name                                                   |
| `get_automation`         | One rule with 7-day stats; `read_only` marks rules synced from an autoresponder trigger                     |
| `create_automation`      | Create a rule (validated against the catalogue and account ownership)                                       |
| `update_automation`      | Change a rule; only the fields sent change                                                                  |
| `set_automation_active`  | Activate, deactivate or flip a rule                                                                         |
| `duplicate_automation`   | Copy a rule (the copy starts inactive)                                                                      |
| `delete_automation`      | Delete a rule (default automations need **`confirm: true`**)                                                |
| `get_automation_logs`    | Execution log: status per run, subscriber, per-action results and errors                                    |

### Webhooks

| Tool                        | Description                                                                 |
| --------------------------- | --------------------------------------------------------------------------- |
| `list_webhook_events`       | Event names a webhook can subscribe to                                      |
| `list_webhooks`             | The account's webhooks with status, failure count and last delivery         |
| `get_webhook`               | One webhook's settings                                                      |
| `create_webhook`            | Register a URL for chosen events; returns the HMAC signing secret once      |
| `update_webhook`            | Change name, URL, events or active state                                    |
| `delete_webhook`            | Remove a webhook                                                            |
| `test_webhook`              | Send a `webhook.test` delivery and report whether the endpoint answered 2xx |
| `regenerate_webhook_secret` | Issue a new signing secret; the old one stops working immediately           |

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
| `lists:read`          | List details, stats, import preview, health report, activity, engagement, list settings, forms, system emails/pages |
| `lists:write`         | Create/update/delete lists, import, clean, dedupe, membership changes, tags, custom fields, forms, list settings, system emails/pages |
| `subscribers:read`    | Inline export, subscriber timeline, suppression list                |
| `subscribers:write`   | Suppression changes, subscriber CRUD                                |
| `notifications:write` | `send_notification`                                                 |
| `messages:read/write` | Campaigns, autoresponders, test sends, previews, email templates     |
| `funnels:read/write`  | Funnels and automation rules                                        |
| `webhooks:read/write` | Webhook tools                                                       |

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
- `delete_tag`, `delete_custom_field`, `delete_form` when data is attached — need `confirm: true`
- `delete_automation` on a default automation — needs `confirm: true`
- `resend_campaign_failed`, `send_campaign_to_missed` — need `confirm: true`

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

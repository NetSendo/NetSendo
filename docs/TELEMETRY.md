# Telemetry (opt-in)

NetSendo can send NetSendo PSA **one anonymous usage report a day**. It is **off by default**.
Nothing is sent until the administrator of the installation switches it on, and it can be
switched off at any time. The public description, with every field, is
<https://netsendo.com/en/docs/telemetry>.

## Switching it on and off

- **Panel:** *Settings → Telemetry* (administrator only). The page shows the exact report
  the installation would send, the installation id and when the last report went out.
- **Deployment type:** `NETSENDO_DEPLOYMENT` (`docker`, `cloud`, `other`) is reported as
  is; NetSendo Cloud sets `cloud`. Unset: `docker` when `/.dockerenv` exists, else `other`.
- **Environment:** `NETSENDO_TELEMETRY=true` switches it on, `NETSENDO_TELEMETRY=false`
  switches it off and hides the switch in the panel. When the variable is not set, the
  panel setting decides (default: off).
- Switching it off stops the reports and asks netsendo.com to delete every report of the
  installation id (`DELETE https://netsendo.com/api/telemetry/{id}`). The next time it is
  switched on, a new id is created.

## What is sent

`POST https://netsendo.com/api/telemetry`, JSON, once a day (`php artisan
netsendo:telemetry`, run hourly by the scheduler; it sends in an hour derived from the
installation id, so installations do not all report at once, and does nothing while
telemetry is off; `--show` prints the report without sending it):

- `schema` — format version (1) and `instance_id` — a random UUID created when telemetry
  is switched on, stored in the installation's settings. Not derived from the domain,
  licence key, email or any account.
- `app` — NetSendo version, plan (`silver`, `gold`, `none`), deployment (`docker`,
  `cloud`, `other`), default panel language.
- `platform` — PHP version (major.minor), database engine and version (major.minor),
  queue driver.
- `usage` — counts rounded to a range (`0`, `1-10`, `11-100`, `101-1k`, `1k-10k`,
  `10k-100k`, `100k-1m`, `1m+`): contacts, email lists, SMS lists, users, campaigns,
  emails and SMS sent in the last 30 days, active funnels and automations, webinars,
  forms, mailboxes.
- `features` — true/false: an SMS provider, an AI provider, Brain used, an MCP key, a
  public API key, an active webhook, CRM contacts, webinars, A/B tests, and the
  WooCommerce, WordPress and n8n connections, contacts from Shopify, Stripe, Polar,
  Calendly, Google Calendar and Zoom.
- `providers` — provider *types* only (mailboxes: `smtp`, `sendgrid`, `gmail`, `nmi`; SMS
  gateways such as `twilio` or `smsapi`; AI providers such as `openai` or `ollama`): never
  host names, accounts or keys.

**Never sent:** contacts or their data, email addresses, names, message subjects or
content, list, form or campaign names, the domain, host names, API or licence keys, IP
addresses of contacts, logs, anything typed into the panel.

## Code

`app/Services/Telemetry/TelemetryReport.php` builds the report,
`app/Services/Telemetry/TelemetryClient.php` sends it and asks for deletion,
`app/Services/Telemetry/TelemetryService.php` keeps the switch and the id (settings
`telemetry_enabled`, `telemetry_instance_id`, `telemetry_last_sent_at`,
`telemetry_notice_dismissed_at`), `app/Console/Commands/SendTelemetry.php` is the command.
The dashboard asks the administrator once (`TelemetryNotice.vue`); the answer is kept. Tests:
`tests/Feature/TelemetryTest.php`. The server validates every field against
`lib/telemetry.ts` in NetSendo/NetSendo.com and stores nothing else.

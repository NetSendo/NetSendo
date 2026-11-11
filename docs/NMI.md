# NetSendo Mail Infrastructure (NMI)

Professional email sending infrastructure with dedicated IP management, intelligent IP warming, DKIM signing, and real-time blacklist monitoring.

## Quick Start

### 1. Enable in `.env`

```bash
NMI_ENABLED=true
```

### 2. Run Migrations

```bash
docker compose exec app php artisan migrate
```

### 3. Start NMI Service

```bash
docker compose --profile nmi up -d
```

### 4. Access Dashboard

Navigate to **Settings → Mail Infrastructure** in the UI.

### 5. Add a mailbox that sends through NMI

1. Add your sending domain in **Deliverability** and verify it (the CNAME record).
2. Open **Settings → Mailboxes → Add mailbox** and choose **NetSendo MTA**. The provider is offered when `NMI_ENABLED=true`, the installation has GOLD, and the MTA container answers (the form shows its status and a *Check again* link).
3. Choose the verified domain. The sender address must be in that domain.
4. Optionally choose a dedicated IP from your pools. A free IP is assigned to the domain when you save. The form says whether the MTA really sends from that address (see [Sending from dedicated IPs](#sending-from-dedicated-ips)).
5. Save. Open the mailbox again: the **DKIM signature** block shows the TXT record of the domain's key. Publish it in the domain's DNS and click **Verify record**. Mail is signed from then on.
6. Use **Test connection**: the MTA must accept the test message. The result says if the message went out unsigned or not from the dedicated IP.

There is no login to type in. See [Sending through NMI](#sending-through-nmi) for what happens behind the form.

---

## Features

### IP Pool Management

Create and manage IP pools (Shared or Dedicated) to organize your sending infrastructure.

- **Shared Pools**: Multiple domains share the same IPs
- **Dedicated Pools**: Exclusive IPs for high-volume senders

### IP Warming

Intelligent 28-day warming schedule that gradually increases sending volume:

| Days  | Daily Limit    |
| ----- | -------------- |
| 1-3   | 50-200         |
| 4-7   | 500-2,000      |
| 8-14  | 5,000-20,000   |
| 15-21 | 30,000-60,000  |
| 22-28 | 80,000-100,000 |

### DKIM Key Management

- Automatic 2048-bit RSA key generation
- One-click DNS record copy
- DNS verification tool
- Optional auto-rotation (6-month default)

### Blacklist Monitoring

Real-time monitoring against major DNSBLs:

- Spamhaus ZEN
- SpamCop
- Barracuda
- SORBS
- CBL
- And more...

---

## Sending through NMI

A mailbox of the **NetSendo MTA** provider is linked to a verified domain (`mailboxes.domain_configuration_id`) and, optionally, to a dedicated IP (`mailboxes.dedicated_ip_id`).

### The domain's login to the MTA

The MTA only relays mail of a session that has logged in; everything else is refused at `RCPT TO`, so it is never an open relay.

- When the first NMI mailbox of a domain is saved, NetSendo issues an SMTP login for the domain: a username (`nmi-<domain id>-<random>`) and a 48-character random password. The password is stored encrypted in `domain_configurations.nmi_smtp_password` and is never shown or sent to the browser.
- NetSendo writes all logins to `storage/app/nmi-mta/auth.json` (`NMI_MTA_AUTH_FILE`), as SHA-256 hashes: `{ "version": 1, "users": { "<username>": "<hash>" } }`.
- The file is on the `nmi-auth` volume, which the MTA container mounts read-only at `/opt/haraka/netsendo`. The Haraka plugin `docker/haraka/plugins/netsendo_auth.js` reads it again whenever it changes, so a new or rotated login works without restarting the MTA.
- Removing a domain removes its login. `php artisan nmi:sync-mta-auth` rewrites the file from the database (scheduled every ten minutes while `NMI_ENABLED=true`), which restores it after the volume was recreated.

### DKIM

Mail is signed by NetSendo before it is handed to the MTA.

- **The domain's key.** Saving an NMI mailbox creates a DKIM key for its domain (2048-bit RSA, the private key encrypted in `domain_configurations.nmi_dkim_private_key`). The mailbox form shows the TXT record (`<selector>._domainkey.<domain>`). Mail is signed with the key **only after the record has been verified**: a signature that points at a record nobody published would only produce `dkim=fail` at the receiver. *Check again* looks the record up once more; if it has disappeared, signing stops.
- **The key of a dedicated IP** (the IP's page, *Generate DKIM*) is used while the domain's own key is not verified, provided the IP is assigned to the mailbox's domain.
- Neither: the mail goes out unsigned, and *Test connection* says so.
- The Deliverability check of the domain looks for these selectors first.

Replacing a domain's key (`POST settings/nmi/domains/{domain}/dkim/generate` with `replace=1`) starts unverified again.

### Sending from dedicated IPs

With a dedicated IP chosen, every message tells the MTA which address to send from and which HELO name belongs to it (the IP's host name). The Haraka plugin `docker/haraka/plugins/netsendo_outbound.js` binds the outbound connection to that address and removes the internal headers. The IP's warming limit and counters apply as before.

The MTA can only send from an address it owns. In the default setup the MTA container sits on NetSendo's Docker network and owns none of the server's addresses, so all mail leaves from the server's main address, whatever IP the mailbox has. The mailbox form shows this under the IP picker ("The MTA does not own this address…"), *Test connection* repeats it, and `docker logs netsendo-mta` has a warning per message.

To send from dedicated IPs:

1. **Configure the addresses on the server** (the provider's panel plus `ip addr add` or netplan), each with a PTR record that points to the IP's host name, and that host name resolving back to the IP.
2. **Run the MTA on the server's network** with the override file (Linux, Docker Compose 2.24+):

   ```bash
   docker compose -f docker-compose.yml -f docker-compose.nmi-host.yml --profile nmi up -d
   ```

   and in `.env`:

   ```bash
   NMI_MTA_HOST=10.58.0.1
   NMI_MTA_PORT=2525
   ```

   In this mode the MTA listens only on the gateway address of NetSendo's Docker network (`docker/haraka/host-network/smtp.ini`), not on the server's public addresses.
3. **Translated addresses.** Where the provider maps a public address to a private one on the server (DigitalOcean anchor IPs, Google Cloud, AWS), the server does not own the public address itself. Say which private address belongs to which public one:

   ```bash
   NMI_BIND_MAP=203.0.113.10=10.19.0.5,203.0.113.11=10.19.0.6
   ```

4. Open the mailbox form: under the IP picker it now says "The MTA sends from this address".

`NMI_HELO_HOST` sets the HELO name of mail without a dedicated IP (default: the container's host name, which other servers cannot resolve). Use a name that resolves to the server's address and matches its PTR record.

Not every host can do this. A Google Cloud VM, for example, has one external address per network interface, so one such VM cannot send from several dedicated IPs.

### What the server needs

- **Outbound port 25.** The MTA delivers straight to the recipients' mail servers. Many cloud providers block outbound port 25 (Google Cloud always does; AWS, Azure, DigitalOcean and others by default). On such a host the MTA accepts mail and then cannot deliver it: `docker logs netsendo-mta` shows the connection timeouts.
- **DNS for the sending address**: a PTR record of the sending IP that matches the MTA's host name, and the IP in the domain's SPF record.

### GOLD

Setting NMI up needs GOLD: adding an NMI mailbox, switching a mailbox to NMI, changing its domain or IP. An NMI mailbox set up earlier stays editable and keeps sending without it.

---

## Docker Setup

NMI uses Haraka MTA running in a separate container.

### Production

```bash
# Start all services including NMI
docker compose --profile nmi up -d

# View MTA logs
docker logs netsendo-mta
```

### Development

```bash
# Start with NMI profile
docker compose -f docker-compose.dev.yml --profile nmi up -d
```

### Volumes

NMI creates the following persistent volumes:

| Volume      | Purpose                                                        |
| ----------- | -------------------------------------------------------------- |
| `nmi_auth`  | MTA logins of the sending domains (written by the app, hashes) |
| `nmi_queue` | Email queue storage                                            |
| `nmi_logs`  | MTA log files                                                  |
| `nmi_dkim`  | DKIM private keys                                              |
| `nmi_tls`   | TLS certificates                                               |

### Updating an installation that already runs the MTA

The MTA image is built on your server, so rebuild it after updating the files:

```bash
docker compose --profile nmi up -d --build
```

This picks up the `nmi-auth` volume, the `netsendo_auth` plugin and the new plugin list (`docker/haraka/config/plugins`).

---

## Configuration Reference

### Master Switch

| Variable      | Default | Description               |
| ------------- | ------- | ------------------------- |
| `NMI_ENABLED` | `false` | Enable NMI features in UI |

### MTA Connection

| Variable             | Default        | Description                  |
| -------------------- | -------------- | ---------------------------- |
| `NMI_MTA_HOST`       | `netsendo-mta` | Haraka container hostname    |
| `NMI_MTA_PORT`       | `25`           | SMTP port                    |
| `NMI_MTA_ENCRYPTION` | `none`         | `none` (clear text on the Docker network), `tls` (require STARTTLS) or `ssl` (TLS from the start) |
| `NMI_MTA_AUTH_FILE`  | `storage/app/nmi-mta/auth.json` | Where the app writes the MTA logins; the MTA reads the same file (`NMI_AUTH_FILE` in its container) |
| `NMI_HELO_HOST`      | (container name) | HELO name of the MTA for mail without a dedicated IP |
| `NMI_BIND_MAP`       | (empty)        | Dedicated IPs the provider translates: `public=private,public=private` |

### IP Warming

| Variable                   | Default | Description               |
| -------------------------- | ------- | ------------------------- |
| `NMI_WARMING_DAYS`         | `28`    | Duration of warming cycle |
| `NMI_WARMING_AUTO_ADVANCE` | `true`  | Auto-advance daily        |

### DKIM

| Variable                   | Default | Description          |
| -------------------------- | ------- | -------------------- |
| `NMI_DKIM_KEY_SIZE`        | `2048`  | RSA key size         |
| `NMI_DKIM_ROTATION_MONTHS` | `6`     | Key rotation period  |
| `NMI_DKIM_AUTO_ROTATE`     | `false` | Enable auto-rotation |

### Blacklist Monitoring

| Variable                    | Default | Description            |
| --------------------------- | ------- | ---------------------- |
| `NMI_BLACKLIST_CHECK_HOURS` | `24`    | Check interval (hours) |
| `NMI_BLACKLIST_ALERT`       | `true`  | Send alerts on listing |

### Reputation Thresholds

| Variable                 | Default | Description                    |
| ------------------------ | ------- | ------------------------------ |
| `NMI_REPUTATION_PAUSE`   | `50`    | Pause sending below this score |
| `NMI_REPUTATION_WARNING` | `70`    | Warning threshold              |
| `NMI_MAX_BOUNCE_RATE`    | `5`     | Max bounce rate (%)            |
| `NMI_MAX_COMPLAINT_RATE` | `0.1`   | Max complaint rate (%)         |

---

## Migration Guide

> [!IMPORTANT]
> **For users upgrading from versions without NMI:**

### Step 1: Update Docker Compose

Pull the latest `docker-compose.yml` which includes the `nmi-mta` service.

### Step 2: Update `.env`

Add NMI variables from `.env.example`:

```bash
NMI_ENABLED=false
NMI_MTA_HOST=netsendo-mta
# ... (see .env.example for full list)
```

### Step 3: Run Migrations

```bash
docker compose exec app php artisan migrate
```

This creates the required tables:

- `ip_pools`
- `dedicated_ip_addresses`
- NMI fields in `domain_configurations`

### Step 4: Enable NMI (Optional)

When ready to use NMI:

```bash
# Update .env
NMI_ENABLED=true

# Start NMI container
docker compose --profile nmi up -d
```

---

## Troubleshooting

### NMI not showing in menu

1. Verify `NMI_ENABLED=true` in `.env`
2. Clear cache: `php artisan optimize:clear`
3. Rebuild frontend: `npm run build`

### MTA container not starting

```bash
# Check logs
docker logs netsendo-mta

# Verify network
docker network ls | grep netsendo
```

### "NetSendo MTA" is not offered in the mailbox form

1. `NMI_ENABLED=true` in `.env`, then recreate the containers (`docker compose up -d`): the provider is hidden otherwise.
2. The button is greyed out while the MTA does not answer: start it with `docker compose --profile nmi up -d` and click *Check again*.
3. A GOLD badge on the button means the installation has no GOLD licence.

### Test connection says "535 Authentication failed"

The MTA does not know the domain's login: it is not reading the file NetSendo writes.

```bash
# The file NetSendo wrote
docker compose exec app cat storage/app/nmi-mta/auth.json

# The same file, as the MTA sees it
docker exec netsendo-mta cat /opt/haraka/netsendo/auth.json

# Write it again from the database
docker compose exec app php artisan nmi:sync-mta-auth
```

If the second command fails, the MTA container was created before the `nmi-auth` volume existed: `docker compose --profile nmi up -d --build`.

### Saving the mailbox says the MTA login file could not be written

The app cannot write to `storage/app/nmi-mta` (the `nmi-auth` volume was created with another owner):

```bash
docker compose exec -u root app chown dev:www-data /var/www/storage/app/nmi-mta
```

### Mail does not come from the dedicated IP

The mailbox form says "The MTA does not own this address" and the MTA log has `dedicated IP … is not an address of this MTA`. The address is not configured on the server, the MTA is not running on the server's network, or a translated address is missing from `NMI_BIND_MAP`. See [Sending from dedicated IPs](#sending-from-dedicated-ips). The addresses the MTA can send from are in its log at start (`can send from: …`).

### The DKIM record does not verify

Look the record up yourself: `dig +short TXT <selector>._domainkey.<domain>`. The value must contain the whole `p=` key from the form (DNS panels split long values into several strings, which is fine). Some panels append the domain to the name: enter only `<selector>._domainkey` there.

### Mail is accepted but never arrives

`docker logs netsendo-mta` shows every delivery attempt. Timeouts on connecting to the recipients' servers mean outbound port 25 is blocked by the hosting provider (see [What the server needs](#what-the-server-needs)).

### DKIM verification failing

1. Ensure DNS record is published
2. Wait for DNS propagation (up to 48h)
3. Verify selector matches generated value

---

## API Endpoints

| Endpoint                                 | Method     | Description      |
| ---------------------------------------- | ---------- | ---------------- |
| `/settings/nmi`                          | GET        | Dashboard        |
| `/settings/nmi/pools`                    | GET/POST   | Pool management  |
| `/settings/nmi/pools/{id}`               | GET/DELETE | Pool details     |
| `/settings/nmi/ips/{id}`                 | GET        | IP details       |
| `/settings/nmi/ips/{id}/warming/start`   | POST       | Start warming    |
| `/settings/nmi/ips/{id}/dkim/generate`   | POST       | Generate DKIM    |
| `/settings/nmi/ips/{id}/blacklist/check` | POST       | Check blacklists |
| `/settings/nmi/mta/status`               | GET        | Whether the MTA answers, and the addresses it can send from |
| `/settings/nmi/domains/{id}/dkim`        | GET        | DKIM record of a domain and whether it is verified |
| `/settings/nmi/domains/{id}/dkim/generate` | POST     | Generate the domain's DKIM key (GOLD) |
| `/settings/nmi/domains/{id}/dkim/verify` | POST       | Look the DKIM record up in DNS |

---

## Testing the MTA

`docker/haraka/test/e2e.sh` builds the MTA image, starts it with three addresses next to a receiving mail server (`docker/haraka/test/sink.py`) and runs the two end-to-end tests of `tests/Feature/NmiMailboxTest.php` that the normal suite skips: login and acceptance, and source address, HELO name and DKIM verification at the receiver. Nothing leaves the test network.

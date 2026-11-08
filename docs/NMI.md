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

### 5. Set up a NetSendo MTA mailbox (GOLD)

1. **Domain** — *Deliverability Shield → Add domain*, publish the CNAME record and verify the domain. On the domain page, in the **NetSendo MTA (NMI)** card, click **Switch on NetSendo MTA**. NetSendo generates the domain's login on the MTA (the password is stored encrypted and never shown).
2. **Dedicated IP** — *Mail Infrastructure → pool → Add IP*, open the IP and, under **Sending domain**, assign it to the domain (only your own domains are offered). Click **Generate DKIM keys** and publish the TXT record shown under `<selector>._domainkey.<domain>`; **Verify DKIM** checks it.
3. **Mailbox** — *Settings → Mailboxes → Add Mailbox → NetSendo MTA*, choose the domain and the IP. The sender address must be on the domain or one of its subdomains, so the DKIM signature (`d=` the domain) aligns.

The mailbox is refused while the domain is not verified, NMI is off for it, the IP belongs to another domain or has no DKIM keys. An IP a mailbox sends through cannot be moved, removed from its domain or deleted, and NMI cannot be switched off for a domain whose mailboxes use it. Without GOLD the setup stays readable, existing NMI mailboxes keep working and can be edited or switched off; setting NMI up for a new mailbox, assigning IPs and switching NMI on need GOLD.

### How NetSendo logs in to the MTA

Each domain with NMI on has its own login (`nmi-<domain id>-<8 characters>` and a 40-character random password, stored encrypted in the database). NetSendo writes the logins to `storage/nmi/mta-users.json`, in Docker the `nmi-auth` volume, mounted read-only into `nmi-mta`:

```json
{"version": 1, "helo": "netsendo.example.com",
 "users": {"nmi-3-k2j4h5g6": {"sha256": "<SHA-256 of the password>", "domain": "example.com",
                              "ips": {"203.0.113.10": "mail1.example.com"}}}}
```

`helo` and `ips` tell the MTA how to send (next sections). The file is rewritten when NMI is switched on or off for a domain, a domain is removed, an IP is assigned, renamed or removed, and every 5 minutes by `php artisan nmi:sync-mta-auth` (scheduler). The Haraka plugin `netsendo_auth` (`docker/haraka/plugins/netsendo_auth.js`) reads it at every `AUTH`, so changes need no restart. `AUTH` is offered only to connections from the private Docker network or over TLS. Only a logged-in session may relay; every other outside recipient gets `550`.

### Delivery

The MTA delivers straight to the recipients' mail servers (Haraka outbound, `docker/haraka/config/outbound.ini`): MX lookup, STARTTLS when the receiving server offers it, temporary failures retried for about 40 hours, then a bounce to the sender (the Return-Path, i.e. the bounce mailbox when bounce monitoring is on). Every login, delivery and bounce is in `docker logs netsendo-mta`.

- **Outgoing port 25 must be open.** Many cloud providers block it (Google Compute Engine always does). The Mail Infrastructure dashboard shows *Port 25 open / blocked*: a TCP connection to `NMI_PORT25_CHECK_HOST` (default `gmail-smtp-in.l.google.com`; empty switches the check off), nothing is sent, the answer is kept for 15 minutes.

### The name of the mail server

NetSendo decides the name the MTA gives itself to the receiving servers (HELO) and passes it in the login file; nothing is set in the MTA and no second subdomain is needed:

- **From the server's main address:** the panel's domain, the host of `APP_URL` (e.g. `netsendo.example.com`). `NMI_HOSTNAME` overrides it. With `APP_URL` on `localhost` or on an IP address there is no name and the MTA uses its container's name.
- **From a dedicated IP:** the name of the IP, set when the IP is added and changeable on the IP page (*Sending from this IP*).

Receiving servers (Gmail, Outlook…) look up the **PTR record** of the address mail comes from and expect it to point to this name, and the name to point back to the address. The PTR record belongs to whoever owns the address, so it is set **at the provider of the server** (in its panel or by a support request), not in the domain's DNS. Point the PTR record of the server's main address to the panel's domain: its A record already points to the server.

The Mail Infrastructure dashboard shows the name and whether a PTR record points back to it (*PTR record OK / missing*, with the addresses and their current PTR records). The IP page checks the IP's own record (**Check PTR**: PTR of the IP = its name, and an A record of the name = the IP) and lists the two records to set. If the panel's domain goes through the Cloudflare proxy, it resolves to Cloudflare's addresses: set `NMI_HOSTNAME` to a name without the proxy whose A record points to the server.

### Sending from dedicated IPs

A message names the IP of its mailbox in the `X-NMI-IP` header (`NmiProvider`, the connection test too). The Haraka plugin `netsendo_outbound` (`docker/haraka/plugins/netsendo_outbound.js`) sends it **from that IP, under the IP's name**, when the IP

1. is one of the IPs of the logged-in domain (the login file lists them), and
2. is configured on the server: an address of a network interface the MTA sees, and
3. is an IPv4 address (the MTA delivers over IPv4).

Otherwise the message leaves from the server's main address under the main name; `docker logs netsendo-mta` says which and why for every message (`sending from 203.0.113.10 as mail1.example.com`). The plugin lists the addresses it can send from in its EHLO answer to clients on the private network (`X-NETSENDO-ADDRESS`); the IP page shows whether the MTA has the IP.

On Docker's bridge network (the default) the MTA sees only its container address, so all mail leaves from the server's main address. To send from dedicated IPs:

1. **Add the IPs to the server** as your provider describes (additional / failover IPs on the network interface). `ip -4 addr` on the server lists them.
2. **Give each IP its records:** an A record `mail1.example.com → 203.0.113.10` in the domain's DNS and the PTR record `203.0.113.10 → mail1.example.com` at the provider. **Check PTR** on the IP page confirms both.
3. **Run the MTA on the server's own network.** Next to `docker-compose.yml` create `docker-compose.override.yml` (Docker Compose reads it on its own; `!reset` needs version 2.24.4 or newer):

   ```yaml
   services:
     nmi-mta:
       # The server's own network: the MTA sees every address of the server
       network_mode: host
       networks: !reset []
       ports: !reset []
       healthcheck:
         test: ["CMD-SHELL", "nc -z 172.17.0.1 2525 || exit 1"]
     # The containers that send reach the MTA on the server
     app:
       extra_hosts: ["host.docker.internal:host-gateway"]
     queue:
       extra_hosts: ["host.docker.internal:host-gateway"]
     scheduler:
       extra_hosts: ["host.docker.internal:host-gateway"]
   ```

   and in `.env`:

   ```bash
   # The MTA listens only on Docker's address of the server, not on the internet
   NMI_MTA_LISTEN=172.17.0.1:2525
   NMI_MTA_HOST=host.docker.internal
   NMI_MTA_PORT=2525
   ```

   `172.17.0.1` is the address of Docker's `docker0` bridge, which `host-gateway` points to (`ip -4 addr show docker0` shows it if it differs). A firewall on the server (ufw, firewalld) has to let the containers in: e.g. `ufw allow from 10.58.0.0/16 to 172.17.0.1 port 2525 proto tcp` (`10.58.0.0/16` is `netsendo-network`).
4. `docker compose --profile nmi up -d`, then open the IP page: *The mail server has this address* means mail of its mailboxes leaves from it.

Several NetSendo installations on one server each need their own `NMI_MTA_LISTEN` port.

### The MTA image

`docker-compose.yml` uses `ghcr.io/netsendo/netsendo-mta:${NETSENDO_VERSION}`, built from `docker/haraka` with the same tags as the NetSendo image. `docker-compose.dev.yml` builds it from the directory and mounts `docker/haraka/config`.

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

| Volume      | Purpose             |
| ----------- | ------------------- |
| `nmi_queue` | Email queue storage |
| `nmi_logs`  | MTA log files       |
| `nmi_dkim`  | DKIM private keys   |
| `nmi_tls`   | TLS certificates    |
| `nmi_auth`  | MTA logins written by NetSendo (hashes) |

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
| `NMI_MTA_ENCRYPTION` | `none`         | TLS mode (none/starttls/tls) |
| `NMI_HOSTNAME`       | (empty)        | Name the MTA gives itself from the server's main address; empty: the host of `APP_URL` |
| `NMI_MTA_LISTEN`     | (empty)        | Addresses the MTA listens on (`address:port,…`), for the MTA on the server's network; empty: `[::]:25,[::]:587` |
| `NMI_PORT25_CHECK_HOST` | `gmail-smtp-in.l.google.com` | Server the dashboard connects to on port 25 (empty: no check) |
| `NMI_MTA_AUTH_FILE`  | `storage/nmi/mta-users.json` | MTA logins written by NetSendo |

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
| `/settings/nmi/ips/{id}/domain`          | POST       | Assign the IP to a domain (`domain_configuration_id`) |
| `/settings/nmi/ips/{id}/domain`          | DELETE     | Remove the IP from its domain |
| `/settings/nmi/ips/{id}`                 | PATCH      | Rename the IP (`hostname`: its HELO name and PTR target) |
| `/settings/nmi/ips/{id}/ptr/verify`      | POST       | Check the IP's PTR record and the A record of its name |
| `/settings/nmi/mta/status`               | GET        | MTA online, port 25, the addresses the MTA can send from, the server name and its PTR record |
| `/settings/nmi/domains/{id}/enable`      | POST       | Switch NMI on for a verified domain |
| `/settings/nmi/domains/{id}/disable`     | POST       | Switch NMI off for a domain |

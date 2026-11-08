# NetSendo Mail Infrastructure (NMI): your own sending server

NetSendo MTA sends your mail itself, straight to the recipients' mail servers, from your server's addresses and under your names, with DKIM signing, IP warming and blacklist monitoring. No SMTP relay or API provider is involved. It is a GOLD feature.

It is set up and run from the panel: *Mail Infrastructure* has a step-by-step guide in which every step checks itself. Nothing is edited on the server for it; the sending server (Haraka) ships in `docker-compose.yml` and starts with NetSendo.

**What you need**

- NetSendo with a GOLD licence, installed with the current `docker-compose.yml`.
- A server whose provider lets **outgoing port 25** through. Many cloud providers block it; Google Cloud always does. See [Choosing a server provider](#choosing-a-server-provider).
- Access to the **PTR record** of the server's address (set at the provider) and to the DNS of your sending domain.
- Optional: **additional IPv4 addresses** from the same provider, to send each domain or mailbox from its own address.

## Choosing a server provider

A provider fits when it

1. lets outgoing port 25 through (open, or opened on request),
2. sells additional IPv4 addresses for an existing server, from which outgoing traffic can leave, and
3. lets you set the PTR record of each address.

From the providers' own documentation, terms and price pages, as of 2026-11-08. Terms and prices change: check them with the provider before you buy. "Not stated" means the provider's pages do not say; ask its support.

| Provider | Port 25 out | Additional IPv4 | PTR record | Notes |
| --- | --- | --- | --- | --- |
| **OVHcloud** VPS, Dedicated | Open (blocked in Public Cloud, and automatically when spam is detected) | Additional IP, €1.99 a month; up to 16 per VPS, 256 per dedicated server | Panel and API | The only provider whose docs say port 25 is open by default; [guide for mail senders](https://docs.ovhcloud.com/en/guides/bare-metal-cloud/dedicated-servers/mail-sending-optimization) |
| **Scaleway** Elastic Metal, Instances | You enable it in the console after an identity check | Flexible IP, €0.005 an hour; up to 5 per Instance, 64 on Elastic Metal | Console | PTR included |
| **Hetzner** Dedicated (Robot) | Not stated | Additional IP, €1.70 a month + €4.90 once; needs a technical reason | Robot and API | Ask support about port 25 before buying |
| **Hetzner Cloud** | Blocked; request after a month and a first paid invoice, case by case | Floating IP, €3.00 a month | Console and API | Floating IPs need configuring on the server |
| **netcup** root servers, VPS | Not stated | Additional IPv4, €2.00 incl. VAT | Panel and API | Routed to the main address |
| **Akamai (Linode)** | Request with your details, use case and domains | Needs a justification; price by region | Cloud Manager and API | Explicitly allows opt-in marketing mail |
| **UpCloud** | Request with proof of identity or payment | Up to 5 IPv4 | Panel and API | Each address is a separate interface: routing to configure |
| **Vultr** | Request describing use and volume; not guaranteed | Additional IPv4 (price not stated) | Console and API | Adding an address restarts the server |
| **IONOS** VPS, Cloud Server | Opened by phone once the server has a name, PTR and SPF | €5 a month incl. VAT (DE) | Panel, when the address is created | |
| **home.pl** VPS Linux | Not stated | 3–31 additional IPv4 by plan (price not stated) | Customer panel | |
| **AWS** EC2 | Request form, per region | Elastic IP, $0.005 an hour | Console and API | The AWS policy bans unsolicited mass email |
| **Contabo** VPS, VDS | Not stated | Additional IPv4, €3.50 a month; 1 on a VPS | Panel and API | About 25 emails a minute: not for bulk mail |
| **nazwa.pl** VPS | Not stated | None | Paid add-on, 40 PLN a month | |
| **DigitalOcean** | Blocked on all Droplets, no exceptions | Reserved IP, no PTR | None | Not for mail |
| **Google Cloud** Compute Engine | Blocked, no request process | | Primary interface | Not for mail: a sending server there cannot deliver |
| **Microsoft Azure** | Blocked except Enterprise (EA, MCA-E) subscriptions | Public IP | CLI / PowerShell only | Not for mail on standard subscriptions |

The same comparison, with links to each provider's instructions, is in the panel (*Mail Infrastructure → Where to get a server and IP addresses*). When you add an IP, choose its provider: the IP page then links to that provider's guides for adding the address to the server and setting its PTR record.

## Step by step

The guide on the *Mail Infrastructure* dashboard shows these steps with their live state (done, to do, to fix, blocks sending). **Check again** runs the checks once more.

1. **Switch the sending server on.** An admin of the account clicks **Switch the sending server on** (GOLD). Until then the MTA relays for nobody. *Switch off* takes all logins away from the MTA at once; domains, IPs and mailboxes stay. `NMI_ENABLED=true` in `.env`, as set up before the switch existed, keeps it on.
2. **The sending server is running.** NetSendo connects to it at `netsendo-mta:2525`. If it does not answer: in the NetSendo folder on the server take the current `docker-compose.yml` and run `docker compose pull && docker compose up -d`; with the ufw firewall active also `ufw allow from 10.58.0.0/16 to 10.58.0.1 port 2525 proto tcp`.
3. **Port 25 is open.** NetSendo connects to Gmail's mail server on port 25 (nothing is sent). Blocked: ask the provider to open it, or move to a provider that allows it.
4. **The server's name and PTR record.** The server introduces itself with the panel's domain (the host of `APP_URL`, or `NMI_HOSTNAME`). At the provider, set the PTR record of the server's main address to that name. No second subdomain is needed: the panel's domain already points to the server.
5. **The sender's domain.** *Deliverability Shield → Add domain*, confirm it with the CNAME record, then on the domain page, card **NetSendo MTA (NMI)**, **Switch on NetSendo MTA**. NetSendo creates the domain's login on the MTA.
6. **An IP address.** *Mail Infrastructure → pool → Add IP* with its name (e.g. `mail1.example.com`) and its provider, then on the IP page under **Sending domain** assign it to the domain. No additional addresses? Add the server's main address: the guide lists the server's public addresses.
7. **DKIM.** On the IP page **Generate DKIM keys** and add the TXT record shown (`<selector>._domainkey.<domain>`) to the domain's DNS; **Verify DKIM** checks it.
8. **The IP is on the server.** The MTA sends from an IP only when the server has it. Add an additional address to the server as your provider describes (the IP page links to the guide). Until then its mail leaves from the server's main address.
9. **PTR records of the addresses.** Each IP needs an A record `mail1.example.com → 203.0.113.10` in the domain's DNS and the PTR record `203.0.113.10 → mail1.example.com` at the provider. **Check PTR** on the IP page checks both.
10. **A mailbox.** *Settings → Mailboxes → Add → NetSendo MTA*, choose the domain and the IP. The sender address must be on the domain or a subdomain, so the DKIM signature aligns.
11. **A test.** **Test Connection** on the mailbox, then a message to the address mail-tester.com gives you: it shows whether SPF, DKIM and PTR are right. Start with small volumes and **Start warming** on the IP page.

The mailbox is refused while the domain is not verified, NMI is off for it, the IP belongs to another domain or has no DKIM keys. An IP a mailbox sends through cannot be moved, removed from its domain or deleted, and NMI cannot be switched off for a domain whose mailboxes use it. Without GOLD the setup stays readable, existing NMI mailboxes keep working and can be edited or switched off; switching the sending server on, setting NMI up for a new mailbox, assigning IPs and switching NMI on for a domain need GOLD.

## How it works

### The sending server in Docker

`docker-compose.yml` runs `nmi-mta` (image `ghcr.io/netsendo/netsendo-mta`, the same version as NetSendo) with every installation:

- **On the server's own network** (`network_mode: host`), so it sees every address of the server and can send from each.
- **Listening only on `10.58.0.1:2525`**, the gateway of `netsendo-network` (`NMI_MTA_LISTEN`). The app, queue and scheduler containers reach it there under the name `netsendo-mta` (`extra_hosts`); it is not reachable from the internet. An active ufw must let the containers in: `install.sh` adds `ufw allow from 10.58.0.0/16 to 10.58.0.1 port 2525 proto tcp` when ufw is active.
- **One small process** (about 35 MB of memory) that relays for nobody until the sending server is switched on in the panel.

An `.env` from an older `.env.example` with `NMI_MTA_HOST=netsendo-mta` and `NMI_MTA_PORT=25` keeps working: NetSendo uses port 2525 for that name. `NMI_MTA_HOST` / `NMI_MTA_PORT` point elsewhere only for an MTA set up another way. If you change the subnet of `netsendo-network`, change `NMI_MTA_LISTEN` and the `extra_hosts` entries with it. Several NetSendo installations on one server need their own subnets and listen addresses.

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

Because the MTA runs on the server's network, an additional address works as soon as it is on the server: add it as your provider describes (`ip -4 addr` on the server lists the addresses), set its A and PTR records, and the IP page shows *The mail server has this address*. Nothing in NetSendo or Docker has to change.

### The MTA image

`docker-compose.yml` uses `ghcr.io/netsendo/netsendo-mta:${NETSENDO_VERSION}`, built from `docker/haraka` with the same tags as the NetSendo image. `docker-compose.dev.yml` builds it from the directory, mounts `docker/haraka/config` and keeps it on the Docker network (as `netsendo-mta`, port 2525).

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

NMI uses the Haraka MTA in the `nmi-mta` container, which starts with NetSendo.

```bash
# Start or update NetSendo, the sending server included
docker compose pull && docker compose up -d

# View MTA logs: every login, the address and name of each message, deliveries and bounces
docker logs netsendo-mta
```

Development: `docker compose -f docker-compose.dev.yml up -d` builds the MTA from `docker/haraka`.

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

The sending server is switched on and off in the panel (admin, GOLD to switch on).

| Variable      | Default | Description               |
| ------------- | ------- | ------------------------- |
| `NMI_ENABLED` | (empty) | `true` keeps NetSendo MTA on regardless of the panel; `false` or empty: the panel decides |

### MTA Connection

| Variable             | Default        | Description                  |
| -------------------- | -------------- | ---------------------------- |
| `NMI_MTA_HOST`       | `netsendo-mta` | Where NetSendo hands mail to the MTA (`netsendo-mta` is `10.58.0.1` in the containers) |
| `NMI_MTA_PORT`       | `2525`         | Its port (`25` with `netsendo-mta`, from an older `.env`, means `2525`) |
| `NMI_MTA_ENCRYPTION` | `none`         | TLS mode (none/starttls/tls) |
| `NMI_HOSTNAME`       | (empty)        | Name the MTA gives itself from the server's main address; empty: the host of `APP_URL` |
| `NMI_MTA_LISTEN`     | `10.58.0.1:2525` | Addresses the MTA listens on (`address:port,…`); `docker-compose.yml` sets the gateway of `netsendo-network`; without it the image listens on `[::]:25,[::]:587,[::]:2525` |
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

## Upgrading

1. Take the current `docker-compose.yml` (the `nmi-mta` service now starts with NetSendo on the server's network, and the app, queue and scheduler know it as `netsendo-mta`).
2. `docker compose pull && docker compose up -d`. The migrations run on start.
3. Open *Mail Infrastructure* and follow the guide.

Nothing in `.env` has to change. `NMI_ENABLED=true` from an earlier setup keeps the sending server on; remove it to switch the server from the panel.

---

## Troubleshooting

### The guide says the sending server does not answer

1. `docker compose ps nmi-mta` shows whether it runs; `docker logs netsendo-mta` why not.
2. An older `docker-compose.yml` has no `nmi-mta` on the server's network: take the current one and `docker compose up -d`.
3. ufw active: `ufw allow from 10.58.0.0/16 to 10.58.0.1 port 2525 proto tcp`. Other firewalls need the same rule.
4. `Listening on 10.58.0.1:2525` missing in the log and an error about the address: `netsendo-network` has another subnet; set `NMI_MTA_LISTEN` and the `extra_hosts` entries to its gateway.

### Port 25 blocked

The provider blocks outgoing port 25. Ask it to open it (some do after a request), or move to a provider that allows it. Until then nothing can be delivered.

### Mail leaves from the main address instead of the dedicated IP

`docker logs netsendo-mta` says why for each message:

- *not configured on this server*: the address is not on the server's network interface; add it as the provider describes.
- *not an IP of … domain*: the mailbox's IP belongs to another domain.
- *IPv6*: the MTA delivers over IPv4; use an IPv4 address.

### PTR record does not match

The PTR record is set at the provider the address belongs to, not in the domain's DNS. After changing it, **Check PTR** on the IP page (the answer may take a few minutes to change).

### DKIM verification failing

1. Ensure the DNS record is published
2. Wait for DNS propagation (up to 48h)
3. Verify the selector matches the generated value

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
| `/settings/nmi/ips/{id}`                 | PATCH      | Rename the IP (`hostname`: its HELO name and PTR target) or change its `provider` |
| `/settings/nmi/ips/{id}/ptr/verify`      | POST       | Check the IP's PTR record and the A record of its name |
| `/settings/nmi/mta/status`               | GET        | MTA online, port 25, the addresses the MTA can send from, the server name and its PTR record |
| `/settings/nmi/guide`                    | GET        | The setup guide: each step with its state (`ok`, `todo`, `warning`, `error`, `unknown`, `info`) |
| `/settings/nmi/installation/enable`      | POST       | Switch the sending server on (admin, GOLD) |
| `/settings/nmi/installation/disable`     | POST       | Switch the sending server off (admin) |
| `/settings/nmi/domains/{id}/enable`      | POST       | Switch NMI on for a verified domain |
| `/settings/nmi/domains/{id}/disable`     | POST       | Switch NMI off for a domain |

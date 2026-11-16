# NetSendo Mail Infrastructure (NMI)

Professional email sending infrastructure with dedicated IP management, intelligent IP warming, DKIM signing, and real-time blacklist monitoring.

## Quick Start

Since 2.11.0 the MTA (the `nmi-mta` service of `docker-compose.yml`) starts with every installation. It relays nothing until a **GOLD** installation saves a mailbox with the **NetSendo MTA** provider: only sessions that log in with a login NetSendo issued may send through it.

### 1. Update

Take the `docker-compose.yml` of 2.11.0 or newer (it has the `nmi-mta` service) and run:

```bash
docker compose pull && docker compose up -d
```

The MTA image is published with the app: `ghcr.io/netsendo/netsendo:mta-<version>` (`mta-latest`). Migrations run when the app starts.

### 2. Check that this server can send

Open **Settings → Mailboxes → Add mailbox** and choose **NetSendo MTA** (or **Settings → Mail Infrastructure**). The box **Can this server send mail** shows what the MTA found out by itself:

- **Outbound port 25**: open or blocked by the server's provider;
- **Server address**: the public address receivers see, and whether its **PTR** record names a host that resolves back to it;
- **SPF**: the entry (`ip4:…`) to add to the sending domain's SPF record.

Fix what it marks: [Port 25, PTR and SPF](#port-25-ptr-and-spf) has the steps for common providers.

### 3. Add a mailbox that sends through NMI

1. Add your sending domain in **Deliverability** and verify it (the CNAME record).
2. Open **Settings → Mailboxes → Add mailbox** and choose **NetSendo MTA**. The provider is offered on a GOLD installation while the MTA container answers (the form shows its status and a *Check again* link).
3. Choose the verified domain. The sender address must be in that domain.
4. Optionally choose a dedicated IP from your pools. A free IP is assigned to the domain when you save. The form says whether the MTA really sends from that address (see [Sending from dedicated IPs](#sending-from-dedicated-ips)).
5. Save. Open the mailbox again: the **DKIM signature** block shows the TXT record of the domain's key. Publish it in the domain's DNS and click **Verify record**. Mail is signed from then on.
6. Use **Test connection**: the MTA must accept the test message. The result says if the message went out unsigned or not from the dedicated IP.

`NMI_DISABLED=true` in `.env` hides the provider and stops NMI's scheduled jobs.

There is no login to type in. See [Sending through NMI](#sending-through-nmi) for what happens behind the form.

---

## Port 25, PTR and SPF

Receiving servers (Gmail, Outlook, Yahoo, WP, Onet…) decide about mail from an address by what they can check. Without these three, mail from the MTA is refused or ends up in spam. The box **Can this server send mail** in the mailbox form shows each of them for your server.

### 1. Outbound port 25

The MTA delivers straight to the recipients' mail servers, on port 25. Many providers block that port for new servers. Where it is blocked the MTA accepts mail and then cannot deliver it (the queue grows, `docker logs netsendo-mta` shows connection timeouts).

The MTA checks the port itself at start and every few hours. You can also test it on the server (a line starting with `220` means the port is open):

```bash
timeout 10 bash -c 'exec 3<>/dev/tcp/gmail-smtp-in.l.google.com/25 && head -1 <&3' || echo "port 25 is blocked"
```

What the common providers do (their rules change, check their current documentation):

| Provider | Outbound port 25 |
| -------- | ---------------- |
| Google Cloud | Blocked for most projects. Google lifts the block per project on its own once it considers the project low-risk; the *VPC networks* page shows the state. |
| AWS (EC2) | Limited by default. Ask for the limit to be removed with the form *Request to remove email sending limitations*. |
| Microsoft Azure | Blocked for most subscription types. |
| DigitalOcean | Blocked on new accounts; a support ticket may lift it. |
| Hetzner Cloud | Ports 25 and 465 blocked on new accounts; ask the support to lift it (usually after the first paid invoice). |
| Vultr | Blocked on new accounts; open a support ticket. |
| Linode / Akamai Cloud | Restricted on new accounts; open a support ticket. |
| OVHcloud, Contabo, most dedicated servers | Usually open. |

When you ask for the port to be opened, say what you send (newsletters to people who signed up), that SPF and DKIM are set, and the expected volume.

If the port stays blocked, send through another service's SMTP (a mailbox with the SMTP or SendGrid provider) instead.

### 2. PTR (reverse DNS)

A PTR record maps the server's address back to a host name, and that host name must resolve to the same address again ("forward-confirmed"). The MTA introduces itself with that name (HELO). **The PTR is set at the company that gives you the address, not in your domain's DNS.**

The MTA finds the name itself: when the PTR of the server's address names a host that resolves back to the address, it uses it in HELO and the box shows *PTR: name*. Many providers give every address such a name by default (Google Cloud: `x.x.x.x.bc.googleusercontent.com`), and that is enough to send. A name in your own domain looks better to the receivers; to set one:

1. Choose a host name in a domain you own, for example `mail.yourdomain.com`, and create its **A record** pointing to the server's address (AAAA for IPv6).
2. Set the address's **PTR** to that name in the provider's panel:

   | Provider | Where |
   | -------- | ----- |
   | Hetzner Cloud | Cloud Console → the server → *Networking* → the address → *Edit Reverse DNS*. Dedicated servers: Robot → *IPs*. |
   | OVHcloud | Control panel → *Bare Metal Cloud* → *Network* → *IP* → `…` next to the address → *Modify the reverse path*. |
   | DigitalOcean | The PTR follows the Droplet's name: rename the Droplet to the full host name. |
   | Vultr | The instance → *Settings* → *IPv4* → *Reverse DNS*. |
   | Linode / Akamai Cloud | The Linode → *Network* → the address → *Edit RDNS* (the A record must already resolve). |
   | AWS | *Elastic IPs* → the address → *Actions* → *Update reverse DNS* (the A record must already resolve). |
   | Google Cloud | `gcloud compute instances update-access-config <VM> --zone <zone> --network-interface nic0 --access-config-name "External NAT" --public-ptr-domain=mail.yourdomain.com.` The domain must be verified in Google Search Console by the account that runs the command. |
   | Contabo | Customer panel → *Reverse DNS management*. |

3. Wait for DNS (minutes to an hour) and click *Check again* in the box. To force a name, set `NMI_HELO_HOST=mail.yourdomain.com` in `.env` and run `docker compose up -d`.

Several installations on one server share its address and so its PTR name and its reputation with the receivers. Each sending domain still needs the address in its SPF record and its own DKIM key.

### 3. SPF

The sending domain's SPF record (a TXT record at the domain itself) must list the address the MTA sends from: the server's address, or the mailbox's dedicated IP. The box shows the entry, for example `ip4:203.0.113.10`.

- The domain has an SPF record already (it starts with `v=spf1`): put the entry before its ending, `~all` or `-all`:

  ```
  v=spf1 include:_spf.google.com ip4:203.0.113.10 ~all
  ```

- It has none: create one:

  ```
  v=spf1 ip4:203.0.113.10 ~all
  ```

A domain may have **one** SPF record only; two make both invalid. **Deliverability → the domain** checks the record (it warns when the address is missing) and shows the complete record to publish.

### 4. DKIM and DMARC

- **DKIM**: each domain gets its own key when its first NetSendo MTA mailbox is saved; the mailbox form shows the TXT record to publish ([DKIM](#dkim)).
- **DMARC**: Deliverability → the domain shows the record (`_dmarc.yourdomain.com`), starting with `p=none`.

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
- Removing a domain removes its login. `php artisan nmi:sync-mta-auth` rewrites the file from the database (scheduled every ten minutes unless `NMI_DISABLED=true`), which restores it after the volume was recreated.

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
   docker compose -f docker-compose.yml -f docker-compose.nmi-host.yml up -d
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

See [Getting dedicated IPs](#getting-dedicated-ips) for where the addresses come from.

`NMI_HELO_HOST` sets the HELO name of mail without a dedicated IP. Empty, the MTA uses the PTR name of the server's public address when that name resolves back to the address ([PTR](#2-ptr-reverse-dns)), else the container's host name, which other servers cannot resolve.

Not every host can do this. A Google Cloud VM, for example, has one external address per network interface, so one such VM cannot send from several dedicated IPs.

### Getting dedicated IPs

A dedicated IP comes from the company that hosts the server: **an address can only be used by a server at that same company.** The server NetSendo runs on (with the MTA) has to be one of your servers there. *Settings → Mail Infrastructure → Configure Providers* shows these instructions next to each provider.

Whatever the route, an address needs four things before mail should leave from it:

1. a **host name** (for example `mail2.yourdomain.com`) with an A record pointing to the address;
2. **reverse DNS (PTR)** of the address pointing back to that host name;
3. the address **configured on the server**;
4. the **MTA on the server's network**, so it owns the address ([Sending from dedicated IPs](#sending-from-dedicated-ips)).

The address's page checks 1 and 2 (*Reverse DNS → Check*); the mailbox form shows 3 and 4 ("The MTA sends from this address").

#### Vultr (through the API)

1. Your NetSendo server is a Vultr instance.
2. In the Vultr panel: *Account → API*, enable the API and copy the key. If access is limited to certain addresses, add the server's address.
3. *Configure Providers → Vultr*: paste the key and click *Connect*. NetSendo asks Vultr whose key it is and stores it (encrypted) only when Vultr accepts it.
4. On a pool click *Provision IP*: choose Vultr, the server NetSendo runs on, and the host name of the new address. NetSendo reserves an address in the server's region, attaches it to the server and sets its reverse DNS.
5. Configure the address on the server: Vultr does not do that for you. Add `<address>/32` to the network interface (the Vultr panel generates the netplan file under the instance's *Settings → IPv4*), apply it and restart the server.
6. Vultr blocks outbound port 25 on new accounts: open a support ticket there (what you send, SPF and DKIM, expected volume).

An address costs about 3 USD a month, billed by Vultr until it is deleted.

#### Linode / Akamai Cloud (through the API)

1. Your NetSendo server is a Linode.
2. **Ask Akamai's support for additional IPv4 addresses first**: a ticket with a technical justification (a dedicated sending address for email). Without their approval the order is refused with "Additional IPv4 addresses require technical justification", which NetSendo shows as it is.
3. In Cloud Manager: profile → *API Tokens*, create a Personal Access Token with the scope **Linodes: Read/Write**.
4. *Configure Providers → Linode*: paste the token and click *Connect*.
5. On a pool click *Provision IP*: choose Linode, the server and the host name. The server list marks servers on which outbound SMTP is restricted (newer accounts; a support ticket lifts it).
6. Create the A record, then on the address's page click *Set reverse DNS*: Akamai accepts reverse DNS only once the host name already resolves to the address.
7. Reboot the Linode: Network Helper configures the address at boot. With Network Helper off, add `<address>/24` to the interface by hand.

Akamai bills each additional address by the hour until it is deleted.

#### DigitalOcean: not possible

DigitalOcean's Reserved IPs cannot send mail. Its documentation says SMTP ports 25, 465 and 587 are blocked on them, that they do not support PTR records, and that a Droplet can hold only one. Earlier versions of NetSendo could reserve such an address; that is gone, and an address reserved back then is given back to DigitalOcean when it is deleted in NetSendo.

#### Any other company (Hetzner, OVH, your own data centre…)

1. Order an additional IPv4 address for the server in the company's panel.
2. Configure it on the server the way that company describes.
3. Create the host name's A record and set the address's reverse DNS to the host name in the company's panel.
4. On a pool click *Add IP*: the address and its host name.
5. On the address's page click *Check* under Reverse DNS.

#### Deleting an address

*Delete address* on the address's page first gives an address ordered through the API back to the provider, so it is no longer billed. If the provider refuses, the address stays in NetSendo with the provider's reason; *Remove from NetSendo only* then removes it here and leaves it at the provider. An address that a mailbox sends through cannot be deleted.

### What the server needs

Outbound port 25, a PTR record of the sending address and that address in the domain's SPF record: [Port 25, PTR and SPF](#port-25-ptr-and-spf).

### GOLD

Setting NMI up needs GOLD: adding an NMI mailbox, switching a mailbox to NMI, changing its domain or IP. An NMI mailbox set up earlier stays editable and keeps sending without it.

---

## Docker Setup

NMI uses the Haraka MTA, running in its own container (`nmi-mta`, container `netsendo-mta`). It starts with the stack; the app reaches it on the Docker network as `nmi-mta:25`. Nothing is published on the server's ports.

### Production

```bash
# The image comes from the registry like the app's (ghcr.io/netsendo/netsendo:mta-<version>)
docker compose pull && docker compose up -d

# View MTA logs: delivery, port 25 check, the HELO name it uses
docker logs netsendo-mta
```

### Development

```bash
# Builds the MTA from docker/haraka, with the config and plugins mounted for editing
docker compose -f docker-compose.dev.yml up -d
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

Since 2.11.0 the MTA image is prebuilt and its configuration and plugins are part of it. Take the new `docker-compose.yml` (the `nmi-mta` service has `image:` instead of `build:` and no `profiles:`), then:

```bash
docker compose pull && docker compose up -d
```

`NMI_ENABLED` is no longer read: remove it from `.env`. `NMI_MTA_HOST=netsendo-mta` keeps working while the container keeps that name; the default is now the service name, `nmi-mta`.

---

## Configuration Reference

### Master Switch

| Variable       | Default | Description |
| -------------- | ------- | ----------- |
| `NMI_DISABLED` | `false` | `true` hides the NetSendo MTA provider and stops NMI's scheduled jobs. `NMI_ENABLED`, read before 2.11.0, is ignored. |

### MTA Connection

| Variable             | Default        | Description                  |
| -------------------- | -------------- | ---------------------------- |
| `NMI_MTA_HOST`       | `nmi-mta`      | Host name of the MTA on the Docker network (the compose service) |
| `NMI_MTA_PORT`       | `25`           | SMTP port                    |
| `NMI_MTA_ENCRYPTION` | `none`         | `none` (clear text on the Docker network), `tls` (require STARTTLS) or `ssl` (TLS from the start) |
| `NMI_MTA_AUTH_FILE`  | `storage/app/nmi-mta/auth.json` | Where the app writes the MTA logins; the MTA reads the same file (`NMI_AUTH_FILE` in its container) |
| `NMI_HELO_HOST`      | (the confirmed PTR name) | HELO name of the MTA for mail without a dedicated IP. Empty: the PTR name of the server's public address when it resolves back to it |
| `NMI_EGRESS_PROBE`   | `true`         | The MTA's check of outbound port 25, the public address and its PTR (an SMTP greeting with a Gmail MX, nothing sent). `false` turns it off; the panel then cannot say whether mail can leave |
| `NMI_EGRESS_PROBE_HOSTS` | (Gmail MX) | Servers the check talks to on port 25, comma-separated |
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
> **Upgrading to 2.11.0 or newer**

1. Take the `docker-compose.yml` of the new version: it adds the `nmi-mta` service and its volumes (`nmi-auth` is also mounted by `app`, `scheduler` and `queue`).
2. `docker compose pull && docker compose up -d`. The migrations run when the app starts.
3. Remove `NMI_ENABLED` from `.env` (no longer read). To keep NMI off, set `NMI_DISABLED=true`.
4. Open **Settings → Mailboxes → NetSendo MTA** and fix what *Can this server send mail* marks ([Port 25, PTR and SPF](#port-25-ptr-and-spf)).

---

## Troubleshooting

### NMI not showing in menu

1. `NMI_DISABLED` must not be `true` in `.env`
2. Clear cache: `php artisan optimize:clear`

### MTA container not starting

```bash
# Check logs
docker logs netsendo-mta

# Verify network
docker network ls | grep netsendo
```

### "NetSendo MTA" is not offered in the mailbox form

1. `NMI_DISABLED=true` in `.env` hides it.
2. The button is greyed out while the MTA does not answer. The `docker-compose.yml` must have the `nmi-mta` service (2.11.0 or newer, see [Migration Guide](#migration-guide)); then `docker compose up -d` and click *Check again*.
3. A GOLD badge on the button means the installation has no GOLD licence.

### Mail stays in the MTA's queue

The box *Can this server send mail* says why: port 25 blocked, or no PTR. `docker logs netsendo-mta` shows the delivery attempts. See [Port 25, PTR and SPF](#port-25-ptr-and-spf).

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

If the second command fails, the MTA container was created before the `nmi-auth` volume existed: `docker compose up -d --force-recreate nmi-mta`.

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
| `/settings/nmi/providers`                | GET/POST   | Connected IP providers; connect (the key is checked first) or disconnect (GOLD) |
| `/settings/nmi/providers/{provider}/servers` | GET    | The account's servers at a provider (GOLD) |
| `/settings/nmi/pools/{id}/provision`     | POST       | Order an address for a server: `provider`, `server_id`, `hostname` (GOLD) |
| `/settings/nmi/ips/{id}/ptr/check`       | POST       | Look the address's reverse DNS up |
| `/settings/nmi/ips/{id}/ptr/set`         | POST       | Set reverse DNS at the provider the address was ordered from (GOLD) |
| `/settings/nmi/ips/{id}`                 | DELETE     | Delete an address; an ordered one is released at the provider first |
| `/settings/nmi/domains/{id}/dkim`        | GET        | DKIM record of a domain and whether it is verified |
| `/settings/nmi/domains/{id}/dkim/generate` | POST     | Generate the domain's DKIM key (GOLD) |
| `/settings/nmi/domains/{id}/dkim/verify` | POST       | Look the DKIM record up in DNS |

---

## Testing the MTA

`docker/haraka/test/e2e.sh` builds the MTA image, starts it with three addresses next to a receiving mail server (`docker/haraka/test/sink.py`) and runs the two end-to-end tests of `tests/Feature/NmiMailboxTest.php` that the normal suite skips: login and acceptance, and source address, HELO name and DKIM verification at the receiver. Nothing leaves the test network.

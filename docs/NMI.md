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

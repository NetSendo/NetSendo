# 🐳 NetSendo Docker Installation Guide

Complete guide for deploying NetSendo using Docker.

---

## 📋 Prerequisites

| Requirement    | Minimum | Recommended |
| -------------- | ------- | ----------- |
| Docker         | 20.10+  | 24.0+       |
| Docker Compose | v2.0+   | v2.20+      |
| RAM            | 2GB     | 4GB         |
| Disk Space     | 5GB     | 10GB        |

---

## 🚀 Quick Start (Production)

### 1. Clone Repository

```bash
git clone https://github.com/NetSendo/NetSendo.git
cd NetSendo
```

### 2. Configure Environment

```bash
# Copy example configuration
cp .env.example .env

# Generate APP_KEY
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env

# Edit .env with your settings
nano .env
```

**Required settings in `.env`:**

```env
APP_KEY=base64:YOUR_GENERATED_KEY
APP_URL=http://localhost:5029

DB_DATABASE=netsendo_prod
DB_USERNAME=netsendo_user
DB_PASSWORD=YOUR_SECURE_PASSWORD   # CHANGE THIS!

# WebSocket Configuration (Required for real-time features)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=netsendo
REVERB_APP_KEY=netsendo-reverb-key
REVERB_APP_SECRET=netsendo-reverb-secret
REVERB_HOST=reverb
REVERB_PORT=8085
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8085
VITE_REVERB_SCHEME=http
```

### 3. Start Services

```bash
docker compose up -d
```

### 4. Access Application

| Service                | URL                   |
| ---------------------- | --------------------- |
| **NetSendo**           | http://localhost:5029 |
| **Mailpit**            | http://localhost:5031 |
| **Reverb (WebSocket)** | http://localhost:8085 |

Note: A background worker (`netsendo-scheduler`) is also started to handle scheduled emails and automation.

---

## 📧 NMI - NetSendo Mail Infrastructure (Optional)

NMI provides professional email sending with dedicated IPs, IP warming, DKIM, and blacklist monitoring.

### Enable NMI

**1. Add NMI variables to `.env`:**

```env
NMI_ENABLED=true
NMI_MTA_HOST=netsendo-mta
NMI_MTA_PORT=25
```

> See `.env.example` for all NMI configuration options.

**2. Start with NMI profile:**

```bash
docker compose --profile nmi up -d
```

**3. Access NMI Dashboard:**

Navigate to **Settings → Mail Infrastructure** in NetSendo UI.

### NMI Volumes

| Volume      | Purpose             |
| ----------- | ------------------- |
| `nmi_queue` | Email queue storage |
| `nmi_logs`  | MTA log files       |
| `nmi_dkim`  | DKIM private keys   |
| `nmi_tls`   | TLS certificates    |

> 📖 Full documentation: [docs/NMI.md](docs/NMI.md)

---

## 🔄 Updates

NetSendo supports the standard Docker update workflow:

```bash
# 1. Stop services
docker compose down

# 2. Pull latest images
docker compose pull

# 3. Start services
docker compose up -d
```

**That's it!** No manual cache clearing or rebuilding required.

### Update to Specific Version

```bash
NETSENDO_VERSION=1.1.0 docker compose up -d
```

### ⚠️ Manual Update Required (v1.6.9+)

If you manually maintain Docker configuration files (instead of using `git pull`), you need to apply these changes for **storage file serving** to work:

**1. Update `docker/nginx/default.conf`** - Add this location block:

```nginx
server {
    # ... existing config ...

    # Add this block to serve uploaded files
    location /storage {
        alias /var/www/storage/app/public;
        try_files $uri $uri/ =404;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # ... rest of config ...
}
```

**2. Update `docker-compose.yml`** - Add storage volume to webserver:

```yaml
webserver:
  volumes:
    - netsendo-public:/var/www/public:ro
    - netsendo-storage:/var/www/storage/app:ro # ADD THIS LINE
    - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
```

**3. Restart with recreate:**

```bash
docker compose down && docker compose up -d
```

---

## 📁 Data Persistence

| Volume             | Purpose                    | Backed Up?  |
| ------------------ | -------------------------- | ----------- |
| `dbdata`           | MySQL database             | ✅ Yes      |
| `redisdata`        | Redis cache                | ❌ No       |
| `netsendo-storage` | User uploads               | ✅ Yes      |
| `netsendo-logs`    | App logs                   | ❌ Optional |
| `netsendo-public`  | Static files (auto-synced) | ❌ No       |

### Backup Commands

```bash
# Database backup
docker compose exec db mysqldump -u root -p netsendo_prod > backup.sql

# User uploads backup
docker compose cp app:/var/www/storage/app ./backup-storage/
```

---

## 🔧 Configuration Reference

### Environment Variables

| Variable           | Default   | Description          |
| ------------------ | --------- | -------------------- |
| `NETSENDO_VERSION` | `latest`  | Docker image version |
| `HTTP_PORT`        | `5029`    | HTTP port            |
| `DB_PORT`          | `5030`    | MySQL port           |
| `DB_PASSWORD`      | `root`    | MySQL password       |
| `MAIL_HOST`        | `mailpit` | SMTP host            |

### Production with Reverse Proxy

For public deployments, use a reverse proxy (nginx/Caddy) with HTTPS:

```nginx
server {
    listen 443 ssl http2;
    server_name netsendo.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://127.0.0.1:5029;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket proxy for Reverb
    location /app/ {
        proxy_pass http://127.0.0.1:8085;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }
}
```

Update `.env`:

```env
APP_URL=https://netsendo.example.com

# Update WebSocket host for production
VITE_REVERB_HOST=netsendo.example.com
VITE_REVERB_SCHEME=https
```

> [!IMPORTANT]
> After changing `VITE_*` variables, rebuild the frontend:
>
> ```bash
> docker compose exec app npm run build
> ```

---

## 🛠️ Troubleshooting

### Container Won't Start

```bash
# Check logs
docker compose logs app

# Verify database is healthy
docker compose exec db mysqladmin ping -h localhost
```

### Permission Issues

```bash
# Fix storage permissions
docker compose exec app chmod -R 755 storage bootstrap/cache
```

### Clear Caches

```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan view:clear
```

### Full Reset (Caution: Deletes Data!)

```bash
docker compose down -v
docker compose up -d
```

### Unsubscribe Confirmations or New-Subscriber Notifications Not Sent

The *unsubscribed confirmation* email (system email `unsubscribed_confirmation`)
and the *new subscriber* notification are queued on the `notifications` queue.
Campaigns, autoresponders and the *Confirm your unsubscribe* email do not use
it, so they keep going out while these two never arrive.

**Cause:** the `queue` service runs a worker for the `default` queue only. Up to
2.1.3 `docker-compose.yml` started it with `php artisan queue:work` and no
`--queue`, so no worker ever read `notifications`.

**Solution:** the worker must listen to both queues, `default` first:

```yaml
  queue:
    command: php artisan queue:work --queue=default,notifications --sleep=3 --tries=3 --max-time=3600
```

`docker compose pull` does not change a `docker-compose.yml` you already have —
edit the `command` of the `queue` service, then `docker compose up -d queue`.
A supervisor or systemd worker outside Docker needs the same `--queue` option.

**Before restarting, check the backlog.** Jobs queued while no worker read
`notifications` are sent as soon as one does — confirmations to people who
unsubscribed weeks ago and a notification for every past signup:

```bash
# QUEUE_CONNECTION=database
docker compose exec app php artisan tinker --execute="echo DB::table('jobs')->where('queue', 'notifications')->count();"

# QUEUE_CONNECTION=redis (the key carries the Redis prefix)
docker compose exec redis redis-cli --scan --pattern '*queues:notifications*'
```

Up to 2.1.3 both listeners were pinned to the `database` connection, so with
`QUEUE_CONNECTION=redis` their jobs went to the `jobs` table, which the
entrypoint empties at every container start — nothing is left to send. With
`QUEUE_CONNECTION=database` they wait in `jobs`; delete them first if they
should not go out (`DB::table('jobs')->where('queue', 'notifications')->delete()`).

### WebSocket Connection Issues

If you see `WebSocket connection failed` errors in the browser console:

**1. Verify Reverb is running:**

```bash
docker compose ps
# Look for netsendo-reverb with status "Up"
```

**2. Check Reverb logs:**

```bash
docker compose logs reverb
# Should show: "Starting server on 0.0.0.0:8085"
```

**3. Verify configuration in `.env`:**

```bash
docker compose exec app grep -E "BROADCAST|REVERB|VITE_REVERB" .env
```

Should show:

```env
BROADCAST_CONNECTION=reverb
REVERB_PORT=8085
VITE_REVERB_PORT=8085
```

**4. Test WebSocket endpoint:**

```bash
curl http://localhost:8085
# Should return a response from Reverb
```

**5. Restart and rebuild:**

```bash
docker compose restart reverb
docker compose exec app npm run build
```

### Port 8085 Already in Use

```bash
# Find what's using the port
lsof -i :8085

# Change the port in .env if needed
REVERB_PORT=8086
VITE_REVERB_PORT=8086

# Update docker-compose.yml ports mapping
# Then restart
docker compose down
docker compose up -d
```

---

## 🔄 Migration from Old Installation

If you have an existing installation with the old `app-data` volume:

### 1. Backup Current Data

```bash
# Backup database
docker compose exec db mysqldump -u root -p netsendo > backup.sql

# Backup uploads
docker compose cp netsendo-app:/var/www/storage/app ./backup-storage/

# Backup .env
cp .env .env.backup
```

### 2. Remove Old Volumes

```bash
docker compose down -v
```

### 3. Start Fresh

```bash
docker compose up -d
```

### 4. Restore Data

```bash
# Restore database
docker compose exec -T db mysql -u root -p netsendo < backup.sql

# Restore uploads
docker compose cp ./backup-storage/. app:/var/www/storage/app/
```

---

## ⚠️ Common Issues

### `.env` Folder Created Instead of File

**Symptom:** After running `docker compose up -d`, a `.env/` directory is created instead of a `.env` file, and the application fails to start.

**Cause:** Docker creates a folder instead of a file when the source path for a bind mount doesn't exist. If you skip the `cp .env.example .env` step, Docker will create `.env` as a directory.

**Solution:**

```bash
# 1. Stop containers
docker compose down

# 2. Remove the incorrectly created folder
rm -rf .env

# 3. Create .env file from example
cp .env.example .env

# 4. Edit .env with your settings
nano .env

# 5. Start containers
docker compose up -d
```

> [!IMPORTANT]
> **Always create the `.env` file before running `docker compose up -d`!**

---

### WebSocket Connection Failed

**Symptom:** Browser console shows:

```
WebSocket connection to 'ws://localhost:8080/app/...' failed
```

**Cause:** Wrong port configuration or Reverb not running.

**Solution:**

1. Check `.env` has `REVERB_PORT=8085` and `VITE_REVERB_PORT=8085`
2. Verify Reverb container: `docker compose ps`
3. Rebuild frontend: `docker compose exec app npm run build`

### Reverb Container Not Starting

**Symptom:** `docker compose ps` shows reverb as "Restarting" or "Exited".

**Solution:**

```bash
# Check logs for errors
docker compose logs reverb

# Common issue: database not ready
# Wait for db to be healthy, then restart
docker compose restart reverb
```

### Real-time Features Not Working

**Symptom:** Live Visitors, notifications don't update in real-time.

**Cause:** `BROADCAST_CONNECTION` not set to `reverb`.

**Solution:**

```bash
# Update .env
BROADCAST_CONNECTION=reverb

# Clear cache and restart
docker compose exec app php artisan config:clear
docker compose restart app reverb
```

---

## 📞 Support

- 📖 Documentation: https://docs.netsendo.com
- 💬 Forum: https://forum.netsendo.com
- 🐛 Issues: https://github.com/NetSendo/NetSendo/issues

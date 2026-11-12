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

# Put a new APP_KEY into .env (the containers mount .env read-only and cannot write one)
sed -i.bak "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env && rm .env.bak

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
REVERB_APP_KEY=YOUR_RANDOM_KEY       # e.g. openssl rand -hex 10
REVERB_APP_SECRET=YOUR_RANDOM_SECRET # e.g. openssl rand -hex 16 (signs private channels)
REVERB_HOST=reverb
REVERB_PORT=8085                     # inside the Docker network; leave at 8085
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

## 📧 NMI - NetSendo Mail Infrastructure

NMI is NetSendo's own mail server (MTA) with dedicated IPs, IP warming, DKIM per domain and blacklist monitoring. It is a **GOLD** feature.

The MTA container (`nmi-mta`) starts with the rest of the stack: nothing to switch on. It relays nothing until a GOLD installation saves a mailbox with the **NetSendo MTA** provider.

**Before you send through it**, open **Settings → Mailboxes → Add mailbox → NetSendo MTA**. The box *Can this server send mail* shows whether this server can deliver:

- **outbound port 25** (many cloud providers block it for new servers);
- the server's **public address** and its **PTR** record (set at the company that gives you the address);
- the **SPF** entry (`ip4:…`) to add to the sending domain's SPF record.

Step-by-step instructions for common providers: [docs/NMI.md → Port 25, PTR and SPF](docs/NMI.md#port-25-ptr-and-spf).

Then verify your domain in **Deliverability** and add the mailbox. `NMI_DISABLED=true` in `.env` hides the provider.

### NMI Volumes

| Volume      | Purpose                           |
| ----------- | --------------------------------- |
| `nmi_auth`  | MTA logins of the sending domains |
| `nmi_queue` | Email queue storage               |
| `nmi_logs`  | MTA log files                     |
| `nmi_dkim`  | DKIM private keys                 |
| `nmi_tls`   | TLS certificates                  |

> 📖 Full documentation: [docs/NMI.md](docs/NMI.md)

---

## 🔄 Updates

NetSendo supports the standard Docker update workflow:

1. **Back up the database.** Migrations run on start and cannot be undone:

   ```bash
   docker compose exec db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" > /backups/before-update.sql'
   ```

   The file lands in `backups/db/` next to `docker-compose.yml`.

2. **Update the configuration files.** `docker-compose.yml` mounts your own copies of `docker/php/docker-entrypoint.sh`, `docker/php/uploads.ini` and `docker/nginx/default.conf` over the ones in the image, so `docker compose pull` alone does not bring their changes. In a git checkout run `git pull`. In a folder made by `install.sh`, or with downloaded copies:

   ```bash
   V=v2.11.3   # the release you update to, or main for the newest
   for f in docker-compose.yml docker/php/docker-entrypoint.sh docker/php/uploads.ini docker/nginx/default.conf; do
     cp "$f" "$f.bak" 2>/dev/null
     curl -fsSL "https://raw.githubusercontent.com/NetSendo/NetSendo/$V/$f" -o "$f"
   done
   chmod +x docker/php/docker-entrypoint.sh
   ```

   If you changed one of these files, carry your change over from its `.bak` copy. `.env` is never replaced; compare it with [`.env.example`](.env.example) for new settings.

3. **Pull the images and start:**

   ```bash
   docker compose pull
   docker compose up -d
   docker compose logs -f app   # migrations run here; scheduler, queue and reverb wait for them
   ```

   With a `docker-compose.yml` older than 2.11.1, also run `docker compose restart webserver` once the app is up: the old file does not restart it, and nginx keeps the app's previous address (every page answers 502).

No frontend rebuild or cache clearing is needed: the image brings the built assets and the containers rebuild their caches on start.

The `app` container runs pending migrations and seeds missing starter data on
start; `scheduler`, `queue` and `reverb` (`NETSENDO_RUN_MIGRATIONS=false` in
`docker-compose.yml`) wait until it is done. A MySQL lock lets only one
container prepare the database at a time, also with an older compose file.
Follow it with `docker compose logs -f app`.

Since 2.11.0 `docker-compose.yml` also runs the NetSendo MTA (service `nmi-mta`, image `ghcr.io/netsendo/netsendo:mta-<version>`, no port published on the server). An installation that keeps an older compose file goes on working, but *Settings → Mailboxes* then reports that the MTA does not answer.

### Update to Specific Version

```bash
NETSENDO_VERSION=2.11.3 docker compose up -d
```

Use the `docker-compose.yml` of the version you pin: versions before 2.11.0 have no `mta-` image, so the compose file of 2.11.0 or newer cannot start them.

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
# Database backup (-T: no terminal, so nothing but the dump reaches the file;
# the password and database name come from the db container's environment)
docker compose exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > backup.sql

# User uploads backup (docker compose cp takes the service name, "app")
docker compose cp app:/var/www/storage/app ./backup-storage/
```

Check the dump before relying on it: `head -n 5 backup.sql` should show `-- MySQL dump` and no password prompt or warning text.

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

Hosted installations (NetSendo Cloud) also use:

- `NETSENDO_DORMANT=true` puts a sleeping instance to sleep: only unsubscribe links work, nothing is sent, every other page shows "instance asleep" (503). Stop the `queue` and `scheduler` services too.
- `php artisan netsendo:bootstrap --admin-email=… [--locale=pl] [--license=…]` creates the administrator right after installing (password by email), so the first visitor cannot register as administrator.

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
> After changing `VITE_*` variables, run `docker compose up -d`. Since 2.7.1 the browser gets the `VITE_REVERB_*` values from the server, so no frontend rebuild is needed (and `npm run build` fails in the production image: it deletes `node_modules` after building the assets, `docker/php/Dockerfile`).

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

**5. Apply the configuration:**

```bash
docker compose up -d
```

### Port 8085 Already in Use

```bash
# Find what's using the port
lsof -i :8085

# Publish Reverb on another host port. Keep REVERB_PORT=8085: the app reaches
# Reverb inside the Docker network on 8085, whatever the host port is.
REVERB_HOST_PORT=8086
VITE_REVERB_PORT=8086

# Then apply it
docker compose up -d
```

`REVERB_HOST_PORT` needs a `docker-compose.yml` newer than 2.7.2 (its `reverb` ports line mentions `REVERB_HOST_PORT`). With an older compose file, change the left side of the `reverb` ports mapping (`"8086:8085"`) instead, and never `REVERB_PORT`: with `REVERB_PORT=8086` the app looks for Reverb on port 8086 inside the network, where nothing listens, and real-time updates stop.

### Reverb on a public server

Out of the box Reverb listens on all interfaces of the host (`0.0.0.0:8085`), so browsers can reach it directly. If a reverse proxy on the host forwards `/app/` to it (see *WebSocket Configuration (Reverb)* in README), close the direct port:

```env
REVERB_BIND_ADDRESS=127.0.0.1
```

Installations made with NetSendo 2.7.2 or older, by hand or with `install.sh`, use the example `REVERB_APP_KEY` and `REVERB_APP_SECRET` from `.env.example`. Anyone who knows the secret can sign access to the private channels of Live Visitors, so replace both with random values (`openssl rand -hex 16`) and run `docker compose up -d`; open browser tabs pick up the new key when reloaded.

---

## 🔄 Migration from Old Installation

If you have an existing installation with the old `app-data` volume:

### 1. Backup Current Data

```bash
# Backup database (-T keeps the terminal out of the dump; the database is the one in
# DB_DATABASE, netsendo_prod by default)
docker compose exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > backup.sql

# Backup uploads (service name "app", not the container name netsendo-app)
docker compose cp app:/var/www/storage/app ./backup-storage/

# Backup .env
cp .env .env.backup
```

Before the next step deletes the volumes, check the dump: `head -n 5 backup.sql` must show `-- MySQL dump`, and the file must not be empty.

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
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < backup.sql

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
WebSocket connection to 'ws://localhost:8085/app/...' failed
```

**Cause:** Wrong port configuration or Reverb not running.

**Solution:**

1. Check `.env` has `REVERB_PORT=8085` and `VITE_REVERB_PORT=8085`
2. Verify Reverb container: `docker compose ps`
3. Apply the configuration again: `docker compose up -d` (the browser gets the `VITE_REVERB_*` values from the server, no frontend rebuild is needed)

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

- 📖 Documentation: https://netsendo.com/en/docs
- 📢 Telegram: https://t.me/netsendo_official
- 🐛 Issues: https://github.com/NetSendo/NetSendo/issues

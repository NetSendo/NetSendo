# 🐳 NetSendo Docker Installation Guide

Complete guide for deploying NetSendo using Docker.

---

## 📋 Prerequisites

| Requirement | Minimum | Recommended |
|------------|---------|-------------|
| Docker | 20.10+ | 24.0+ |
| Docker Compose | v2.0+ | v2.20+ |
| RAM | 2GB | 4GB |
| Disk Space | 5GB | 10GB |

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
```

### 3. Start Services

```bash
docker compose up -d
```

### 4. Access Application

| Service | URL |
|---------|-----|
| **NetSendo** | http://localhost:5029 |
| **Mailpit** | http://localhost:5031 |

Note: A background worker (`netsendo-scheduler`) is also started to handle scheduled emails and automation.

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

---

## 📁 Data Persistence

| Volume | Purpose | Backed Up? |
|--------|---------|------------|
| `dbdata` | MySQL database | ✅ Yes |
| `redisdata` | Redis cache | ❌ No |
| `netsendo-storage` | User uploads | ✅ Yes |
| `netsendo-logs` | App logs | ❌ Optional |
| `netsendo-public` | Static files (auto-synced) | ❌ No |

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

| Variable | Default | Description |
|----------|---------|-------------|
| `NETSENDO_VERSION` | `latest` | Docker image version |
| `HTTP_PORT` | `5029` | HTTP port |
| `DB_PORT` | `5030` | MySQL port |
| `DB_PASSWORD` | `root` | MySQL password |
| `MAIL_HOST` | `mailpit` | SMTP host |

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
}
```

Update `.env`:
```env
APP_URL=https://netsendo.example.com
```

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

## 📞 Support

- 📖 Documentation: https://docs.netsendo.com
- 💬 Forum: https://forum.netsendo.com
- 🐛 Issues: https://github.com/NetSendo/NetSendo/issues

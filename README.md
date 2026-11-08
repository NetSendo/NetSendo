<div align="center">

![NetSendo Logo](https://gregciupek.com/wp-content/uploads/2025/12/Logo-NetSendo-1700-x-500-px.png)

# NetSendo

**Professional Email Marketing & Automation Platform**

[![Version](https://img.shields.io/badge/version-2.7.2-blue.svg)](https://github.com/NetSendo/NetSendo/releases)
[![PHP](https://img.shields.io/badge/PHP-8.5-purple.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-red.svg)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.5-green.svg)](https://vuejs.org)
[![Inertia](https://img.shields.io/badge/Inertia-3-9553e9.svg)](https://inertiajs.com)
[![Tailwind](https://img.shields.io/badge/Tailwind-4-38bdf8.svg)](https://tailwindcss.com)
[![Vite](https://img.shields.io/badge/Vite-8-646cff.svg)](https://vite.dev)
[![MySQL](https://img.shields.io/badge/MySQL-8.4_LTS-4479a1.svg)](https://dev.mysql.com/doc/refman/8.4/en/)
[![License](https://img.shields.io/badge/License-Proprietary-orange.svg)](LICENSE.md)
[![Contributing](https://img.shields.io/badge/contributions-welcome-brightgreen.svg)](CONTRIBUTING.md)
[![GitHub Stars](https://img.shields.io/github/stars/NetSendo/NetSendo?style=social)](https://github.com/NetSendo/NetSendo/stargazers)

[📖 Documentation](https://netsendo.com/en/docs) • [📚 Guides](https://netsendo.com/en/guides) • [📢 Telegram](https://t.me/netsendo_official) • [🐛 Report Bug](https://github.com/NetSendo/NetSendo/issues/new?template=bug_report.md)

**[🇺🇸 English](#-about-netsendo)** | [🇵🇱 Polski](#-o-netsendo-pl) | [🇩🇪 Deutsch](#-über-netsendo-de) | [🇪🇸 Español](#-acerca-de-netsendo-es)

</div>

---

## 🚀 About NetSendo

NetSendo is a modern email marketing and automation platform that enables:

- 📧 **Email Marketing** - Create and send email campaigns with advanced MJML editor
- 🎥 **Webinars & Auto-Webinars** - Host live sessions or schedule automated evergreen webinars with simulated chat
- 👥 **Live Visitors** - Real-time visitor tracking and analytics using WebSockets
- 🛍️ **E-commerce & Funnels** - Sell digital products with Stripe, Polar and Tpay, take orders from Shopify and WooCommerce, and follow up with built-in sales funnels
- 🤖 **AI Suite** - NetSendo Brain, Campaign Auditor, Advisor and content generation with your own OpenAI, Anthropic Claude, Google Gemini or xAI Grok keys (OpenRouter and local Ollama models on GOLD)
- 🔌 **MCP Server** - Model Context Protocol integration for AI assistants (Claude, Cursor, VS Code)
- 📱 **SMS Marketing** - Send SMS messages to your subscribers
- 🔄 **Automations** - Build complex scenarios and workflows
- 📦 **Integrations** - Native support for WordPress, WooCommerce, n8n, and more
- 📊 **Analytics** - Detailed open, click, and conversion statistics
- 🎨 **Templates** - Drag & drop email template builder
- 👥 **CRM** - Manage subscribers, groups, and tags
- 🔒 **[NMI](docs/NMI.md)** - Professional mail infrastructure with dedicated IPs, IP warming, DKIM, and blacklist monitoring

### 🔐 Privacy

- The panel loads nothing from other hosts: fonts and scripts are served by your own installation, and there is no Google Analytics. A webinar page loads Google Fonts only when you pick a Google font for it.
- Telemetry is off by default. If the administrator switches it on, the installation sends one anonymous usage report a day; [docs/TELEMETRY.md](docs/TELEMETRY.md) lists every field.
- Activating and checking the licence sends netsendo.com the licence key or e-mail address, the domain and the NetSendo version. Your subscribers, messages and statistics stay on your server.

---

## 📸 Dashboard Preview

<div align="center">

![NetSendo Dashboard](https://gregciupek.com/wp-content/uploads/2025/12/CleanShot-2025-12-20-at-13.58.34.png)

_Modern, intuitive dashboard with real-time analytics and campaign management_

</div>

---

## 📋 Requirements

- **Docker** with Docker Compose v2 (recommended), or for a manual setup:
  - PHP 8.5 (the Docker image and CI use 8.5)
  - MySQL 8.4 LTS (8.0 still works, but it reached end of life in April 2026)
  - Redis 7
  - Node.js 24 LTS and npm (to build the frontend)
  - Composer 2
  - **PHP GD Extension** (optional, for automatic color extraction from images)

> [!TIP]
> The PHP GD extension enables automatic color palette extraction from uploaded images in the Media Library. If GD is not installed, image uploads will still work, but color extraction will be skipped.

---

## 🐳 Installation (Docker)

> [!IMPORTANT]
> The production stack reads the `.env` file in the repository root (created from `.env.example`) and mounts it read-only, so set these values **before the first start**:
>
> - `APP_KEY` — must start with `base64:`; generate it with `openssl rand -base64 32`. The containers cannot write a key into the read-only file.
> - `APP_URL` — the address users open, e.g. `https://mail.example.com`.
> - `DB_PASSWORD` — change it from the default.
>
> AI keys do not go into `.env`: add OpenAI, Anthropic, Gemini or Grok keys in the panel under **Settings → AI Integrations**.

---

### 🚀 Production Deployment

**Option 1: Quick Install Script (Recommended)**

```bash
curl -fsSL https://raw.githubusercontent.com/NetSendo/NetSendo/main/install.sh | bash
```

The script creates a `netsendo/` folder with the compose file, its config files and a `.env` with a new `APP_KEY` and a random database password, then starts the stack on http://localhost:8080. To install a specific version:

```bash
curl -fsSL https://raw.githubusercontent.com/NetSendo/NetSendo/main/install.sh | VERSION=2.7.2 bash
```

**Option 2: Manual Production Deployment**

```bash
# Clone repository
git clone https://github.com/NetSendo/NetSendo.git
cd NetSendo

# Create .env file from example and put a new key into it
cp .env.example .env
sed -i.bak "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env && rm .env.bak
# Edit .env: APP_URL, DB_PASSWORD, mail settings

# Start production stack (uses pre-built images)
docker compose up -d

# Or specify a version
NETSENDO_VERSION=2.7.2 docker compose up -d
```

| Service      | Address               | Description                                  |
| ------------ | --------------------- | -------------------------------------------- |
| **NetSendo** | http://localhost:5029 | Main dashboard (`HTTP_PORT`)                 |
| **Mailpit**  | http://localhost:5031 | Test email inbox (`MAILPIT_HTTP_PORT`)       |
| **MySQL**    | 127.0.0.1:3306        | Database (`DB_PORT`, 3306 in `.env.example`) |
| **Reverb**   | localhost:8085        | WebSocket Server (`REVERB_PORT`)             |

> [!TIP]
> The web app, MySQL and Mailpit are bound to `127.0.0.1`; put a reverse proxy (nginx, Caddy) in front of the web app for public access. Reverb listens on all interfaces because browsers connect to it directly.

---

### 🛠️ Development Setup

```bash
git clone https://github.com/NetSendo/NetSendo.git
cd NetSendo

# The dev stack mounts ./src and uses src/.env
cp src/.env.docker src/.env

# Start development stack (builds from source)
docker compose -f docker-compose.dev.yml up -d --build
```

On first run, the container will automatically:

- ✅ Install Composer and NPM dependencies
- ✅ Generate application key (into `src/.env`)
- ✅ Run database migrations
- ✅ Build frontend assets

| Service      | URL                   | Description            |
| ------------ | --------------------- | ---------------------- |
| **NetSendo** | http://localhost:8080 | Main dashboard         |
| **Mailpit**  | http://localhost:8025 | Test email inbox       |
| **MySQL**    | localhost:33006       | Database               |
| **Reverb**   | localhost:8085        | WebSocket Server       |
| **Vite HMR** | http://localhost:5173 | Hot Module Replacement |

Run the tests from `src/` with `php artisan test` (SQLite in memory, no Docker needed).

---

## 🔑 Licensing

Every installation runs on a licence: SILVER is free, GOLD is a subscription. Without an active GOLD licence (no key, SILVER, or GOLD past its paid period) the GOLD features are switched off and everything else keeps working; nothing is deleted.

### License Plans

| Plan       | Price                 | Features |
| ---------- | --------------------- | -------- |
| **SILVER** | Free                  | Email and SMS marketing with unlimited lists and contacts, automations and funnels, complete CRM (incl. lead scoring and CardIntel), live webinars, AI assistants with your own OpenAI, Anthropic, Gemini or Grok keys, NetSendo Brain chat (manual mode), up to 3 AI campaigns, all integrations, public API and MCP server, affiliate program, owner + 1 team member, community support |
| **GOLD**   | $97/mo or $970/yr     | Everything in SILVER, plus: unlimited AI campaigns, OpenRouter and local Ollama models, autonomous NetSendo Brain (automatic modes, cron, goals, digest, Telegram, research, voice), semantic Brain knowledge base (vector search), AI Campaign Auditor, A/B tests (campaigns and funnel split steps), auto-webinars with the scenario builder, hybrid webinars, segmentation dashboard, Deliverability Shield, NetSendo Mail Infrastructure, unlimited team members, priority support |

The list the application enforces is `src/config/netsendo.php` (`plans`) and `App\Licensing\Feature`.

No plan includes white-label or rebranding rights (EULA §3.6, §7.3).

### License Activation

1. Open the application and register the administrator account.
2. The licence page opens. For **SILVER**, enter your e-mail address; the licence is activated automatically.
3. For **GOLD**, choose **Buy GOLD**: payment opens on netsendo.com in a new tab, and GOLD switches on in the panel as soon as the payment is confirmed. A key you already have goes in under **I have a license key**.

Plans and prices: [netsendo.com/en/pricing](https://netsendo.com/en/pricing).

---

## 🛠️ Docker Commands

### Production

```bash
# Start production stack
docker compose up -d

# Stop
docker compose down

# View logs
docker compose logs -f app

# Shell access
docker exec -it netsendo-app bash

# Artisan commands
docker exec netsendo-app php artisan <command>
```

### Development

```bash
# Start development stack
docker compose -f docker-compose.dev.yml up -d --build

# Stop
docker compose -f docker-compose.dev.yml down

# View logs
docker compose -f docker-compose.dev.yml logs -f app

# Shell access
docker exec -it netsendo-app bash

# Artisan commands
docker exec netsendo-app php artisan <command>

# Composer
docker exec netsendo-app composer <command>

# NPM
docker exec netsendo-app npm <command>
```

---

## 📁 Project Structure

```
NetSendo/
├── docker/                     # Docker configuration
│   ├── haraka/                # NMI mail server (Haraka MTA)
│   ├── nginx/                 # Nginx config
│   └── php/                   # PHP Dockerfile + entrypoint
├── docs/                       # Integration and feature docs
├── mcp/                        # MCP client (@netsendo/mcp-client on npm)
├── src/                        # Laravel source code
│   ├── app/                   # Application logic
│   ├── config/                # Configuration
│   ├── database/              # Migrations and seeders
│   ├── resources/             # Frontend (Vue.js, CSS)
│   ├── routes/                # Routing
│   └── public/                # Public files
├── backups/                    # Database backups
│   └── db/                    # MySQL backup files
├── docker-compose.yml          # Production configuration (main)
├── docker-compose.dev.yml      # Development configuration
├── install.sh                  # Quick install script
└── README.md                  # This file
```

---

## 🔧 Configuration

The production stack reads `.env` in the repository root (created from `.env.example`, read-only inside the containers). The development stack reads `src/.env` (created from `src/.env.docker`). After changing `.env`, run `docker compose up -d` again; the containers rebuild their configuration cache on start.

### Required Environment Variables

```env
# REQUIRED: Application Key (must start with base64:)
# Generate with: openssl rand -base64 32
APP_KEY=base64:YOUR_GENERATED_KEY_HERE

APP_URL=http://localhost:5029
APP_LOCALE=en

# Database (Docker) - CHANGE THE PASSWORD!
DB_HOST=db
DB_DATABASE=netsendo_prod
DB_USERNAME=netsendo_user
DB_PASSWORD=your_secure_password_here

# Redis
REDIS_HOST=redis

# Mail (Mailpit in Docker)
MAIL_HOST=mailpit
MAIL_PORT=1025
```

### AI Integrations

AI providers are not configured in `.env`. Add your keys in the panel under **Settings → AI Integrations**: OpenAI, Anthropic, Google Gemini and xAI Grok on every plan, OpenRouter and local Ollama models on GOLD.

### WebSocket Configuration (Reverb)

> [!IMPORTANT] > **WebSocket server is required** for real-time features like Live Visitors, notifications, and real-time analytics.

NetSendo uses Laravel Reverb as a WebSocket server. The configuration is already included in `docker-compose.yml` and `docker-compose.dev.yml`.

**Required environment variables:**

```env
# Enable broadcasting via Reverb
BROADCAST_CONNECTION=reverb

# Backend configuration (for PHP/Laravel)
REVERB_APP_ID=netsendo
REVERB_APP_KEY=netsendo-reverb-key
REVERB_APP_SECRET=netsendo-reverb-secret
REVERB_HOST=reverb                    # Docker service name
REVERB_PORT=8085
REVERB_SCHEME=http

# Frontend configuration (for browser)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=localhost             # Or your domain for production
VITE_REVERB_PORT=8085                  # 443 behind an HTTPS reverse proxy
VITE_REVERB_SCHEME=http                # https behind an HTTPS reverse proxy
```

**Key points:**

- `REVERB_HOST=reverb` - for backend (Docker internal network)
- `VITE_REVERB_HOST=localhost` - for browser (external access); on a public instance behind an HTTPS reverse proxy that forwards `/app/` to port 8085, use your domain with `VITE_REVERB_PORT=443` and `VITE_REVERB_SCHEME=https`
- Port `8085` is exposed by the `reverb` container
- The browser gets the `VITE_REVERB_*` values from the server when a page loads, so after changing them run `docker compose up -d`; no frontend rebuild is needed

### Production Configuration

> [!TIP] > **Auto-detected settings**: NetSendo automatically configures these from `APP_URL`:
>
> - `SESSION_DOMAIN` - extracted from APP_URL hostname
> - `SESSION_SECURE_COOKIE` - set to `true` if APP_URL uses `https://`

```env
# === REQUIRED ===
APP_URL=https://your-domain.com    # Your production URL (with https://)
APP_KEY=base64:...                 # Generate with: openssl rand -base64 32
APP_ENV=production
APP_DEBUG=false

# Database
DB_CONNECTION=mysql
DB_HOST=db
DB_DATABASE=netsendo_prod
DB_USERNAME=netsendo_user
DB_PASSWORD=your_secure_password   # CHANGE THIS!

# Redis (recommended for sessions/cache)
REDIS_HOST=redis
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# Mail configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-server.com
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# === OPTIONAL OVERRIDES ===
# These are auto-detected from APP_URL, only set if you need custom values:

# SESSION_DOMAIN=.your-domain.com       # Auto-detected from APP_URL
# SESSION_SECURE_COOKIE=true            # Auto-detected (true for https://)
# TRUSTED_PROXIES=*                     # Set if behind load balancer/CDN
```

> [!IMPORTANT] > **Reverse Proxy Configuration**: If using nginx/Caddy as reverse proxy, ensure your proxy passes the correct headers:
>
> ```nginx
> proxy_set_header X-Forwarded-Proto $scheme;
> proxy_set_header X-Forwarded-Host $host;
> proxy_set_header X-Real-IP $remote_addr;
> ```

---

## 🌍 Internationalization

NetSendo supports the following languages:

- 🇺🇸 English (default)
- 🇵🇱 Polski
- 🇩🇪 Deutsch
- 🇪🇸 Español

Language switcher is available in the application header.

---

## 📈 Updates

Check for available updates:

1. In the app: **Settings → Updates**
2. On GitHub: [Releases](https://github.com/NetSendo/NetSendo/releases)

### Update Process (Standard Docker Workflow)

1. **Back up the database.** Migrations run on start and cannot be undone:

   ```bash
   docker compose exec db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" > /backups/before-update.sql'
   ```

   The file lands in `backups/db/` next to `docker-compose.yml`.

2. **Update the configuration files.** `docker-compose.yml` mounts your own copies of `docker/php/docker-entrypoint.sh`, `docker/php/uploads.ini` and `docker/nginx/default.conf` over the ones in the image, so `docker compose pull` alone does not bring their changes. In a git checkout run `git pull`. In a folder made by `install.sh`, or with downloaded copies:

   ```bash
   V=v2.7.2   # the release you update to, or main for the newest
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

No frontend rebuild or cache clearing is needed: the image brings the built assets and the containers rebuild their caches on start.

**Update to specific version:**

```bash
NETSENDO_VERSION=2.7.2 docker compose up -d
```

## 📖 For detailed instructions, see [DOCKER_INSTALL.md](DOCKER_INSTALL.md)

## 🔧 Troubleshooting

### Container Won't Start

```bash
# Check logs
docker compose logs app

# Verify database is healthy
docker compose exec db mysqladmin ping -h localhost
```

### Clear Caches

```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan view:clear
```

### Browser Cache Issues

If changes don't appear after update:

- Hard refresh: `Ctrl+Shift+R` (Windows/Linux) or `Cmd+Shift+R` (Mac)
- Clear browser cache or use incognito mode

### WebSocket Connection Failed

If you see errors like:

```
WebSocket connection to 'ws://localhost:8085/app/...' failed
```

**Cause:** Reverb is not configured or not running.

**Solution:**

1. **Check Reverb configuration in `.env`:**

   ```env
   BROADCAST_CONNECTION=reverb
   REVERB_PORT=8085
   VITE_REVERB_PORT=8085
   ```

2. **Verify Reverb container is running:**

   ```bash
   docker compose ps
   # Should show netsendo-reverb with status "Up"
   ```

3. **Check Reverb logs:**

   ```bash
   docker compose logs reverb
   # Should show: "Starting server on 0.0.0.0:8085"
   ```

4. **Apply the configuration again:**

   ```bash
   docker compose up -d
   docker compose restart reverb
   ```

5. **Verify port 8085 is accessible:**
   ```bash
   curl http://localhost:8085
   # Should return Reverb response
   ```

📖 For more troubleshooting, see [DOCKER_INSTALL.md](DOCKER_INSTALL.md#-troubleshooting)

---

## 🤝 Support

- 📖 **Documentation**: https://netsendo.com/en/docs
- 📚 **Guides**: https://netsendo.com/en/guides
- 🐛 **Report Bug**: [GitHub Issues](https://github.com/NetSendo/NetSendo/issues/new?template=bug_report.md)
- 🔒 **Security issues**: privately, as described in [SECURITY.md](SECURITY.md)
- 📧 **Technical support**: support@netsendo.com
- 📧 **Licensing, orders and business**: contact@netsendo.com

---

## 🤝 Contributing

We welcome contributions! Whether it's fixing bugs, improving documentation, or adding new features.

🏆 **Accepted contributions earn a free GOLD licence:** 12 months for your first one, no end date from your third. [How it works](CONTRIBUTING.md#-gold-for-contributors).

👉 **[Read our Contributing Guide](CONTRIBUTING.md)** to get started.

### Quick ways to help:
- ⭐ **Star this repo** — helps others discover NetSendo
- 🐛 **Report bugs** — [open an issue](https://github.com/NetSendo/NetSendo/issues/new?template=bug_report.md)
- 📝 **Improve docs** — fix typos, add examples
- 🌍 **Translate** — help with [translations](TRANSLATIONS.md)

### Community
- [📢 Telegram](https://t.me/netsendo_official) — product updates and announcements
- [📚 Guides](https://netsendo.com/en/guides) — learn NetSendo

## 📄 License

NetSendo is proprietary, source-available software licensed under the [NetSendo EULA](LICENSE.md). The code is public so you can read it, report issues and contribute — not to copy it.

- ✅ **Allowed:** running a licensed instance for your own business; forking to prepare pull requests to this repository (EULA §6.8).
- ❌ **Not allowed:** redistributing or republishing the code, rebranding or white-labelling it, offering it as SaaS, bypassing licence checks, building a competing product from it — by hand or with AI tools (EULA §7).
- 🤖 **AI:** using this repository for AI training or text and data mining is reserved and prohibited (EULA §7.9, §8.8). Notes for AI coding agents are in [AGENTS.md](AGENTS.md).

See also [NOTICE](NOTICE).

---

<details>
<summary>

## 🇵🇱 O NetSendo (PL)

</summary>

NetSendo to nowoczesna platforma e-mail marketingu i automatyzacji. Umożliwia tworzenie kampanii emailowych, SMS, automatyzacji sprzedażowych i szczegółowej analityki.

### Instalacja

```bash
curl -fsSL https://raw.githubusercontent.com/NetSendo/NetSendo/main/install.sh | bash
```

Skrypt tworzy folder `netsendo/` z konfiguracją i kluczem aplikacji. Aplikacja jest dostępna pod http://localhost:5029. Licencja SILVER jest darmowa, GOLD to subskrypcja; szczegóły w [cenniku](https://netsendo.com/pl/pricing). Pełna instrukcja: [Production Deployment](#-production-deployment).

</details>

<details>
<summary>

## 🇩🇪 Über NetSendo (DE)

</summary>

NetSendo ist eine moderne E-Mail-Marketing- und Automatisierungsplattform. Erstellen Sie E-Mail-Kampagnen, SMS, Verkaufsautomatisierungen und detaillierte Analysen.

### Installation

```bash
curl -fsSL https://raw.githubusercontent.com/NetSendo/NetSendo/main/install.sh | bash
```

Das Skript legt den Ordner `netsendo/` mit Konfiguration und Anwendungsschlüssel an. Die Anwendung ist unter http://localhost:5029 erreichbar. Die SILVER-Lizenz ist kostenlos, GOLD ist ein Abonnement; Details in der [Preisliste](https://netsendo.com/de/pricing). Vollständige Anleitung: [Production Deployment](#-production-deployment).

</details>

<details>
<summary>

## 🇪🇸 Acerca de NetSendo (ES)

</summary>

NetSendo es una plataforma moderna de email marketing y automatización. Cree campañas de correo electrónico, SMS, automatizaciones de ventas y análisis detallados.

### Instalación

```bash
curl -fsSL https://raw.githubusercontent.com/NetSendo/NetSendo/main/install.sh | bash
```

El script crea la carpeta `netsendo/` con la configuración y la clave de la aplicación. La aplicación está disponible en http://localhost:5029. La licencia SILVER es gratuita y GOLD es una suscripción; detalles en los [precios](https://netsendo.com/es/pricing). Guía completa: [Production Deployment](#-production-deployment).

</details>

---

<div align="center">

**Made with ❤️ by [NetSendo Team](https://netsendo.com)**

![NetSendo Icon](https://gregciupek.com/wp-content/uploads/2025/12/logo-netsendo-kwadrat-ciemne.png)

</div>

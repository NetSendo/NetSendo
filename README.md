<div align="center">

![NetSendo Logo](https://gregciupek.com/wp-content/uploads/2025/12/Logo-NetSendo-1700-x-500-px.png)

# NetSendo

**Professional Email Marketing & Automation Platform**

[![Version](https://img.shields.io/badge/version-1.2.11-blue.svg)](https://github.com/NetSendo/NetSendo/releases)
[![PHP](https://img.shields.io/badge/PHP-8.5-purple.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3-green.svg)](https://vuejs.org)
[![License](https://img.shields.io/badge/License-Proprietary-orange.svg)](LICENSE)

[📖 Documentation](https://docs.netsendo.com) • [🎓 Courses](https://netsendo.com/courses) • [💬 Forum](https://forum.netsendo.com) • [🐛 Report Bug](https://support.netsendo.com)

**[🇺🇸 English](#-about-netsendo)** | [🇵🇱 Polski](#-o-netsendo-pl) | [🇩🇪 Deutsch](#-über-netsendo-de) | [🇪🇸 Español](#-acerca-de-netsendo-es)

</div>

---

## 🚀 About NetSendo

NetSendo is a modern email marketing and automation platform that enables:

- 📧 **Email Marketing** - Create and send email campaigns with advanced MJML editor
- 📱 **SMS Marketing** - Send SMS messages to your subscribers
- 🤖 **Automations** - Build automated sales funnels and workflows
- 📊 **Analytics** - Detailed open, click, and conversion statistics
- 🎨 **Templates** - Drag & drop email template builder
- 🔗 **AI Integrations** - OpenAI, Anthropic Claude, Google Gemini support
- 📝 **Forms** - Generate subscription forms with webhooks
- 👥 **CRM** - Manage subscribers, groups, and tags

---

## 📸 Dashboard Preview

<div align="center">

![NetSendo Dashboard](https://gregciupek.com/wp-content/uploads/2025/12/CleanShot-2025-12-20-at-13.58.34.png)

_Modern, intuitive dashboard with real-time analytics and campaign management_

</div>

---

## 📋 Requirements

- **Docker Desktop** (recommended) or:
  - PHP 8.5+
  - MySQL 8.0+
  - Redis
  - Node.js 25+
  - Composer

---

## 🐳 Installation (Docker)

> [!IMPORTANT] > **Required Configuration Before Starting Docker**
>
> Before running Docker, you MUST configure the following environment variables in `src/.env.docker`:
>
> **1. APP_KEY** (Required - Docker will NOT build without this!):
>
> ```env
> # Find the line: APP_KEY=
> # Replace with (IMPORTANT: must start with base64:):
> APP_KEY=base64:YOUR_32_BYTE_KEY_HERE
> ```
>
> Generate a key using: `openssl rand -base64 32`
>
> **2. DB_PASSWORD** (Required - change from default):
>
> ```env
> DB_PASSWORD=your_secure_password
> ```
>
> **3. AI API Keys** (Optional - for AI features):
>
> ```env
> OPENAI_API_KEY=sk-...
> ANTHROPIC_API_KEY=sk-ant-...
> GOOGLE_AI_API_KEY=...
> ```

---

### 🚀 Production Deployment

**Option 1: Quick Install Script (Recommended)**

```bash
curl -fsSL https://raw.githubusercontent.com/NetSendo/NetSendo/main/install.sh | bash
```

To install a specific version:

```bash
VERSION=1.0.0 curl -fsSL https://raw.githubusercontent.com/NetSendo/NetSendo/main/install.sh | bash
```

**Option 2: Manual Production Deployment**

```bash
# Clone repository
git clone https://github.com/NetSendo/NetSendo.git
cd NetSendo

# Create .env file from example
cp .env.example .env
# Edit .env with your production settings

# Start production stack (uses pre-built images)
docker compose up -d

# Or specify a version
NETSENDO_VERSION=1.0.0 docker compose up -d
```

| Service      | URL                   | Description      |
| ------------ | --------------------- | ---------------- |
| **NetSendo** | http://localhost:5029 | Main dashboard   |
| **Mailpit**  | http://localhost:5031 | Test email inbox |
| **MySQL**    | localhost:5030        | Database         |

> [!TIP]
> All ports are bound to `127.0.0.1` for security. Use a reverse proxy (nginx, Caddy) for public access.

---

### 🛠️ Development Setup

```bash
git clone https://github.com/NetSendo/NetSendo.git
cd NetSendo

# Start development stack (builds from source)
docker compose -f docker-compose.dev.yml up -d --build
```

On first run, the container will automatically:

- ✅ Install Composer and NPM dependencies
- ✅ Generate application key
- ✅ Run database migrations
- ✅ Build frontend assets

| Service      | URL                   | Description            |
| ------------ | --------------------- | ---------------------- |
| **NetSendo** | http://localhost:8080 | Main dashboard         |
| **Mailpit**  | http://localhost:8025 | Test email inbox       |
| **MySQL**    | localhost:33006       | Database               |
| **Vite HMR** | http://localhost:5173 | Hot Module Replacement |

---

## 🔑 Licensing

NetSendo requires an active license to operate.

### License Plans

| Plan       | Price  | Features                                                 |
| ---------- | ------ | -------------------------------------------------------- |
| **SILVER** | Free   | All basic features, unlimited contacts                   |
| **GOLD**   | $97/mo | Advanced automations, priority support, API, white-label |

### License Activation

1. Launch the application and go to the main page
2. Register an administrator account
3. On the license page, select SILVER (free) or GOLD plan
4. Enter your email - the license will be automatically activated

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
│   ├── nginx/                 # Nginx config
│   └── php/                   # PHP Dockerfile + entrypoint
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
└── README.md                  # This file
```

---

## 🔧 Configuration

Configuration is stored in `src/.env` (automatically created from `src/.env.docker`).

> [!CAUTION] > **You MUST configure these variables before building Docker!**

### Required Environment Variables

```env
# REQUIRED: Application Key (must start with base64:)
# Generate with: openssl rand -base64 32
APP_KEY=base64:YOUR_GENERATED_KEY_HERE

APP_URL=http://localhost:8080
APP_LOCALE=en

# Database (Docker) - CHANGE THE PASSWORD!
DB_HOST=db
DB_DATABASE=netsendo
DB_USERNAME=netsendo
DB_PASSWORD=your_secure_password_here

# Redis
REDIS_HOST=redis

# Mail (Mailpit in Docker)
MAIL_HOST=mailpit
MAIL_PORT=1025
```

### Optional: AI Integration Keys

```env
# OpenAI (for GPT features)
OPENAI_API_KEY=sk-...

# Anthropic Claude
ANTHROPIC_API_KEY=sk-ant-...

# Google AI (Gemini)
GOOGLE_AI_API_KEY=...
```

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
CACHE_DRIVER=redis
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

```bash
# Stop containers
docker compose down

# Pull latest images
docker compose pull

# Start with new version
docker compose up -d
```

> [!TIP]
> That's it! No rebuilding or cache clearing required. Works just like n8n and other Docker apps.

**Update to specific version:**

```bash
NETSENDO_VERSION=1.1.0 docker compose up -d
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

📖 For more troubleshooting, see [DOCKER_INSTALL.md](DOCKER_INSTALL.md#-troubleshooting)

---

## 🤝 Support

- 📖 **Documentation**: https://docs.netsendo.com
- 💬 **Forum**: https://forum.netsendo.com
- 🎓 **Courses**: https://netsendo.com/courses
- 🐛 **Report Bug**: https://support.netsendo.com
- 📧 **Email**: support@netsendo.com

---

## 📄 License

NetSendo is proprietary software. See [LICENSE](LICENSE) for details.

---

<details>
<summary>

## 🇵🇱 O NetSendo (PL)

</summary>

NetSendo to nowoczesna platforma e-mail marketingu i automatyzacji. Umożliwia tworzenie kampanii emailowych, SMS, automatyzacji sprzedażowych i szczegółowej analityki.

### Instalacja

```bash
git clone https://github.com/NetSendo/NetSendo.git
cd NetSendo
docker compose up -d --build
```

Aplikacja dostępna pod: http://localhost:8080

</details>

<details>
<summary>

## 🇩🇪 Über NetSendo (DE)

</summary>

NetSendo ist eine moderne E-Mail-Marketing- und Automatisierungsplattform. Erstellen Sie E-Mail-Kampagnen, SMS, Verkaufsautomatisierungen und detaillierte Analysen.

### Installation

```bash
git clone https://github.com/NetSendo/NetSendo.git
cd NetSendo
docker compose up -d --build
```

Anwendung verfügbar unter: http://localhost:8080

</details>

<details>
<summary>

## 🇪🇸 Acerca de NetSendo (ES)

</summary>

NetSendo es una plataforma moderna de email marketing y automatización. Cree campañas de correo electrónico, SMS, automatizaciones de ventas y análisis detallados.

### Instalación

```bash
git clone https://github.com/NetSendo/NetSendo.git
cd NetSendo
docker compose up -d --build
```

Aplicación disponible en: http://localhost:8080

</details>

---

<div align="center">

**Made with ❤️ by [NetSendo Team](https://netsendo.com)**

![NetSendo Icon](https://gregciupek.com/wp-content/uploads/2025/12/logo-netsendo-kwadrat-ciemne.png)

</div>

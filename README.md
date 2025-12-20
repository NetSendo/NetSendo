<div align="center">

![NetSendo Logo](https://gregciupek.com/wp-content/uploads/2025/12/Logo-NetSendo-1700-x-500-px.png)

# NetSendo

**Professional Email Marketing & Automation Platform**

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/NetSendo/NetSendo/releases)
[![PHP](https://img.shields.io/badge/PHP-8.3-purple.svg)](https://php.net)
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

## 📋 Requirements

- **Docker Desktop** (recommended) or:
  - PHP 8.3+
  - MySQL 8.0+
  - Redis
  - Node.js 20+
  - Composer

---

## 🐳 Installation (Docker)

### Option 1: Quick Install (Recommended)

One-line installation using pre-built Docker images:

```bash
curl -fsSL https://raw.githubusercontent.com/NetSendo/NetSendo/main/install.sh | bash
```

To install a specific version:
```bash
VERSION=1.0.0 curl -fsSL https://raw.githubusercontent.com/NetSendo/NetSendo/main/install.sh | bash
```

### Option 2: Using Pre-built Images

```bash
# Clone repository
git clone https://github.com/NetSendo/NetSendo.git
cd NetSendo

# Pull and start (latest version)
docker compose -f docker-compose.prod.yml up -d

# Or specify a version
NETSENDO_VERSION=1.0.0 docker compose -f docker-compose.prod.yml up -d
```

### Option 3: Build from Source (Development)

```bash
git clone https://github.com/NetSendo/NetSendo.git
cd NetSendo
docker compose up -d --build
```

On first run, the container will automatically:
- ✅ Install Composer and NPM dependencies
- ✅ Generate application key
- ✅ Run database migrations
- ✅ Build frontend assets

### Access the application

| Service | URL | Description |
|---------|-----|-------------|
| **NetSendo** | http://localhost:8080 | Main dashboard |
| **Mailpit** | http://localhost:8025 | Test email inbox |
| **MySQL** | localhost:33006 | Database |

---

## 🔑 Licensing

NetSendo requires an active license to operate.

### License Plans

| Plan | Price | Features |
|------|-------|----------|
| **SILVER** | Free | All basic features, unlimited contacts |
| **GOLD** | $97/mo | Advanced automations, priority support, API, white-label |

### License Activation

1. Launch the application and go to the main page
2. Register an administrator account
3. On the license page, select SILVER (free) or GOLD plan
4. Enter your email - the license will be automatically activated

---

## 🛠️ Docker Commands

```bash
# Start
docker compose up -d

# Stop
docker compose down

# View logs
docker compose logs -f app

# Shell access
docker exec -it netsendo-app bash

# Artisan commands
docker exec netsendo-app php artisan <command>

# Composer
docker exec netsendo-app composer <command>

# NPM
docker exec netsendo-app npm <command>

# Rebuild images
docker compose up -d --build
```

---

## 📁 Project Structure

```
NetSendo/
├── docker/                 # Docker configuration
│   ├── nginx/             # Nginx config
│   └── php/               # PHP Dockerfile + entrypoint
├── src/                    # Laravel source code
│   ├── app/               # Application logic
│   ├── config/            # Configuration
│   ├── database/          # Migrations and seeders
│   ├── resources/         # Frontend (Vue.js, CSS)
│   ├── routes/            # Routing
│   └── public/            # Public files
├── docker-compose.yml      # Docker services definition
└── README.md              # This file
```

---

## 🔧 Configuration

Configuration is stored in `src/.env` (automatically created from `src/.env.docker`).

### Important Environment Variables

```env
APP_URL=http://localhost:8080
APP_LOCALE=en

# Database (Docker)
DB_HOST=db
DB_DATABASE=netsendo
DB_USERNAME=netsendo
DB_PASSWORD=root

# Redis
REDIS_HOST=redis

# Mail (Mailpit in Docker)
MAIL_HOST=mailpit
MAIL_PORT=1025
```

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

### Update Process

```bash
# Stop containers
docker compose down

# Pull latest version
git pull

# Rebuild and start
docker compose up -d --build
```

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

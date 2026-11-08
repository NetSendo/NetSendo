# Contributing to NetSendo 🚀

Thank you for considering contributing to NetSendo! We welcome contributions from developers of all experience levels.

> **Licence:** NetSendo is proprietary, source-available software under the [NetSendo EULA](LICENSE.md). You may fork and modify it only to prepare pull requests to this repository (EULA §6.8); a fork must not be published elsewhere, rebranded or run in production. Contributions are licensed to NetSendo under EULA §8.5. AI coding tools are fine for contributing — see [AGENTS.md](AGENTS.md).

## 🌟 Ways to Contribute

- ⭐ **Star the repo** — it helps others discover NetSendo
- 🐛 **Report bugs** — open an issue with detailed reproduction steps
- 💡 **Suggest features** — open a feature request issue
- 📝 **Improve documentation** — fix typos, add examples, translate
- 🔧 **Submit pull requests** — fix bugs or implement features

## 🚀 Quick Start for Contributors

### 1. Fork & Clone
```bash
git clone https://github.com/YOUR_USERNAME/NetSendo.git
cd NetSendo
```

### 2. Setup with Docker (recommended)
```bash
cp src/.env.docker src/.env
docker compose -f docker-compose.dev.yml up -d --build
```
The development stack builds the image from source, mounts `./src`, generates `APP_KEY` into `src/.env`, runs the migrations and serves the app on http://localhost:8080 (Vite HMR on 5173).

### Running the tests
```bash
cd src
php artisan test
```
The suite runs on SQLite in memory, so it needs PHP 8.5 with Composer dependencies installed but no Docker. CI runs the same suite on every pull request.

### 3. Create a Branch
```bash
git checkout main && git pull
git checkout -b feature/your-feature-name
# or
git checkout -b fix/bug-description
```

### 4. Make Changes & Submit PR
```bash
git commit -m "feat: add awesome feature"
git push origin feature/your-feature-name
# Open PR on GitHub
```

## 📋 Issue Templates

Use our issue templates:
- 🐛 [Bug Report](.github/ISSUE_TEMPLATE/bug_report.md)
- 💡 [Feature Request](.github/ISSUE_TEMPLATE/feature_request.md)

## 🏗️ Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.5 + Laravel 13 |
| Frontend | Vue.js 3.5 + Inertia 3 + Vite 8 + Tailwind CSS 4 |
| Database | MySQL 8.4 LTS |
| Cache/Queue | Redis 7 + Laravel Reverb (WebSockets) |
| Infrastructure | Docker + Docker Compose (image `ghcr.io/netsendo/netsendo`, Node.js 24 for the build) |
| Mail | Haraka MTA (self-hosted SMTP, NMI) |
| AI | OpenAI / Anthropic / Gemini / Grok, plus OpenRouter and Ollama on GOLD |

## ✅ PR Checklist

- [ ] My code follows the existing code style
- [ ] `php artisan test` passes, and new behaviour has a test
- [ ] I've tested my changes locally with Docker
- [ ] New user-facing texts are translated in all four languages (en, pl, de, es), see [TRANSLATIONS.md](TRANSLATIONS.md)
- [ ] I've updated documentation if needed
- [ ] My commit messages follow [Conventional Commits](https://conventionalcommits.org)

## 💬 Community

- [Telegram](https://t.me/netsendo_official) — product updates and announcements
- [GitHub Issues](https://github.com/NetSendo/NetSendo/issues) — bugs and feature requests
- Security issues: privately, see [SECURITY.md](SECURITY.md)
- Email: support@netsendo.com (technical support), contact@netsendo.com (licensing and business)

## 🙏 Recognition

Every merged contribution keeps its author in the git history and on GitHub's [contributors page](https://github.com/NetSendo/NetSendo/graphs/contributors); changes users notice are described in [CHANGELOG.md](CHANGELOG.md).
Stars ⭐ from contributors are our biggest motivation!

---

**Built with ❤️ by the NetSendo community**

# Contributing to NetSendo 🚀

Thank you for considering contributing to NetSendo! We welcome contributions from developers of all experience levels.

**🏆 Accepted contributions earn a free NetSendo GOLD licence** — 12 months for your first one, no end date from your third. See [GOLD for contributors](#-gold-for-contributors).

> **Licence:** NetSendo is proprietary, source-available software under the [NetSendo EULA](LICENSE.md). You may fork and modify it only to prepare pull requests to this repository (EULA §6.8); a fork must not be published elsewhere, rebranded or run in production. Contributions are licensed to NetSendo under EULA §8.5. AI coding tools are fine for contributing — see [AGENTS.md](AGENTS.md).

## 🌟 Ways to Contribute

- ⭐ **Star the repo** — it helps others discover NetSendo
- 🐛 **Report bugs** — open an issue with detailed reproduction steps
- 💡 **Suggest features** — open a feature request issue
- 📝 **Improve documentation** — fix typos, add examples, translate
- 🔧 **Submit pull requests** — fix bugs or implement features
- 🔒 **Report vulnerabilities** — privately, as described in [SECURITY.md](SECURITY.md)

Looking for something to work on? Issues labelled [`good first issue`](https://github.com/NetSendo/NetSendo/labels/good%20first%20issue) and [`help wanted`](https://github.com/NetSendo/NetSendo/labels/help%20wanted) are a good start. For a larger change, open an issue first so we can agree on the approach before you write the code.

## 🏆 GOLD for contributors

When we merge your contribution, you earn points. Points turn into a free NetSendo GOLD licence: every GOLD feature, for your own installation.

| Points | Your GOLD licence |
|---|---|
| 1 | 12 months |
| 2 | 12 more months on top of the current end date |
| 3 or more | **no end date** (Core Contributor) |

### What earns points

When we merge a pull request, we label it:

- **`reward` — 1 point:** a bug fix with a test, a new feature or a clear improvement, a translation of a substantial part of the interface or a new language, substantial documentation (a guide, integration examples).
- **`reward-large` — 3 points:** a large feature or a substantial piece of work across several areas.

A vulnerability confirmed through [SECURITY.md](SECURITY.md) counts as 3 points too, without a pull request.

These do not earn points: typo, wording and formatting fixes, dependency updates, generated or automated changes, and changes you cannot explain or have not tested. AI coding tools are welcome, but you are responsible for every line you submit. One change split into several pull requests counts once. The maintainers decide what counts and are not obliged to merge any pull request (EULA §6.8). Contributions merged before the programme started count too.

### How to claim your licence

1. When your pull request is merged with a `reward` label, a bot comment confirms the points.
2. Email **contact@netsendo.com** with your GitHub login, the links to your merged pull requests, and the address (domain) of your NetSendo installation. Leave out the address if you have no installation yet.
3. We send you a licence key. Paste it on the **Licence** page in NetSendo. For your next point we extend the same licence.

### The rules

- One licence per person (one GitHub account), for **one installation of the official NetSendo** (the `ghcr.io/netsendo/netsendo` image or a release), used for your own business (EULA §6.1). Hosting NetSendo for clients or as a service is not allowed (EULA §7.1).
- Your fork stays a place to prepare pull requests. It must not run in production, with or without the licence (EULA §6.8).
- The licence is personal. It cannot be transferred, sold or shared, and it has no cash value.
- It is a thank-you, not payment for your code. Your contributions are licensed to NetSendo under EULA §8.5 either way.
- It includes all GOLD features. Support is the same as on SILVER: documentation, issues and community channels.
- We end the licence if its holder breaks the EULA or games the programme, for example with several accounts or with code that is not their own.
- When a licence with an end date ends, NetSendo keeps working on SILVER features and nothing is deleted.
- We may change these rules for the future. Points already earned and licences already granted stay.

The legal terms are in [EULA §5.7](LICENSE.md#57-complimentary-gold-licence).

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

Every merged contribution keeps its author in the git history and on GitHub's [contributors page](https://github.com/NetSendo/NetSendo/graphs/contributors); changes users notice are described in [CHANGELOG.md](CHANGELOG.md), with thanks to their authors. Contributions that earn points also earn a [GOLD licence](#-gold-for-contributors).
Stars ⭐ from contributors are our biggest motivation!

---

**Built with ❤️ by the NetSendo community**

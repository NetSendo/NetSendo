# Contributing to NetSendo 🚀

Thank you for considering contributing to NetSendo! We welcome contributions from developers of all experience levels.

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
cp src/.env.example src/.env.docker
# Edit src/.env.docker — add APP_KEY (php artisan key:generate)
docker-compose up -d
```

### 3. Create a Branch
```bash
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
| Backend | PHP 8.5 + Laravel 12 |
| Frontend | Vue.js 3 + Vite |
| Database | MySQL 8.0 |
| Cache/Queue | Redis |
| Infrastructure | Docker + Docker Compose |
| Mail | Haraka MTA (self-hosted SMTP) |
| AI | OpenAI / Claude / Gemini |

## ✅ PR Checklist

- [ ] My code follows the existing code style
- [ ] I've tested my changes locally with Docker
- [ ] I've updated documentation if needed
- [ ] My commit messages follow [Conventional Commits](https://conventionalcommits.org)

## 💬 Community

- [Telegram Community](https://t.me/netsendo) — ask questions, share ideas
- [Forum](https://forum.netsendo.com) — longer discussions
- Email: support@netsendo.com

## 🙏 Recognition

All contributors are listed in [CHANGELOG.md](CHANGELOG.md). 
Stars ⭐ from contributors are our biggest motivation!

---

**Built with ❤️ by the NetSendo community**

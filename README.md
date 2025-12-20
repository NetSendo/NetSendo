<div align="center">

![NetSendo Logo](https://gregciupek.com/wp-content/uploads/2025/12/Logo-NetSendo-1700-x-500-px.png)

# NetSendo

**Profesjonalny system e-mail marketingu i automatyzacji**

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/NetSendo/NetSendo/releases)
[![PHP](https://img.shields.io/badge/PHP-8.3-purple.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3-green.svg)](https://vuejs.org)
[![License](https://img.shields.io/badge/License-Proprietary-orange.svg)](LICENSE)

[📖 Dokumentacja](https://docs.netsendo.com) • [🎓 Szkolenia](https://netsendo.com/kursy) • [💬 Forum](https://forum.netsendo.com) • [🐛 Zgłoś błąd](https://support.netsendo.com)

</div>

---

## 🚀 O NetSendo

NetSendo to nowoczesna platforma e-mail marketingu i automatyzacji, pozwalająca na:

- 📧 **E-mail Marketing** - Tworzenie i wysyłka kampanii emailowych z zaawansowanym edytorem MJML
- 📱 **SMS Marketing** - Wysyłka wiadomości SMS do subskrybentów
- 🤖 **Automatyzacje** - Tworzenie automatycznych lejków sprzedażowych
- 📊 **Analityka** - Szczegółowe statystyki otwarć, kliknięć i konwersji
- 🎨 **Szablony** - Drag & drop edytor szablonów email
- 🔗 **Integracje AI** - Obsługa OpenAI, Anthropic Claude, Google Gemini
- 📝 **Formularze** - Generowanie formularzy zapisu z webhookami
- 👥 **CRM** - Zarządzanie subskrybentami, grupami i tagami

---

## 📋 Wymagania

- **Docker Desktop** (zalecane) lub:
  - PHP 8.3+
  - MySQL 8.0+
  - Redis
  - Node.js 20+
  - Composer

---

## 🐳 Instalacja (Docker)

### 1. Klonowanie repozytorium

```bash
git clone https://github.com/NetSendo/NetSendo.git
cd NetSendo
```

### 2. Uruchomienie

```bash
docker compose up -d --build
```

Przy pierwszym uruchomieniu kontener automatycznie:
- ✅ Zainstaluje zależności Composer i NPM
- ✅ Wygeneruje klucz aplikacji
- ✅ Uruchomi migracje bazy danych
- ✅ Zbuduje assety frontendowe

### 3. Dostęp do aplikacji

| Usługa | URL | Opis |
|--------|-----|------|
| **NetSendo** | http://localhost:8080 | Panel główny |
| **Mailpit** | http://localhost:8025 | Testowa skrzynka email |
| **MySQL** | localhost:33006 | Baza danych |

---

## 🔑 Licencjonowanie

NetSendo wymaga aktywnej licencji do działania.

### Plany licencyjne

| Plan | Cena | Funkcje |
|------|------|---------|
| **SILVER** | Darmowa | Wszystkie podstawowe funkcje, nieograniczone kontakty |
| **GOLD** | $97/mies. | Zaawansowane automatyzacje, priorytetowe wsparcie, API, white-label |

### Aktywacja licencji

1. Uruchom aplikację i przejdź na stronę główną
2. Zarejestruj konto administratora
3. Na stronie licencji wybierz plan SILVER (darmowy) lub GOLD
4. Wprowadź swój email - licencja zostanie automatycznie aktywowana

---

## 🛠️ Komendy Docker

```bash
# Uruchomienie
docker compose up -d

# Zatrzymanie
docker compose down

# Logi aplikacji
docker compose logs -f app

# Shell w kontenerze
docker exec -it netsendo-app bash

# Artisan
docker exec netsendo-app php artisan <polecenie>

# Composer
docker exec netsendo-app composer <polecenie>

# NPM
docker exec netsendo-app npm <polecenie>

# Przebudowanie obrazów
docker compose up -d --build
```

---

## 📁 Struktura projektu

```
NetSendo/
├── docker/                 # Konfiguracja Docker
│   ├── nginx/             # Konfiguracja Nginx
│   └── php/               # Dockerfile PHP + entrypoint
├── src/                    # Kod źródłowy Laravel
│   ├── app/               # Logika aplikacji
│   ├── config/            # Konfiguracja
│   ├── database/          # Migracje i seedery
│   ├── resources/         # Frontend (Vue.js, CSS)
│   ├── routes/            # Routing
│   └── public/            # Pliki publiczne
├── docker-compose.yml      # Definicja usług Docker
└── README.md              # Ten plik
```

---

## 🔧 Konfiguracja

Konfiguracja znajduje się w pliku `src/.env` (tworzony automatycznie z `src/.env.docker`).

### Ważne zmienne środowiskowe

```env
APP_URL=http://localhost:8080
APP_LOCALE=pl

# Baza danych (Docker)
DB_HOST=db
DB_DATABASE=netsendo
DB_USERNAME=netsendo
DB_PASSWORD=root

# Redis
REDIS_HOST=redis

# Mail (Mailpit w Docker)
MAIL_HOST=mailpit
MAIL_PORT=1025
```

---

## 🌍 Wielojęzyczność

NetSendo wspiera następujące języki:

- 🇵🇱 Polski (domyślny)
- 🇬🇧 English
- 🇩🇪 Deutsch
- 🇪🇸 Español

Zmiana języka: Przycisk w nagłówku aplikacji.

---

## 📈 Aktualizacje

Sprawdź dostępne aktualizacje:
1. W aplikacji: **Ustawienia → Aktualizacje**
2. Na GitHub: [Releases](https://github.com/NetSendo/NetSendo/releases)

### Proces aktualizacji

```bash
# Zatrzymaj kontenery
docker compose down

# Pobierz najnowszą wersję
git pull

# Przebuduj i uruchom
docker compose up -d --build
```

---

## 🤝 Wsparcie

- 📖 **Dokumentacja**: https://docs.netsendo.com
- 💬 **Forum**: https://forum.netsendo.com
- 🎓 **Szkolenia**: https://netsendo.com/kursy
- 🐛 **Zgłoś błąd**: https://support.netsendo.com
- 📧 **Email**: support@netsendo.com

---

## 📄 Licencja

NetSendo jest oprogramowaniem własnościowym. Szczegóły w pliku [LICENSE](LICENSE).

---

<div align="center">

**Made with ❤️ by [NetSendo Team](https://netsendo.com)**

![NetSendo Icon](https://gregciupek.com/wp-content/uploads/2025/12/logo-netsendo-kwadrat-ciemne.png)

</div>

# NetSendo v2

Nowa wersja systemu NetSendo oparta o nowoczesny stack technologiczny.

## Stack
- **Backend**: Laravel 11 (PHP 8.3)
- **Frontend**: Vue.js 3 + Tailwind CSS
- **Baza**: MySQL 8.0
- **Cache/Queue**: Redis
- **Dev Tools**: Mailpit (poczta), Docker

## Uruchomienie (Pierwszy raz)

Wymagane: Docker Desktop.

1. Uruchom skrypt inicjalizacyjny:
   ```bash
   ./init.sh
   ```

2. Aplikacja będzie dostępna pod:
   - Web: http://localhost:8080
   - Mailpit: http://localhost:8025

## Codzienna praca

- Start: `docker compose up -d`
- Stop: `docker compose down`
- Artisan: `docker compose exec -u dev app php artisan ...`
- Composer: `docker compose exec -u dev app composer ...`
- NPM: `docker compose exec -u dev app npm ...`

#!/bin/bash

echo "🚀 Rozpoczynanie konfiguracji Backend Core..."

# 1. Instalacja Breeze (Auth + Frontend scaffolding)
echo "📦 Instalacja Laravel Breeze (Vue + Tailwind)..."
docker compose exec -u dev app composer require laravel/breeze --dev
# Instalacja w trybie non-interactive: stack vue, obsługa dark mode, brak SSR (dla uproszczenia startu)
docker compose exec -u dev app php artisan breeze:install vue --dark --no-interaction

# 2. Instalacja Spatie Permission (RBAC)
echo "🛡 Instalacja Spatie Laravel Permission..."
docker compose exec -u dev app composer require spatie/laravel-permission
docker compose exec -u dev app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# 3. Migracje (tabelki userów, permissions, ról)
echo "🗄 Aktualizacja bazy danych..."
docker compose exec -u dev app php artisan migrate

# 4. Frontend build
echo "🎨 Instalacja i budowanie zależności frontendowych..."
docker compose exec -u dev app npm install
docker compose exec -u dev app npm run build

echo "✅ Backend Core (Auth + RBAC) zainstalowany pomyślnie!"

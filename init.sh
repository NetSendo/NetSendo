#!/bin/bash

echo "🚀 Rozpoczynanie instalacji NetSendo v2..."

# 1. Budowanie kontenerów
echo "📦 Budowanie obrazów Docker..."
docker compose build

# 2. Uruchomienie kontenerów
echo "🔥 Uruchamianie kontenerów..."
docker compose up -d

# Czekamy chwilę na wstanie bazy danych
echo "⏳ Oczekiwanie na bazę danych..."
sleep 10

# 3. Instalacja Laravel
echo "🛠 Instalacja Laravel 11..."
# Używamy --force bo katalog . nie jest pusty (jest tam ten skrypt itp), 
# ale chcemy tam zainstalować laravela.
# Najbezpieczniej czyścić katalog src przed, ale docker volume go mapuje.
# Zróbmy to przez tymczasowy kontener lub po prostu w app
docker compose exec -u dev app composer create-project laravel/laravel .

# 4. Konfiguracja uprawnień (chown już w Dockerfile dla usera dev, ale upewnijmy się)
# docker compose exec app chown -R dev:dev /var/www

# 5. Migracje
echo "🗄 Uruchamianie migracji..."
docker compose exec -u dev app php artisan migrate

echo "✅ Gotowe! Aplikacja dostępna pod http://localhost:8080"

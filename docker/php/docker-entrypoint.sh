#!/bin/bash
set -e

cd /var/www

echo "🚀 Starting NetSendo v2 initialization..."

# Copy .env.docker to .env if .env doesn't exist
if [ ! -f .env ]; then
    if [ -f .env.docker ]; then
        echo "📋 Creating .env from .env.docker..."
        cp .env.docker .env
    else
        echo "⚠️ No .env.docker found, skipping .env creation"
    fi
fi

# Install Composer dependencies if vendor directory doesn't exist
if [ ! -d "vendor" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
else
    echo "✅ Composer dependencies already installed"
fi

# Generate app key if not set
if [ -f .env ] && grep -q "^APP_KEY=$" .env; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# Install NPM dependencies if node_modules doesn't exist
if [ ! -d "node_modules" ]; then
    echo "📦 Installing NPM dependencies..."
    npm install
else
    echo "✅ NPM dependencies already installed"
fi

# Wait for database to be ready
echo "⏳ Waiting for database connection..."
max_attempts=30
attempt=0
until php artisan tinker --execute="DB::connection()->getPdo();" 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ $attempt -ge $max_attempts ]; then
        echo "⚠️ Database not ready yet, continuing anyway..."
        break
    fi
    echo "   Waiting for database... (attempt $attempt/$max_attempts)"
    sleep 2
done

if [ $attempt -lt $max_attempts ]; then
    # Run migrations only if database is ready
    echo "🗄️ Running database migrations..."
    php artisan migrate --force || echo "⚠️ Migration failed or already up to date"
    
    # Create storage link if it doesn't exist
    if [ ! -L "public/storage" ]; then
        echo "🔗 Creating storage link..."
        php artisan storage:link 2>/dev/null || true
    fi
fi

echo "✅ Application initialized successfully!"
echo "📍 Access the app at: http://localhost:8080"
echo "📧 Mailpit available at: http://localhost:8025"

# Execute the main command (php-fpm)
exec php-fpm

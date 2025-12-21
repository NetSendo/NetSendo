#!/bin/bash
set -e

cd /var/www

# Get image version for tracking
IMAGE_VERSION="${NETSENDO_VERSION:-unknown}"
VERSION_FILE="/var/www/storage/.image_version"

echo "🚀 Starting NetSendo initialization..."
echo "📦 Image version: ${IMAGE_VERSION}"

# =============================================================================
# SYNC PUBLIC ASSETS TO SHARED VOLUME
# =============================================================================
# This ensures nginx can serve static files from the shared volume.
# We always sync to ensure new deployments have the latest assets.

echo "📁 Syncing public assets to shared volume..."

# Create backup directory for any user uploads in public (if they exist)
if [ -d "/var/www/public/storage" ] && [ "$(ls -A /var/www/public/storage 2>/dev/null)" ]; then
    echo "   Preserving existing public/storage symlink content..."
fi

# Sync public directory from image to volume
# Using rsync if available, otherwise cp
if command -v rsync &> /dev/null; then
    rsync -a --delete --exclude='storage' /var/www/public.dist/ /var/www/public/
else
    # Fallback: copy files (less efficient but works)
    cp -rf /var/www/public.dist/* /var/www/public/ 2>/dev/null || true
fi

echo "✅ Public assets synced"

# =============================================================================
# ENVIRONMENT CONFIGURATION
# =============================================================================

# Generate app key if not set
if [ -f .env ] && grep -q "^APP_KEY=$" .env; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# =============================================================================
# DATABASE INITIALIZATION
# =============================================================================

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

# =============================================================================
# CACHE OPTIMIZATION
# =============================================================================

echo "🔧 Optimizing application cache..."
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

# Save version for tracking
echo "$IMAGE_VERSION" > "$VERSION_FILE" 2>/dev/null || true

echo "✅ Application initialized successfully!"
echo "📍 NetSendo is ready to serve requests"

# Execute the main command (php-fpm)
exec php-fpm

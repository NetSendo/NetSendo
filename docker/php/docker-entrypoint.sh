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
if [ -d "/var/www/public.dist" ]; then
    # Sync public directory from image to volume
    # Using rsync if available, otherwise cp
    if command -v rsync &> /dev/null; then
        rsync -a --delete --exclude='storage' /var/www/public.dist/ /var/www/public/
    else
        # Fallback: copy files (less efficient but works)
        cp -rf /var/www/public.dist/* /var/www/public/ 2>/dev/null || true
    fi
else
    echo "⚠️ Public assets distribution directory not found, skipping sync (Dev Mode)"
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
    # =============================================================================
    # MIGRATION CHECK AND EXECUTION
    # =============================================================================
    
    echo "🔍 Checking migration status..."
    
    # Get pending migrations count
    PENDING_MIGRATIONS=$(php artisan migrate:status 2>/dev/null | grep -c "Pending" || echo "0")
    echo "   Pending migrations: $PENDING_MIGRATIONS"
    
    if [ "$PENDING_MIGRATIONS" != "0" ]; then
        echo "🗄️ Running $PENDING_MIGRATIONS pending migration(s)..."
        
        # Run migrations with retry mechanism
        MIGRATION_ATTEMPTS=3
        MIGRATION_SUCCESS=false
        
        for i in $(seq 1 $MIGRATION_ATTEMPTS); do
            echo "   Migration attempt $i/$MIGRATION_ATTEMPTS..."
            
            if php artisan migrate --force 2>&1; then
                MIGRATION_SUCCESS=true
                echo "✅ Migrations completed successfully"
                break
            else
                echo "⚠️ Migration attempt $i failed"
                if [ $i -lt $MIGRATION_ATTEMPTS ]; then
                    echo "   Retrying in 5 seconds..."
                    sleep 5
                fi
            fi
        done
        
        # Verify migrations after execution
        STILL_PENDING=$(php artisan migrate:status 2>/dev/null | grep -c "Pending" || echo "0")
        if [ "$STILL_PENDING" != "0" ]; then
            echo "❌ WARNING: $STILL_PENDING migration(s) still pending after $MIGRATION_ATTEMPTS attempts!"
            echo "   Application may not work correctly. Please check logs."
        else
            echo "✅ All migrations verified - database schema is up to date"
        fi
    else
        echo "✅ Database schema is already up to date"
    fi
    
    # =============================================================================
    # DATABASE SEEDING
    # =============================================================================
    
    # Check if starter templates exist and seed if needed
    STARTER_COUNT=$(php artisan tinker --execute="echo App\Models\Template::whereNull('user_id')->count();" 2>/dev/null | tail -1)
    if [ "$STARTER_COUNT" = "0" ] || [ -z "$STARTER_COUNT" ]; then
        echo "🌱 Seeding database with starter templates..."
        php artisan db:seed --force || echo "⚠️ Seeding failed"
    else
        echo "✅ Starter templates already exist ($STARTER_COUNT found), skipping seed"
    fi
    
    # Create storage link if it doesn't exist
    if [ ! -L "public/storage" ]; then
        echo "🔗 Creating storage link..."
        php artisan storage:link 2>/dev/null || true
    fi
    
    # =============================================================================
    # SEED DEFAULT AUTOMATIONS
    # =============================================================================
    # Ensure default AutoTag Pro automations exist for all users.
    # This is safe to run on every startup - the seeder skips existing automations.
    
    echo "🤖 Ensuring default automations exist for all users..."
    php artisan automations:seed-defaults 2>/dev/null || echo "⚠️ Default automations seeding skipped"
    
    # =============================================================================
    # SEED LEAD SCORING RULES
    # =============================================================================
    # Ensure Lead Scoring rules exist for all admin users.
    # This is safe to run on every startup - the seeder only creates rules for users who don't have any.
    
    echo "📊 Ensuring Lead Scoring rules exist for all users..."
    php artisan netsendo:seed-lead-scoring-rules 2>/dev/null || echo "⚠️ Lead Scoring rules seeding skipped"
    
    # =============================================================================
    # CLEANUP STALE DATABASE QUEUE JOBS
    # =============================================================================
    # Jobs may have accumulated in the database queue before switching to Redis.
    # These jobs (scoring, notifications, default) will never be processed since
    # the queue worker now uses Redis. Clean them up to prevent confusion.
    
    STALE_JOBS=$(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null | tail -1)
    if [ -n "$STALE_JOBS" ] && [ "$STALE_JOBS" != "0" ]; then
        echo "🧹 Cleaning up $STALE_JOBS stale job(s) from database queue..."
        php artisan tinker --execute="DB::table('jobs')->truncate();" 2>/dev/null || true
        echo "✅ Stale jobs cleaned up (queue now uses Redis)"
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

# =============================================================================
# EXECUTE MAIN COMMAND
# =============================================================================
# If arguments are passed (e.g., from docker-compose command), execute them.
# Otherwise, run php-fpm as default.

if [ $# -gt 0 ]; then
    echo "📍 Executing custom command: $@"
    exec "$@"
else
    echo "📍 NetSendo is ready to serve requests"
    exec php-fpm
fi

#!/bin/bash
set -e

cd /var/www

# Get image version for tracking
IMAGE_VERSION="${NETSENDO_VERSION:-unknown}"
VERSION_FILE="/var/www/storage/.image_version"

# =============================================================================
# ONE CONTAINER PREPARES THE DATABASE
# =============================================================================
# app, scheduler, queue and reverb all run this entrypoint, and
# `docker compose up -d` starts them together: on a fresh install and on every
# update that ships migrations. Each of them used to migrate and seed at the
# same time ("Table 'users' already exists", seeding into tables that did not
# exist yet), and MySQL cannot roll back DDL, so a migration could be left
# half-applied.
#
# Migrating, seeding and the database upkeep below now run under a MySQL named
# lock (GET_LOCK), held by a small PHP process while one container prepares the
# database; the others wait. The lock lives on the database server, so it works
# whatever the compose file looks like and whether or not the containers share
# a volume or a host, and MySQL drops it as soon as the holder disconnects: a
# container that dies cannot leave it behind.
#
# NETSENDO_RUN_MIGRATIONS=false (docker-compose.yml sets it for scheduler, queue
# and reverb) makes a container leave migrating and seeding to the app: it waits
# until the schema is up to date, then starts. Default: true.
# NETSENDO_MIGRATIONS_WAIT_TIMEOUT (seconds, default 1800) caps how long a
# container waits for another one; after that it starts without preparing the
# database.

case "${NETSENDO_RUN_MIGRATIONS:-true}" in
    [Ff][Aa][Ll][Ss][Ee]|0|[Nn][Oo]|[Oo][Ff][Ff]) RUN_MIGRATIONS=false ;;
    *) RUN_MIGRATIONS=true ;;
esac

MIGRATIONS_WAIT_TIMEOUT="${NETSENDO_MIGRATIONS_WAIT_TIMEOUT:-1800}"
case "$MIGRATIONS_WAIT_TIMEOUT" in
    ''|*[!0-9]*) MIGRATIONS_WAIT_TIMEOUT=1800 ;;
esac

# Lock holder. Talks to the entrypoint over stdout with NETSENDO_LOCK:<state>
# lines, then holds the lock until it is stopped.
DB_LOCK_PHP=$(cat <<'PHP'
$timeout = max(0, (int) ($argv[1] ?? 1800));
$say = function (string $state): void {
    fwrite(STDOUT, "NETSENDO_LOCK:{$state}\n");
    fflush(STDOUT);
};

try {
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $connection = $app->make('db')->connection();
    if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
        $say('unsupported '.$connection->getDriverName());
        exit(0);
    }

    $pdo = $connection->getPdo();
    // An idle session would be closed after wait_timeout, and the lock with it
    $pdo->exec('SET SESSION wait_timeout = 86400');

    // Lock names are server-wide: the database name keeps installations sharing a server apart
    $name = 'netsendo:db-init:'.substr(sha1((string) $connection->getDatabaseName()), 0, 16);
    $deadline = time() + $timeout;
    $getLock = $pdo->prepare('SELECT GET_LOCK(?, ?)');

    while (true) {
        $getLock->execute([$name, max(1, min(10, $deadline - time()))]);
        $acquired = $getLock->fetchColumn();
        $getLock->closeCursor();

        if ((string) $acquired === '1') {
            break;
        }
        if ($acquired === null) {
            $say('error GET_LOCK returned NULL');
            exit(1);
        }
        if (time() >= $deadline) {
            $say('timeout');
            exit(0);
        }
        $say('waiting');
    }

    $say('acquired '.getmypid());
} catch (Throwable $e) {
    $say('error '.str_replace(["\r", "\n"], ' ', $e->getMessage()));
    exit(1);
}

// Hold the lock until the entrypoint stops this process. The ping keeps idle
// timeouts on the server or a proxy from closing the session.
while (true) {
    sleep(30);
    try {
        $pdo->query('SELECT 1')->fetchColumn();
    } catch (Throwable $e) {
        fwrite(STDERR, "⚠️ Database preparation lock lost: {$e->getMessage()}\n");
        exit(1);
    }
}
PHP
)

DB_LOCK_PID=""
DB_LOCK_FD=""

# Usage: acquire_db_lock <timeout seconds>
# Returns 0 when this container holds the lock, 1 when another container kept it
# for longer than the timeout, 2 when there is no lock (not MySQL, or an error).
acquire_db_lock() {
    local line rc=2

    exec {DB_LOCK_FD}< <(exec php -d display_errors=stderr -r "$DB_LOCK_PHP" -- "$1")
    DB_LOCK_PID=$!

    while IFS= read -r -t 300 line <&"$DB_LOCK_FD"; do
        case "$line" in
            "NETSENDO_LOCK:acquired "*)
                DB_LOCK_PID="${line#NETSENDO_LOCK:acquired }"
                return 0
                ;;
            "NETSENDO_LOCK:waiting")
                echo "   ⏳ Another container is preparing the database, waiting..."
                ;;
            "NETSENDO_LOCK:timeout")
                rc=1
                break
                ;;
            "NETSENDO_LOCK:unsupported "*)
                echo "   ℹ️  No database preparation lock for the '${line#NETSENDO_LOCK:unsupported }' driver"
                break
                ;;
            "NETSENDO_LOCK:error "*)
                echo "   ⚠️ Database preparation lock unavailable: ${line#NETSENDO_LOCK:error }"
                break
                ;;
            *)
                echo "   $line"
                ;;
        esac
    done

    release_db_lock
    return $rc
}

release_db_lock() {
    if [ -n "$DB_LOCK_PID" ]; then
        kill "$DB_LOCK_PID" 2>/dev/null || true
        wait "$DB_LOCK_PID" 2>/dev/null || true
        DB_LOCK_PID=""
    fi
    if [ -n "$DB_LOCK_FD" ]; then
        exec {DB_LOCK_FD}<&-
        DB_LOCK_FD=""
    fi
}

trap release_db_lock EXIT

# True when migrate:status runs (on a fresh database it fails: no migrations
# table yet) and lists no pending migration.
database_schema_is_current() {
    local status
    status=$(php artisan migrate:status 2>/dev/null) || return 1
    ! printf '%s\n' "$status" | grep -q "Pending"
}

# Wait until the container that runs the migrations has brought the schema up
# to date. The check runs under the lock, so a migration or seeding in progress
# elsewhere is waited out instead of being seen half-done.
wait_for_database_schema() {
    local started elapsed lock_rc current

    started=$(date +%s)
    while true; do
        elapsed=$(( $(date +%s) - started ))
        lock_rc=0
        acquire_db_lock $(( MIGRATIONS_WAIT_TIMEOUT > elapsed ? MIGRATIONS_WAIT_TIMEOUT - elapsed : 0 )) || lock_rc=$?

        current=false
        if [ "$lock_rc" != "1" ] && database_schema_is_current; then
            current=true
        fi
        release_db_lock

        if [ "$current" = "true" ]; then
            return 0
        fi
        if [ $(( $(date +%s) - started )) -ge "$MIGRATIONS_WAIT_TIMEOUT" ]; then
            return 1
        fi
        echo "   Waiting for the app container to migrate the database..."
        sleep 5
    done
}

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

DB_PREPARE=false
if [ $attempt -lt $max_attempts ]; then
    if [ "$RUN_MIGRATIONS" = "true" ]; then
        echo "🔒 Taking the database preparation lock..."
        lock_rc=0
        acquire_db_lock "$MIGRATIONS_WAIT_TIMEOUT" || lock_rc=$?
        case "$lock_rc" in
            0)
                echo "   Lock acquired"
                DB_PREPARE=true
                ;;
            1)
                echo "⚠️ Another container has been preparing the database for over ${MIGRATIONS_WAIT_TIMEOUT}s"
                echo "   Starting without migrating or seeding in this container"
                ;;
            *)
                echo "   Continuing without the lock"
                DB_PREPARE=true
                ;;
        esac
    else
        echo "⏳ Waiting for the database schema (NETSENDO_RUN_MIGRATIONS=false, the app container migrates)..."
        if wait_for_database_schema; then
            echo "✅ Database schema is up to date"
        else
            echo "⚠️ Database schema still not up to date after ${MIGRATIONS_WAIT_TIMEOUT}s, starting anyway"
        fi
    fi
fi

if [ "$DB_PREPARE" = "true" ]; then
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
        STILL_PENDING=$(php artisan migrate:status 2>/dev/null | grep -c "Pending" || true)
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
    # CLEANUP ORPHANED DATABASE QUEUE JOBS
    # =============================================================================
    # Jobs may have accumulated in the `jobs` table before an installation
    # switched its queue driver to Redis. Those rows will never be processed and
    # are safe to drop.
    #
    # CRITICAL: this must NOT run when the queue driver actually IS `database`
    # (which is the default — see config/queue.php). There the `jobs` table is
    # the live queue: truncating it on every container start silently deleted
    # all pending sends, and because their queue entries were already marked
    # `queued` (a state the cron never re-processes) those recipients were never
    # sent to at all. The driver is therefore read from the app config first.

    QUEUE_DRIVER=$(php artisan tinker --execute="echo config('queue.default');" 2>/dev/null | tail -1)

    if [ "$QUEUE_DRIVER" = "database" ]; then
        echo "ℹ️  Queue driver is 'database' - keeping pending jobs (live queue)"
    else
        STALE_JOBS=$(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null | tail -1)
        if [ -n "$STALE_JOBS" ] && [ "$STALE_JOBS" != "0" ]; then
            echo "🧹 Cleaning up $STALE_JOBS orphaned job(s) from database queue (driver: ${QUEUE_DRIVER:-unknown})..."
            php artisan tinker --execute="DB::table('jobs')->truncate();" 2>/dev/null || true
            echo "✅ Orphaned jobs cleaned up"
        fi
    fi

    echo "🔓 Database prepared"
fi

release_db_lock

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    echo "🔗 Creating storage link..."
    php artisan storage:link 2>/dev/null || true
fi

# =============================================================================
# ENSURE BACKUP TEMP DIRECTORY (GitHub #11)
# =============================================================================
# The backup-temp directory must exist with write permissions for www-data.
# Since storage/app is a Docker volume, the directory from the image may
# not persist across container recreations.

if [ ! -d "storage/app/backup-temp" ]; then
    echo "📦 Creating backup temp directory..."
    mkdir -p storage/app/backup-temp
fi
chmod 775 storage/app/backup-temp 2>/dev/null || true

# =============================================================================
# CACHE OPTIMIZATION
# =============================================================================

echo "🔧 Optimizing application cache..."
php artisan config:cache 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
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

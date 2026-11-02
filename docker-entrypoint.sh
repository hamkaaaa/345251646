#!/bin/bash
set -e

echo "🌸 Melati V2 — Starting deployment..."

# ============================================
# 1. Setup SQLite database on persistent volume
# ============================================
# Render.com uses /data mount, Railway uses RAILWAY_VOLUME_MOUNT_PATH
if [ -n "$RENDER_DISK_MOUNT_PATH" ]; then
    PERSIST_PATH="$RENDER_DISK_MOUNT_PATH"
elif [ -n "$RAILWAY_VOLUME_MOUNT_PATH" ]; then
    PERSIST_PATH="$RAILWAY_VOLUME_MOUNT_PATH"
elif [ -d "/data" ]; then
    PERSIST_PATH="/data"
else
    PERSIST_PATH="/app/database"
fi

DB_PATH="${PERSIST_PATH}/database.sqlite"
echo "📁 Using database path: $DB_PATH"

if [ ! -f "$DB_PATH" ]; then
    echo "🆕 Creating new SQLite database..."
    mkdir -p "$(dirname "$DB_PATH")"
    touch "$DB_PATH"
fi

export DB_DATABASE="$DB_PATH"

# ============================================
# 2. Create required Laravel directories
# ============================================
echo "📂 Ensuring Laravel directories exist..."
mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache/data \
    storage/logs \
    bootstrap/cache
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# ============================================
# 3. Generate APP_KEY if not already set
# ============================================
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# ============================================
# 4. Run database migrations
# ============================================
echo "🗄️  Running migrations..."
php artisan migrate --force

# ============================================
# 5. Seed database (only on first deploy)
# ============================================
SEED_FLAG="${PERSIST_PATH}/.seeded"
if [ ! -f "$SEED_FLAG" ]; then
    echo "🌱 Seeding database with demo data..."
    php artisan db:seed --force
    touch "$SEED_FLAG"
    echo "✅ Database seeded successfully!"
else
    echo "⏭️  Database already seeded, skipping..."
fi

# ============================================
# 6. Laravel optimizations for production
# ============================================
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ============================================
# 7. Start Laravel server
# ============================================
PORT="${PORT:-8080}"
echo "🚀 Starting Melati V2 on port $PORT..."
exec php artisan serve --host=0.0.0.0 --port="$PORT"

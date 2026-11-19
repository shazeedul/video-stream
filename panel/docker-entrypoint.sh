#!/bin/sh
set -e

# Copy .env from .env.example if missing
if [ ! -f /var/www/html/.env ]; then
    echo "==> Creating .env from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Ensure storage directories exist with write permissions
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

find /var/www/html/storage /var/www/html/bootstrap/cache -type d -exec chmod 777 {} + 2>/dev/null || true
find /var/www/html/storage /var/www/html/bootstrap/cache -type f -exec chmod 666 {} + 2>/dev/null || true

# Install composer dependencies if vendor autoload is missing
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "==> Vendor autoload not found. Running composer install..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Ensure application key is set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "==> Generating application key..."
    php artisan key:generate --force
fi

# Wait for PostgreSQL database
echo "==> Waiting for database connection (${DB_HOST:-postgres}:${DB_PORT:-5432})..."
MAX_RETRIES=30
RETRY_COUNT=0
until php -r "try { new PDO('pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_TIMEOUT => 2]); exit(0); } catch (Throwable \$e) { exit(1); }" 2>/dev/null; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ "$RETRY_COUNT" -ge "$MAX_RETRIES" ]; then
        echo "==> Warning: Could not connect to database after $MAX_RETRIES attempts. Continuing..."
        break
    fi
    sleep 1
done
if [ "$RETRY_COUNT" -lt "$MAX_RETRIES" ]; then
    echo "==> Database connected successfully!"
fi

# Run database migrations
echo "==> Running database migrations..."
php artisan migrate --force

# Seed database if no users exist
USER_COUNT=$(php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); try { echo \App\Models\User::count(); } catch (Throwable \$e) { echo 0; }" 2>/dev/null || echo 0)
if [ "$USER_COUNT" = "0" ]; then
    echo "==> Seeding initial demo data (roles, users, demo tenant)..."
    php artisan db:seed --force || echo "==> Seeding skipped or encountered non-fatal error."
fi

# Create public storage link if not exists
if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link || true
fi

# Initialize MinIO buckets
if [ -n "$MINIO_ENDPOINT" ]; then
    echo "==> Initializing MinIO buckets..."
    php artisan storage:minio-init || true
fi

# Initialize RabbitMQ topology
if [ -n "$RABBITMQ_HOST" ]; then
    echo "==> Configuring RabbitMQ topology..."
    php artisan rabbitmq:setup || true
fi

# Check frontend assets build
if [ ! -f /var/www/html/public/build/manifest.json ]; then
    echo "==> Vite manifest not found. Building frontend assets..."
    if command -v pnpm >/dev/null 2>&1; then
        pnpm install
        pnpm run build
    else
        npm install
        npm run build
    fi
fi

echo "==> Wiz Video Panel ready. Starting server..."
exec "$@"

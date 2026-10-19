#!/bin/bash
set -e

# Render injects $PORT (usually 10000). Dynamically configure Apache ports
PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf

# Ensure required directories exist
mkdir -p /var/www/html/resources/views
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/logs

# Connect storage symbolic link for public uploads
php artisan storage:link --no-interaction || true

# Optimize cache for production
php artisan config:cache || true
php artisan route:cache || true

# Auto-run database migrations if enabled via environment variable
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# Auto-run database seeder if enabled via environment variable
if [ "$RUN_SEEDER" = "true" ]; then
    echo "Running database seeder..."
    php artisan db:seed --force
fi

# Ensure web server (www-data) owns all storage and cache directories
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "Starting Apache web server on port $PORT..."
exec apache2-foreground

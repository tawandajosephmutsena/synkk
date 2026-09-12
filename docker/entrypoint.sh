#!/bin/sh
set -e

# Dynamically adapt Nginx port if PORT environment variable is provided by cloud host (Render, Railway, Cloud Run)
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    echo "Configuring Nginx to listen on port $PORT..."
    sed -i "s/listen 80;/listen $PORT;/g" /etc/nginx/nginx.conf
fi

# Ensure APP_KEY exists; auto-generate if empty or unpopulated
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "Generating application encryption key..."
    if [ ! -f /var/www/html/.env ]; then
        touch /var/www/html/.env
    fi
    php artisan key:generate --force --no-interaction
fi

# Ensure SQLite database directory and file exist
DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
DB_DIR="$(dirname "$DB_FILE")"
mkdir -p "$DB_DIR"
if [ ! -f "$DB_FILE" ]; then
    echo "Initializing SQLite database at $DB_FILE..."
    touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE"
fi

# Ensure storage directories exist with proper permissions
mkdir -p /var/www/html/storage/app/private /var/www/html/storage/logs /var/www/html/storage/framework/views /var/www/html/storage/framework/sessions /var/www/html/storage/framework/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache "$DB_DIR"

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force --no-interaction

exec "$@"

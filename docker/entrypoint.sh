#!/bin/sh
set -e

# Ensure SQLite database file exists
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Initializing SQLite database..."
    touch /var/www/html/database/database.sqlite
    chown www-data:www-data /var/www/html/database/database.sqlite
fi

# Ensure storage directories exist with proper permissions
mkdir -p /var/www/html/storage/app/vaults /var/www/html/storage/logs /var/www/html/storage/framework/views /var/www/html/storage/framework/sessions /var/www/html/storage/framework/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force --no-interaction

exec "$@"

#!/bin/sh
set -e

# Dynamically adapt Nginx port if PORT environment variable is provided by cloud host (Render, Railway, Cloud Run)
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    echo "Configuring Nginx to listen on port $PORT..."
    sed -i "s/listen 80;/listen $PORT;/g" /etc/nginx/nginx.conf
fi

# Require APP_KEY to be set externally — never auto-generate in production.
# A container-generated key is lost on restart, invalidating sessions and
# encrypted data. Set APP_KEY in your deployment environment.
if ! php -r '$key = getenv("APP_KEY"); if (! is_string($key)) { exit(1); } if (str_starts_with($key, "base64:")) { $key = base64_decode(substr($key, 7), true); } exit(is_string($key) && strlen($key) === 32 ? 0 : 1);'; then
    echo "FATAL: APP_KEY must be a valid 32-byte key or its base64 encoding." >&2
    echo "Generate a stable key with: php artisan key:generate --show" >&2
    echo "Then set APP_KEY in your deployment environment or secrets manager." >&2
    exit 1
fi

# Ensure SQLite database directory and file exist
DB_FILE="${DB_DATABASE:-/var/www/html/storage/app/database.sqlite}"
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
su-exec www-data php artisan migrate --force --no-interaction

if [ -n "${SYNKK_BOOTSTRAP_EMAIL:-}" ] || [ -n "${SYNKK_BOOTSTRAP_PASSWORD:-}" ] || [ -n "${SYNKK_BOOTSTRAP_NAME:-}" ]; then
    echo "Checking initial administrator bootstrap..."
    su-exec www-data php artisan synkk:bootstrap-admin --if-empty --no-interaction
fi

unset SYNKK_BOOTSTRAP_EMAIL SYNKK_BOOTSTRAP_PASSWORD SYNKK_BOOTSTRAP_NAME SYNKK_BOOTSTRAP_PASSWORD_SOURCE

exec "$@"

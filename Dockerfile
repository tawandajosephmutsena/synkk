# Multi-stage Dockerfile for Synkk (Laravel + SQLite + Nginx + PHP 8.4)
FROM php:8.4-fpm-alpine as base

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    sqlite \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    icui18n \
    icu-dev \
    && docker-php-ext-install pdo_sqlite mbstring zip intl bcmath opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application source
COPY . .

# Install production dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Build frontend assets using Node
FROM node:20-alpine as frontend-builder
WORKDIR /app
COPY package*.json vite.config.js ./
COPY resources ./resources
RUN npm ci && npm run build

# Final Stage
FROM base as final
COPY --from=frontend-builder /app/public/build /var/www/html/public/build

# Setup permissions & database directory
RUN mkdir -p /var/www/html/storage/app/vaults /var/www/html/database \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Copy Nginx and Supervisor configs
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

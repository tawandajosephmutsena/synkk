FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nginx \
    supervisor \
    su-exec \
    curl \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    sqlite \
    && docker-php-ext-install zip pdo pdo_mysql pdo_sqlite pcntl bcmath

WORKDIR /var/www/html

ENV DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/storage/app/database.sqlite

COPY composer.json composer.lock ./
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-scripts --no-autoloader --no-dev

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN composer dump-autoload --optimize \
    && npm run build \
    && rm -rf node_modules

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/synkk-uploads.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-synkk-loopback.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT:-80}/up" || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

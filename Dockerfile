# ──────────────────────────────────────────────────────────────
# Stage 1 — Dépendances Composer (image jetée après le build)
# ──────────────────────────────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ──────────────────────────────────────────────────────────────
# Stage 2 — Image de production (PHP-FPM + Nginx + Supervisor)
# ──────────────────────────────────────────────────────────────
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    nginx supervisor \
    git curl zip unzip libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Vendor depuis le stage build
COPY --from=vendor /app/vendor ./vendor

# Code source
COPY . .

# Finaliser l'autoload avec le code source complet
RUN composer dump-autoload --no-dev --optimize --no-interaction

# Configurations
COPY docker/nginx/nginx.prod.conf /etc/nginx/sites-available/default
COPY docker/php/php.ini           /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/php.prod.ini      /usr/local/etc/php/conf.d/prod.ini
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint : migrations + démarrage supervisord
COPY docker/php/entrypoint.prod.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Droits sur var/ (cache, logs)
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && chmod -R 775 var

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]

FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

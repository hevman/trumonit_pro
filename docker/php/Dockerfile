FROM php:8.5-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        unzip \
        zip \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/TruMonit.ini

WORKDIR /var/www/html

# PHP 8.4 FPM image for the IPMA Dashboard.
# Installs the extensions required by the app and the tuxonice/ipma-api client,
# plus Composer for dependency management inside the container.
FROM php:8.4-fpm-alpine

# System packages required to build PHP extensions and run Composer.
RUN apk add --no-cache \
        git \
        unzip \
        icu-data-full \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        autoconf \
        g++ \
        make \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        zip \
        opcache \
    && apk del --no-network autoconf g++ make

# Install Composer (copied from the official Composer image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Sensible PHP defaults for development.
RUN { \
        echo 'memory_limit = 512M'; \
        echo 'display_errors = Off'; \
        echo 'display_startup_errors = Off'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.validate_timestamps = 1'; \
        echo 'opcache.revalidate_freq = 0'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

# The actual source is mounted as a volume via docker-compose in development.
EXPOSE 9000
CMD ["php-fpm"]

FROM php:8.5.13-fpm-alpine@sha256:51414d5291758fe27f58a64c7e8a078465d32cc7dcef7bda803f5b36019802e0

RUN apk add --no-cache git unzip icu-libs libpq libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev linux-headers postgresql-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pcntl pdo_pgsql sockets zip \
    && pecl install pcov-1.0.12 \
    && docker-php-ext-enable pcov \
    && apk del .build-deps

COPY --from=composer:2.11.2@sha256:50ea4d33fdcde1adb0f045547295faa1548b33872e5f789dae5b02ce2fb5de93 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/local.ini /usr/local/etc/php/conf.d/99-local.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-local.conf

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm", "-F", "-R"]

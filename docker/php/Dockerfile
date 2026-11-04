FROM php:8.5-fpm-alpine

RUN apk add --no-cache git unzip icu-libs libpq libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pcntl pdo_pgsql zip \
    && pecl install pcov-1.0.12 \
    && docker-php-ext-enable pcov \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/local.ini /usr/local/etc/php/conf.d/99-local.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-local.conf

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm", "-F", "-R"]

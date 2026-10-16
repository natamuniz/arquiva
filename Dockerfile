FROM php:8.3-fpm-alpine

# Extensões: PostgreSQL, zip (Composer), intl e pcntl (worker da fila).
RUN apk add --no-cache postgresql-dev libzip-dev icu-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql zip intl pcntl \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/entrypoint.sh /usr/local/bin/arquiva-entrypoint

RUN chmod +x /usr/local/bin/arquiva-entrypoint

WORKDIR /var/www/html

CMD ["php-fpm"]

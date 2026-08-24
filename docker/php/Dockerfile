FROM php:8.4-fpm-alpine3.21

WORKDIR /var/www/backend

RUN apk add --no-cache \
    git \
    unzip \
    curl

COPY php.ini /usr/local/etc/php/conf.d/99-mis.ini

COPY --from=mlocati/php-extension-installer:2.7.8 \
    /usr/bin/install-php-extensions \
    /usr/local/bin/

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    bcmath \
    zip \
    pcntl \
    redis \
    sockets

COPY --from=composer:2.8.8 /usr/bin/composer /usr/local/bin/composer

RUN mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R 775 bootstrap/cache storage

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]

EXPOSE 9000

CMD ["php-fpm"]

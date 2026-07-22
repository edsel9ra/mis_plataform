FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git \
    unzip \
    curl

COPY php.ini /usr/local/etc/php/conf.d/99-mis.ini

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    bcmath \
    zip \
    pcntl \
    redis \
    sockets

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/backend

EXPOSE 9000

CMD ["php-fpm"]

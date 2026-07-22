FROM php:8.4-fpm-alpine

# 1. Definir el directorio de trabajo desde el inicio
WORKDIR /var/www/backend

# 2. Instalar utilidades del sistema
RUN apk add --no-cache \
    git \
    unzip \
    curl

# 3. Copiar archivo de configuración personalizada
COPY php.ini /usr/local/etc/php/conf.d/99-mis.ini

# 4. Instalador de extensiones de PHP
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    bcmath \
    zip \
    pcntl \
    redis \
    sockets

# 5. Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 6. Crear estructura de carpetas necesaria y asignar permisos
RUN mkdir -p bootstrap/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R 775 bootstrap/cache storage

EXPOSE 9000

CMD ["php-fpm"]

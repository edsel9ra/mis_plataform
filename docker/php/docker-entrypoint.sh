#!/bin/sh

set -e

cd /var/www/backend

echo "Verificando directorios de Laravel..."

mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

if [ "$(id -u)" -eq 0 ]; then
    chown -R www-data:www-data bootstrap/cache storage
    chmod -R 775 bootstrap/cache storage
fi

exec "$@"

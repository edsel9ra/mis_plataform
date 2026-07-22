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

chown -R www-data:www-data bootstrap/cache storage
chmod -R 775 bootstrap/cache storage

exec "$@"

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

if [ "${RUN_DATABASE_BOOTSTRAP:-false}" = "true" ]; then
    readiness_file="/tmp/mis-database-ready"
    rm -f "$readiness_file"

    echo "Esperando las dependencias de Laravel..."
    while [ ! -f vendor/autoload.php ]; do
        sleep 1
    done

    echo "Ejecutando migraciones y seeders..."
    php artisan migrate --seed --force --no-interaction
    touch "$readiness_file"
fi

exec "$@"

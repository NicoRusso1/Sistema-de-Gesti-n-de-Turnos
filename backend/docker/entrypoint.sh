#!/bin/sh
set -e

export PORT="${PORT:-80}"
cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "Falta la variable APP_KEY. Generala con: php artisan key:generate --show"
  exit 1
fi

mkdir -p storage/app/private storage/fonts storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
rm -f bootstrap/cache/*.php

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  intentos=0
  until php artisan migrate --force; do
    intentos=$((intentos + 1))
    if [ "$intentos" -ge 15 ]; then
      echo "No se pudo migrar la base de datos despues de $intentos intentos."
      exit 1
    fi
    echo "La base de datos todavia no responde. Reintento $intentos de 15 en 4 segundos."
    sleep 4
  done
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
  php artisan db:seed --force
fi

if [ "${RUN_DEMO_SEEDER:-false}" = "true" ]; then
  php artisan db:seed --class=DemoSeeder --force
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
